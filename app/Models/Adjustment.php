<?php

namespace App\Models;

use App\Models\Concerns\HasFeeComputation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjustment extends Model
{
    use HasFeeComputation;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'previous_fee_cents' => 'integer',
            'amount_cents' => 'integer',
            ...$this->feeComputationCasts(),
        ];
    }

    protected function number(): Attribute
    {
        return Attribute::get(fn () => sprintf('ADJ-%05d', $this->id));
    }

    public function isCredit(): bool
    {
        return $this->amount_cents < 0;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function billingRun(): BelongsTo
    {
        return $this->belongsTo(BillingRun::class);
    }

    /** The original invoice this adjustment corrects. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function outcomeEvent(): BelongsTo
    {
        return $this->belongsTo(OutcomeEvent::class);
    }
}
