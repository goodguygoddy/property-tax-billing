<?php

namespace Tests\Unit\Billing;

use App\Billing\TaxCalculator;
use App\Enums\ExemptionType;
use App\Models\Jurisdiction;
use App\Models\Property;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxCalculatorTest extends TestCase
{
    private TaxCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculator;
    }

    #[Test]
    public function it_taxes_a_non_homestead_property_on_its_full_value(): void
    {
        $property = $this->property(homestead: false, codes: ['ISD', 'CNTY', 'CITY']);

        // 300000 / 100 * (0.95 + 0.38 + 0.52) = 5550
        $this->assertTrue(BigDecimal::of('5550')->isEqualTo($this->calculator->taxAt($property, BigDecimal::of('300000'))));
    }

    #[Test]
    public function it_applies_homestead_exemptions_per_jurisdiction(): void
    {
        $property = $this->property(homestead: true, codes: ['ISD', 'CNTY', 'CITY']);

        $breakdown = collect($this->calculator->breakdown($property, BigDecimal::of('400000')))->keyBy('code');

        $this->assertTrue(BigDecimal::of('300000')->isEqualTo($breakdown['ISD']['taxable'])); // first $100k exempt
        $this->assertTrue(BigDecimal::of('2850')->isEqualTo($breakdown['ISD']['tax']));
        $this->assertTrue(BigDecimal::of('320000')->isEqualTo($breakdown['CNTY']['taxable'])); // 20% exempt
        $this->assertTrue(BigDecimal::of('1216')->isEqualTo($breakdown['CNTY']['tax']));
        $this->assertTrue(BigDecimal::of('400000')->isEqualTo($breakdown['CITY']['taxable'])); // no exemption
        $this->assertTrue(BigDecimal::of('2080')->isEqualTo($breakdown['CITY']['tax']));
    }

    #[Test]
    public function a_fixed_exemption_cannot_take_taxable_value_below_zero(): void
    {
        $property = $this->property(homestead: true, codes: ['ISD']);

        $this->assertTrue($this->calculator->taxAt($property, BigDecimal::of('80000'))->isZero());
    }

    #[Test]
    public function it_keeps_fractional_cents_without_rounding(): void
    {
        $property = $this->property(homestead: false, codes: ['CNTY']);

        // 12345.67 / 100 * 0.38 = 46.913546
        $this->assertSame('46.913546', (string) $this->calculator->taxAt($property, BigDecimal::of('12345.67'))->strippedOfTrailingZeros());
    }

    /**
     * @param  list<string>  $codes
     */
    private function property(bool $homestead, array $codes): Property
    {
        $all = [
            'ISD' => new Jurisdiction(['code' => 'ISD', 'rate_per_100' => '0.95', 'homestead_exemption_type' => ExemptionType::Fixed, 'homestead_exemption_amount' => '100000']),
            'CNTY' => new Jurisdiction(['code' => 'CNTY', 'rate_per_100' => '0.38', 'homestead_exemption_type' => ExemptionType::Percent, 'homestead_exemption_amount' => '20']),
            'CITY' => new Jurisdiction(['code' => 'CITY', 'rate_per_100' => '0.52', 'homestead_exemption_type' => ExemptionType::None]),
        ];

        $property = new Property(['homestead' => $homestead]);
        $property->setRelation('jurisdictions', collect($codes)->map(fn ($code) => $all[$code]));

        return $property;
    }
}
