<?php

namespace Tests\Feature;

use App\Billing\BillingRunException;
use App\Billing\BillingRunner;
use App\Models\Adjustment;
use App\Models\BillingRun;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\OutcomeEvent;
use App\Models\Property;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingRunTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_first_run_reproduces_the_expected_invoices(): void
    {
        $this->importSample();

        $this->artisan('billing:run', ['--as-of' => '2026-07-01'])->assertSuccessful();

        $this->assertSame(3, Invoice::count());
        $this->assertSame(21288, Invoice::where('client_id', 'C1')->sole()->total_cents);
        $this->assertSame(256938, Invoice::where('client_id', 'C2')->sole()->total_cents);
        $this->assertSame(0, Invoice::where('client_id', 'C3')->sole()->total_cents);

        $lines = InvoiceLine::all()->keyBy('property_id');
        $this->assertSame(['P1', 'P2', 'P3', 'P4', 'P5'], $lines->keys()->sort()->values()->all());

        $this->assertLine($lines['P1'], savings: '709.6', fee: 21288, rate: '0.3');
        $this->assertLine($lines['P2'], savings: '277.5', fee: 6938, rate: '0.25');
        $this->assertLine($lines['P3'], savings: '10640', fee: 250000, rate: '0.25', capped: true);
        $this->assertLine($lines['P4'], savings: '0', fee: 0, rate: '0.25');
        $this->assertLine($lines['P5'], savings: '25.08', fee: 0, rate: '0.3', waived: true);

        $this->assertDatabaseMissing('invoice_lines', ['property_id' => 'P6']);
    }

    #[Test]
    public function a_revised_outcome_produces_an_adjustment_and_leaves_the_invoice_untouched(): void
    {
        $this->importSample();
        $this->artisan('billing:run', ['--as-of' => '2026-07-01'])->assertSuccessful();
        $original = Invoice::where('client_id', 'C1')->sole();

        $this->artisan('billing:run', ['--as-of' => '2026-09-01'])->assertSuccessful();

        $this->assertSame(3, Invoice::count());
        $this->assertSame(21288, $original->fresh()->total_cents);

        $adjustment = Adjustment::sole();
        $this->assertSame('C1', $adjustment->client_id);
        $this->assertSame('P1', $adjustment->property_id);
        $this->assertSame($original->id, $adjustment->invoice_id);
        $this->assertSame(21288, $adjustment->previous_fee_cents);
        $this->assertSame(26610, $adjustment->fee_cents);
        $this->assertSame(5322, $adjustment->amount_cents);
        $this->assertSame('E7', $adjustment->outcomeEvent->source_id);
    }

    #[Test]
    public function running_again_with_no_new_information_produces_nothing(): void
    {
        $this->importSample();
        $this->artisan('billing:run', ['--as-of' => '2026-07-01']);
        $this->artisan('billing:run', ['--as-of' => '2026-09-01']);

        $this->artisan('billing:run', ['--as-of' => '2026-09-01'])
            ->expectsOutputToContain('Nothing new to bill.')
            ->assertSuccessful();

        $this->assertSame(3, Invoice::count());
        $this->assertSame(1, Adjustment::count());
        $this->assertTrue(BillingRun::latest('id')->first()->producedNothing());
    }

    #[Test]
    public function a_pending_property_is_billed_once_it_becomes_final(): void
    {
        $this->importSample();
        $this->artisan('billing:run', ['--as-of' => '2026-09-01']);

        OutcomeEvent::factory()->create([
            'property_id' => 'P6',
            'source_id' => null,
            'recorded_at' => '2026-09-10',
            'noticed_value' => '510000',
            'final_value' => '480000',
        ]);

        $this->artisan('billing:run', ['--as-of' => '2026-09-30'])->assertSuccessful();

        // C3 now has a second invoice holding only P6; the first invoice is unchanged.
        $invoices = Invoice::where('client_id', 'C3')->orderBy('id')->get();
        $this->assertCount(2, $invoices);
        $this->assertSame(0, $invoices[0]->total_cents);
        $this->assertSame(['P6'], $invoices[1]->lines->pluck('property_id')->all());

        // Homestead: ISD (30000 * 0.95%) + CNTY (24000 * 0.38%) + CITY (30000 * 0.52%) = 285 + 91.2 + 156 = 532.2; 30% = 159.66
        $this->assertSame(15966, $invoices[1]->total_cents);
    }

    #[Test]
    public function a_second_revision_adjusts_against_the_previously_adjusted_fee(): void
    {
        $this->importSample();
        $this->artisan('billing:run', ['--as-of' => '2026-07-01']);
        $this->artisan('billing:run', ['--as-of' => '2026-09-01']);

        // P1 goes back to its first final value: fee returns to 212.88, so the client is credited the earlier charge.
        OutcomeEvent::factory()->create([
            'property_id' => 'P1',
            'source_id' => 'E8',
            'recorded_at' => '2026-10-01',
            'noticed_value' => '400000',
            'final_value' => '360000',
        ]);

        $this->artisan('billing:run', ['--as-of' => '2026-10-15'])->assertSuccessful();

        $latest = Adjustment::latest('id')->first();
        $this->assertSame(26610, $latest->previous_fee_cents);
        $this->assertSame(21288, $latest->fee_cents);
        $this->assertSame(-5322, $latest->amount_cents);
        $this->assertSame(Invoice::where('client_id', 'C1')->sole()->id, $latest->invoice_id);
    }

    #[Test]
    public function a_revision_that_does_not_change_the_fee_produces_no_adjustment(): void
    {
        $this->importSample();
        $this->artisan('billing:run', ['--as-of' => '2026-09-01']);

        OutcomeEvent::factory()->create([
            'property_id' => 'P4',
            'source_id' => 'E8',
            'recorded_at' => '2026-09-05',
            'noticed_value' => '250000',
            'final_value' => '255000', // still no savings, still $0
        ]);

        $this->artisan('billing:run', ['--as-of' => '2026-09-30'])->expectsOutputToContain('Nothing new to bill.');
    }

    #[Test]
    public function events_recorded_after_the_as_of_date_are_ignored(): void
    {
        $this->importSample();

        $this->artisan('billing:run', ['--as-of' => '2026-06-02'])->assertSuccessful();

        $this->assertSame(['P1'], InvoiceLine::pluck('property_id')->all());
    }

    #[Test]
    public function the_volume_rate_counts_every_enrolled_property_regardless_of_outcome(): void
    {
        $client = Client::factory()->create();
        $billed = Property::factory()->for($client)->inJurisdictions(['CITY'])->create();
        Property::factory()->for($client)->inJurisdictions()->count(2)->create(); // no outcomes at all
        OutcomeEvent::factory()->for($billed)->create(['noticed_value' => '200000', 'final_value' => '100000']);

        app(BillingRunner::class)->run(Carbon::parse('2026-07-01'));

        $this->assertSame('0.25', (string) InvoiceLine::sole()->fee_rate);
    }

    #[Test]
    public function it_refuses_to_run_as_of_a_date_before_an_earlier_run(): void
    {
        $this->importSample();
        $this->artisan('billing:run', ['--as-of' => '2026-09-01']);

        $this->expectException(BillingRunException::class);

        app(BillingRunner::class)->run(Carbon::parse('2026-07-01'));
    }

    private function importSample(): void
    {
        $this->artisan('billing:import', ['file' => 'database/data/sample.json'])->assertSuccessful();
    }

    private function assertLine(InvoiceLine $line, string $savings, int $fee, string $rate, bool $waived = false, bool $capped = false): void
    {
        $this->assertSame($savings, (string) BigDecimal::of($line->savings)->strippedOfTrailingZeros(), "savings for {$line->property_id}");
        $this->assertSame($fee, $line->fee_cents, "fee for {$line->property_id}");
        $this->assertSame($rate, (string) BigDecimal::of($line->fee_rate)->strippedOfTrailingZeros(), "rate for {$line->property_id}");
        $this->assertSame($waived, $line->waived, "waived flag for {$line->property_id}");
        $this->assertSame($capped, $line->capped, "capped flag for {$line->property_id}");
    }
}
