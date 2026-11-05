<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\OutcomeEvent;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_properties_and_events_from_the_sample_file(): void
    {
        $this->artisan('billing:import', ['file' => 'database/data/sample.json'])->assertSuccessful();

        $this->assertSame(3, Client::count());
        $this->assertSame(6, Property::count());
        $this->assertSame(7, OutcomeEvent::count());

        $p1 = Property::find('P1');
        $this->assertTrue($p1->homestead);
        $this->assertSame('C1', $p1->client_id);
        $this->assertSame(['CITY', 'CNTY', 'ISD'], $p1->jurisdictions->pluck('code')->all());

        $e6 = OutcomeEvent::where('source_id', 'E6')->first();
        $this->assertFalse($e6->isFinal());
        $this->assertNull($e6->final_value);
    }

    #[Test]
    public function importing_the_same_file_twice_does_not_duplicate_anything(): void
    {
        $this->artisan('billing:import', ['file' => 'database/data/sample.json'])->assertSuccessful();
        $this->artisan('billing:import', ['file' => 'database/data/sample.json'])
            ->expectsOutputToContain('Events already present (skipped)')
            ->assertSuccessful();

        $this->assertSame(6, Property::count());
        $this->assertSame(7, OutcomeEvent::count());
    }

    #[Test]
    public function it_rejects_an_invalid_file_without_importing_anything(): void
    {
        $path = $this->writeFile([
            'properties' => [
                ['id' => 'P9', 'client_id' => 'C9', 'homestead' => true, 'jurisdictions' => ['ISD']],
            ],
            'events' => [
                ['source_id' => 'E9', 'property_id' => 'P9', 'recorded_at' => '2026-06-01', 'status' => 'final', 'noticed_value' => 100000, 'final_value' => null],
                ['source_id' => 'E10', 'property_id' => 'P9', 'recorded_at' => '2026-06-01', 'status' => 'pending', 'noticed_value' => 100000, 'final_value' => 90000],
                ['source_id' => 'E11', 'property_id' => 'NOPE', 'recorded_at' => '2026-06-01', 'status' => 'final', 'noticed_value' => 100000, 'final_value' => 90000],
            ],
        ]);

        $this->artisan('billing:import', ['file' => $path])
            ->expectsOutputToContain('A final outcome must have a final value')
            ->expectsOutputToContain('A pending outcome cannot have a final value')
            ->assertFailed();

        $this->assertSame(0, Property::count());
        $this->assertSame(0, OutcomeEvent::count());
    }

    #[Test]
    public function it_rejects_unknown_jurisdictions(): void
    {
        $path = $this->writeFile([
            'properties' => [['id' => 'P9', 'client_id' => 'C9', 'homestead' => false, 'jurisdictions' => ['MUD']]],
            'events' => [],
        ]);

        $this->artisan('billing:import', ['file' => $path])->assertFailed();
        $this->assertSame(0, Property::count());
    }

    #[Test]
    public function it_fails_clearly_for_a_missing_file(): void
    {
        $this->artisan('billing:import', ['file' => 'nowhere.json'])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    private function writeFile(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'billing').'.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }
}
