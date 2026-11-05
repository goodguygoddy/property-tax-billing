<?php

namespace App\Models;

use App\Enums\ExemptionType;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Table(key: 'code', keyType: 'string', incrementing: false)]
class Jurisdiction extends Model
{
    protected $fillable = [
        'code',
        'name',
        'rate_per_100',
        'homestead_exemption_type',
        'homestead_exemption_amount',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_100' => 'string',
            'homestead_exemption_type' => ExemptionType::class,
            'homestead_exemption_amount' => 'string',
        ];
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_jurisdiction', 'jurisdiction_code', 'property_id');
    }

    /**
     * Human-readable description of the homestead exemption, for display.
     */
    public function exemptionDescription(): string
    {
        return match ($this->homestead_exemption_type) {
            ExemptionType::None => 'None',
            ExemptionType::Fixed => 'First $'.number_format((float) $this->homestead_exemption_amount).' of value',
            ExemptionType::Percent => rtrim(rtrim(number_format((float) $this->homestead_exemption_amount, 2), '0'), '.').'% of value',
        };
    }
}
