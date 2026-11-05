<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(keyType: 'string', incrementing: false)]
class Client extends Model
{
    use HasFactory;

    protected $fillable = ['id'];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    /**
     * Total billed to date: issued invoices plus adjustments (credits are negative).
     */
    public function balanceCents(): int
    {
        return (int) $this->invoices()->sum('total_cents') + (int) $this->adjustments()->sum('amount_cents');
    }
}
