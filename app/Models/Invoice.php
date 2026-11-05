<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = ['client_id', 'billing_run_id', 'issued_on', 'total_cents'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'total_cents' => 'integer',
        ];
    }

    protected function number(): Attribute
    {
        return Attribute::get(fn () => sprintf('INV-%05d', $this->id));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function billingRun(): BelongsTo
    {
        return $this->belongsTo(BillingRun::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }
}
