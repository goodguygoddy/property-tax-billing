<?php

namespace Database\Seeders;

use App\Enums\ExemptionType;
use App\Models\Jurisdiction;
use Illuminate\Database\Seeder;

class JurisdictionSeeder extends Seeder
{
    public function run(): void
    {
        $jurisdictions = [
            ['code' => 'ISD', 'name' => 'School district', 'rate_per_100' => '0.95', 'homestead_exemption_type' => ExemptionType::Fixed, 'homestead_exemption_amount' => '100000'],
            ['code' => 'CNTY', 'name' => 'County', 'rate_per_100' => '0.38', 'homestead_exemption_type' => ExemptionType::Percent, 'homestead_exemption_amount' => '20'],
            ['code' => 'CITY', 'name' => 'City', 'rate_per_100' => '0.52', 'homestead_exemption_type' => ExemptionType::None, 'homestead_exemption_amount' => null],
        ];

        foreach ($jurisdictions as $attributes) {
            Jurisdiction::updateOrCreate(['code' => $attributes['code']], $attributes);
        }
    }
}
