<?php

namespace App\Billing;

use Brick\Math\BigDecimal;

/**
 * Everything needed to explain how a property's fee was arrived at.
 */
final readonly class FeeComputation
{
    /**
     * @param  list<array<string, string>>  $taxBreakdown  per-jurisdiction figures as decimal strings
     */
    public function __construct(
        public BigDecimal $noticedValue,
        public BigDecimal $finalValue,
        public BigDecimal $savings,
        public BigDecimal $feeRate,
        public BigDecimal $rawFee,
        public int $feeCents,
        public bool $waived,
        public bool $capped,
        public array $taxBreakdown,
    ) {}

    /**
     * Attributes for persisting onto an invoice line or adjustment.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'noticed_value' => (string) $this->noticedValue->toScale(2),
            'final_value' => (string) $this->finalValue->toScale(2),
            'savings' => (string) $this->savings,
            'fee_rate' => (string) $this->feeRate,
            'raw_fee' => (string) $this->rawFee,
            'fee_cents' => $this->feeCents,
            'waived' => $this->waived,
            'capped' => $this->capped,
            'tax_breakdown' => $this->taxBreakdown,
        ];
    }
}
