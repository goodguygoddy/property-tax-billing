<?php

namespace App\Billing;

use App\Models\OutcomeEvent;
use App\Models\Property;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Applies the fee rules to a property's final outcome.
 */
class FeeCalculator
{
    public function __construct(private TaxCalculator $taxes) {}

    /**
     * @param  int  $enrolledPropertyCount  how many properties the client has enrolled, which sets the rate
     */
    public function compute(Property $property, OutcomeEvent $outcome, int $enrolledPropertyCount): FeeComputation
    {
        $noticed = BigDecimal::of($outcome->noticed_value);
        $final = BigDecimal::of($outcome->final_value);

        $atNoticed = $this->taxes->breakdown($property, $noticed);
        $atFinal = $this->taxes->breakdown($property, $final);

        $savings = $this->sum($atNoticed)->minus($this->sum($atFinal));
        $rate = $this->rateFor($enrolledPropertyCount);

        // All intermediate math is exact; only the final fee is rounded, halves up.
        $rawFee = $savings->isNegativeOrZero() ? BigDecimal::zero() : $savings->multipliedBy($rate);
        $rounded = $rawFee->toScale(2, RoundingMode::HalfUp);

        $waived = $rounded->isPositive() && $rounded->isLessThan(config('billing.minimum_fee'));
        $capped = $rounded->isGreaterThan(config('billing.maximum_fee'));

        $fee = match (true) {
            $waived => BigDecimal::zero(),
            $capped => BigDecimal::of(config('billing.maximum_fee')),
            default => $rounded,
        };

        return new FeeComputation(
            noticedValue: $noticed,
            finalValue: $final,
            savings: $savings,
            feeRate: $rate,
            rawFee: $rawFee,
            feeCents: $fee->multipliedBy(100)->toScale(0)->toInt(),
            waived: $waived,
            capped: $capped,
            taxBreakdown: $this->describeBreakdown($atNoticed, $atFinal),
        );
    }

    public function rateFor(int $enrolledPropertyCount): BigDecimal
    {
        $rate = $enrolledPropertyCount >= config('billing.volume_threshold')
            ? config('billing.volume_rate')
            : config('billing.standard_rate');

        return BigDecimal::of($rate);
    }

    /**
     * @param  list<array{code: string, rate: BigDecimal, taxable: BigDecimal, tax: BigDecimal}>  $rows
     */
    private function sum(array $rows): BigDecimal
    {
        return collect($rows)->reduce(fn (BigDecimal $sum, array $row) => $sum->plus($row['tax']), BigDecimal::zero());
    }

    /**
     * Pair the noticed and final figures per jurisdiction as plain strings for storage.
     *
     * @return list<array<string, string>>
     */
    private function describeBreakdown(array $atNoticed, array $atFinal): array
    {
        return collect($atNoticed)->zip($atFinal)->map(fn ($pair) => [
            'code' => $pair[0]['code'],
            'rate' => (string) $pair[0]['rate'],
            'taxable_at_noticed' => (string) $pair[0]['taxable'],
            'tax_at_noticed' => (string) $pair[0]['tax'],
            'taxable_at_final' => (string) $pair[1]['taxable'],
            'tax_at_final' => (string) $pair[1]['tax'],
            'savings' => (string) $pair[0]['tax']->minus($pair[1]['tax']),
        ])->all();
    }
}
