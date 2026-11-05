<?php

namespace App\Billing;

use App\Models\Adjustment;
use App\Models\BillingRun;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\OutcomeEvent;
use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Executes a billing run: issues one invoice per client for newly final
 * properties, and an adjustment for each billed property whose outcome
 * has since been revised. Issued documents are never modified.
 */
class BillingRunner
{
    public function __construct(private FeeCalculator $fees) {}

    public function run(Carbon $asOf): BillingRun
    {
        return DB::transaction(function () use ($asOf) {
            $this->guardAgainstRunningBackwards($asOf);

            $run = BillingRun::create(['as_of' => $asOf->toDateString()]);

            $clients = Client::with(['properties.jurisdictions'])->orderBy('id')->get();
            $latestOutcomes = $this->latestOutcomesAsOf($asOf);

            foreach ($clients as $client) {
                $this->billClient($run, $client, $latestOutcomes);
            }

            $run->update([
                'invoice_count' => $run->invoices()->count(),
                'adjustment_count' => $run->adjustments()->count(),
            ]);

            return $run;
        });
    }

    private function billClient(BillingRun $run, Client $client, Collection $latestOutcomes): void
    {
        $enrolledCount = $client->properties->count();
        $propertyIds = $client->properties->pluck('id');

        $billedLines = InvoiceLine::whereIn('property_id', $propertyIds)->get()->keyBy('property_id');
        $latestAdjustments = Adjustment::whereIn('property_id', $propertyIds)
            ->orderBy('id')
            ->get()
            ->keyBy('property_id'); // keyBy keeps the last seen, i.e. the most recent adjustment

        $newLines = [];
        $adjustments = [];

        foreach ($client->properties as $property) {
            /** @var OutcomeEvent|null $outcome */
            $outcome = $latestOutcomes->get($property->id);

            if ($outcome === null || ! $outcome->isFinal()) {
                continue; // nothing to bill yet, or the case was reopened
            }

            $computation = $this->fees->compute($property, $outcome, $enrolledCount);
            $originalLine = $billedLines->get($property->id);

            if ($originalLine === null) {
                $newLines[] = [$property, $outcome, $computation];

                continue;
            }

            // Compare against whatever was billed last: the latest adjustment, or the original line.
            $lastBilled = $latestAdjustments->get($property->id) ?? $originalLine;

            if ($lastBilled->outcome_event_id === $outcome->id || $lastBilled->fee_cents === $computation->feeCents) {
                continue;
            }

            $adjustments[] = [$property, $outcome, $computation, $originalLine, $lastBilled->fee_cents];
        }

        if ($newLines !== []) {
            $this->issueInvoice($run, $client, $newLines);
        }

        foreach ($adjustments as [$property, $outcome, $computation, $originalLine, $previousFeeCents]) {
            $this->issueAdjustment($run, $client, $property, $outcome, $computation, $originalLine, $previousFeeCents);
        }
    }

    /**
     * @param  list<array{0: Property, 1: OutcomeEvent, 2: FeeComputation}>  $lines
     */
    private function issueInvoice(BillingRun $run, Client $client, array $lines): Invoice
    {
        $invoice = $client->invoices()->create([
            'billing_run_id' => $run->id,
            'issued_on' => $run->as_of,
            'total_cents' => array_sum(array_map(fn ($line) => $line[2]->feeCents, $lines)),
        ]);

        foreach ($lines as [$property, $outcome, $computation]) {
            $invoice->lines()->create([
                'property_id' => $property->id,
                'outcome_event_id' => $outcome->id,
                ...$computation->toAttributes(),
            ]);
        }

        return $invoice;
    }

    private function issueAdjustment(
        BillingRun $run,
        Client $client,
        Property $property,
        OutcomeEvent $outcome,
        FeeComputation $computation,
        InvoiceLine $originalLine,
        int $previousFeeCents,
    ): Adjustment {
        return $client->adjustments()->create([
            'billing_run_id' => $run->id,
            'invoice_id' => $originalLine->invoice_id,
            'invoice_line_id' => $originalLine->id,
            'property_id' => $property->id,
            'outcome_event_id' => $outcome->id,
            'issued_on' => $run->as_of,
            'previous_fee_cents' => $previousFeeCents,
            'amount_cents' => $computation->feeCents - $previousFeeCents,
            ...$computation->toAttributes(),
        ]);
    }

    /**
     * The most recent outcome event for every property, considering only
     * events recorded on or before the as-of date.
     *
     * @return Collection<string, OutcomeEvent> keyed by property id
     */
    private function latestOutcomesAsOf(Carbon $asOf): Collection
    {
        return OutcomeEvent::query()
            ->knownAsOf($asOf)
            ->get()
            ->unique('property_id') // first per property is the latest thanks to the scope's ordering
            ->keyBy('property_id');
    }

    /**
     * A run dated before an earlier run would see fewer events than that run
     * did, which could only produce contradictory documents.
     */
    private function guardAgainstRunningBackwards(Carbon $asOf): void
    {
        $latest = BillingRun::query()->max('as_of');

        if ($latest !== null && $asOf->lt(Carbon::parse($latest))) {
            throw new BillingRunException(
                "Cannot run billing as of {$asOf->toDateString()}: a run already exists as of ".Carbon::parse($latest)->toDateString().'.'
            );
        }
    }
}
