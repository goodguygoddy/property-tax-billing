<?php

namespace App\Models;

use App\Models\Concerns\HasFeeComputation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceLine extends Model
{
    use HasFeeComputation;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return $this->feeComputationCasts();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function outcomeEvent(): BelongsTo
    {
        return $this->belongsTo(OutcomeEvent::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }
}
