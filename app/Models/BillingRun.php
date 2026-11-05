<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingRun extends Model
{
    protected $fillable = ['as_of', 'invoice_count', 'adjustment_count'];

    protected function casts(): array
    {
        return [
            'as_of' => 'date',
            'invoice_count' => 'integer',
            'adjustment_count' => 'integer',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    public function producedNothing(): bool
    {
        return $this->invoice_count === 0 && $this->adjustment_count === 0;
    }
}
