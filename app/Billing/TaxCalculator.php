<?php

namespace App\Billing;

use App\Enums\ExemptionType;
use App\Models\Jurisdiction;
use App\Models\Property;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Computes property tax exactly (no rounding) across a property's jurisdictions.
 */
class TaxCalculator
{
    /**
     * Tax owed on a property at the given appraised value, summed across jurisdictions.
     */
    public function taxAt(Property $property, BigDecimal $value): BigDecimal
    {
        return collect($this->breakdown($property, $value))
            ->reduce(fn (BigDecimal $sum, array $row) => $sum->plus($row['tax']), BigDecimal::zero());
    }

    /**
     * Per-jurisdiction taxable value and tax at the given appraised value.
     *
     * @return list<array{code: string, rate: BigDecimal, taxable: BigDecimal, tax: BigDecimal}>
     */
    public function breakdown(Property $property, BigDecimal $value): array
    {
        return $property->jurisdictions
            ->map(function (Jurisdiction $jurisdiction) use ($property, $value) {
                $taxable = $this->taxableValue($jurisdiction, $property->homestead, $value);

                return [
                    'code' => $jurisdiction->code,
                    'rate' => BigDecimal::of($jurisdiction->rate_per_100),
                    'taxable' => $taxable,
                    'tax' => $this->tax($taxable, $jurisdiction),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The value a jurisdiction actually taxes, after any homestead exemption.
     */
    public function taxableValue(Jurisdiction $jurisdiction, bool $homestead, BigDecimal $value): BigDecimal
    {
        if (! $homestead) {
            return $value;
        }

        $amount = BigDecimal::of($jurisdiction->homestead_exemption_amount ?? '0');

        return match ($jurisdiction->homestead_exemption_type) {
            ExemptionType::None => $value,
            ExemptionType::Fixed => BigDecimal::max($value->minus($amount), BigDecimal::zero()),
            ExemptionType::Percent => $value->minus(
                $value->multipliedBy($amount)->dividedBy(100, $value->getScale() + $amount->getScale() + 2, RoundingMode::Unnecessary)
            ),
        };
    }

    /**
     * tax = taxable value / 100 * rate, computed exactly.
     */
    private function tax(BigDecimal $taxable, Jurisdiction $jurisdiction): BigDecimal
    {
        $rate = BigDecimal::of($jurisdiction->rate_per_100);

        return $taxable->multipliedBy($rate)
            ->dividedBy(100, $taxable->getScale() + $rate->getScale() + 2, RoundingMode::Unnecessary);
    }
}
