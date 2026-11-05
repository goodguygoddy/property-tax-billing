<?php

namespace App\Models\Concerns;

/**
 * Shared casts for records that store how a fee was computed
 * (invoice lines and adjustments).
 */
trait HasFeeComputation
{
    protected function feeComputationCasts(): array
    {
        return [
            'noticed_value' => 'decimal:2',
            'final_value' => 'decimal:2',
            'savings' => 'string',
            'fee_rate' => 'string',
            'raw_fee' => 'string',
            'fee_cents' => 'integer',
            'waived' => 'boolean',
            'capped' => 'boolean',
            'tax_breakdown' => 'array',
        ];
    }

    /** Fee rate as a whole-number percentage, e.g. "30%". */
    public function feeRatePercent(): string
    {
        return rtrim(rtrim(number_format((float) $this->fee_rate * 100, 2), '0'), '.').'%';
    }
}
