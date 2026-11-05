<?php

namespace App\Models;

use App\Enums\OutcomeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OutcomeEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'source_id',
        'recorded_at',
        'status',
        'noticed_value',
        'final_value',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'date',
            'status' => OutcomeStatus::class,
            'noticed_value' => 'decimal:2',
            'final_value' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function isFinal(): bool
    {
        return $this->status === OutcomeStatus::Final;
    }

    /**
     * Events known as of a date, most recent first. Ties on the same day are
     * broken by insertion order, so the last one recorded wins.
     */
    public function scopeKnownAsOf(Builder $query, Carbon $asOf): Builder
    {
        return $query->whereDate('recorded_at', '<=', $asOf->toDateString())
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');
    }
}
