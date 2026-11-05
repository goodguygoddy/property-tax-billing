<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table(keyType: 'string', incrementing: false)]
class Property extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'client_id', 'homestead'];

    protected function casts(): array
    {
        return [
            'homestead' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function jurisdictions(): BelongsToMany
    {
        return $this->belongsToMany(Jurisdiction::class, 'property_jurisdiction', 'property_id', 'jurisdiction_code')
            ->orderBy('code');
    }

    public function outcomeEvents(): HasMany
    {
        return $this->hasMany(OutcomeEvent::class);
    }

    /** The original invoice line for this property, if it has been billed. */
    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }
}
