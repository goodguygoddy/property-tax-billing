<?php

namespace App\Console\Commands;

use App\Billing\BillingRunException;
use App\Billing\BillingRunner;
use App\Models\Adjustment;
use App\Models\Invoice;
use App\Support\Money;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunBillingCommand extends Command
{
    protected $signature = 'billing:run {--as-of= : Bill using outcomes recorded on or before this date (YYYY-MM-DD, default today)}';

    protected $description = 'Issue invoices and adjustments for all outcomes that are final as of a date';

    public function handle(BillingRunner $runner): int
    {
        try {
            $asOf = $this->option('as-of')
                ? Carbon::createFromFormat('Y-m-d', $this->option('as-of'))->startOfDay()
                : Carbon::today();
        } catch (InvalidFormatException) {
            $this->components->error('The --as-of option must be a date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        try {
            $run = $runner->run($asOf);
        } catch (BillingRunException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Billing run #{$run->id} as of {$run->as_of->toDateString()}");

        if ($run->producedNothing()) {
            $this->components->info('Nothing new to bill.');

            return self::SUCCESS;
        }

        if ($run->invoice_count > 0) {
            $this->table(
                ['Invoice', 'Client', 'Lines', 'Total'],
                $run->invoices()->with('lines')->orderBy('id')->get()->map(fn (Invoice $invoice) => [
                    $invoice->number,
                    $invoice->client_id,
                    $invoice->lines->count(),
                    Money::cents($invoice->total_cents),
                ]),
            );
        }

        if ($run->adjustment_count > 0) {
            $this->table(
                ['Adjustment', 'Client', 'Property', 'Adjusts', 'Previous fee', 'New fee', 'Amount'],
                $run->adjustments()->with('invoice')->orderBy('id')->get()->map(fn (Adjustment $adjustment) => [
                    $adjustment->number,
                    $adjustment->client_id,
                    $adjustment->property_id,
                    $adjustment->invoice->number,
                    Money::cents($adjustment->previous_fee_cents),
                    Money::cents($adjustment->fee_cents),
                    Money::cents($adjustment->amount_cents, signed: true),
                ]),
            );
        }

        return self::SUCCESS;
    }
}
