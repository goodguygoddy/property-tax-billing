<?php

namespace Tests\Unit\Billing;

use App\Billing\FeeCalculator;
use App\Billing\TaxCalculator;
use App\Enums\ExemptionType;
use App\Enums\OutcomeStatus;
use App\Models\Jurisdiction;
use App\Models\OutcomeEvent;
use App\Models\Property;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeeCalculatorTest extends TestCase
{
    private FeeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new FeeCalculator(new TaxCalculator);
    }

    #[Test]
    public function it_charges_thirty_percent_of_savings_by_default(): void
    {
        $fee = $this->calculator->compute($this->property(true), $this->outcome('400000', '360000'), enrolledPropertyCount: 1);

        $this->assertSame('709.6', (string) $fee->savings->strippedOfTrailingZeros());
        $this->assertSame(21288, $fee->feeCents);
        $this->assertFalse($fee->waived);
        $this->assertFalse($fee->capped);
    }

    #[Test]
    public function it_charges_twenty_five_percent_for_clients_with_three_or_more_properties(): void
    {
        $fee = $this->calculator->compute($this->property(false), $this->outcome('300000', '285000'), enrolledPropertyCount: 3);

        $this->assertSame('0.25', (string) $fee->feeRate);
        $this->assertSame('69.375', (string) $fee->rawFee->strippedOfTrailingZeros());
    }

    #[Test]
    public function it_rounds_halves_up_to_the_cent(): void
    {
        $fee = $this->calculator->compute($this->property(false), $this->outcome('300000', '285000'), enrolledPropertyCount: 3);

        $this->assertSame(6938, $fee->feeCents); // 69.375 -> 69.38
    }

    #[Test]
    public function it_caps_the_fee_at_the_maximum(): void
    {
        $fee = $this->calculator->compute($this->property(false, ['ISD', 'CNTY']), $this->outcome('4000000', '3200000'), enrolledPropertyCount: 3);

        $this->assertSame('2660', (string) $fee->rawFee->strippedOfTrailingZeros());
        $this->assertSame(250000, $fee->feeCents);
        $this->assertTrue($fee->capped);
    }

    #[Test]
    public function it_waives_fees_under_the_minimum(): void
    {
        $fee = $this->calculator->compute($this->property(true, ['ISD', 'CNTY']), $this->outcome('120000', '118000'), enrolledPropertyCount: 2);

        $this->assertSame('25.08', (string) $fee->savings->strippedOfTrailingZeros());
        $this->assertSame('7.524', (string) $fee->rawFee->strippedOfTrailingZeros());
        $this->assertSame(0, $fee->feeCents);
        $this->assertTrue($fee->waived);
    }

    #[Test]
    public function it_applies_the_minimum_after_rounding(): void
    {
        $property = $this->property(false, ['CITY']);

        // Savings 39.9828 at 25% = 9.9957, which rounds to 10.00 and is therefore not waived.
        $kept = $this->calculator->compute($property, $this->outcome('7689', '0'), enrolledPropertyCount: 3);
        $this->assertSame('9.9957', (string) $kept->rawFee->strippedOfTrailingZeros());
        $this->assertSame(1000, $kept->feeCents);
        $this->assertFalse($kept->waived);

        // One dollar less: 9.9944 rounds to 9.99 and is waived.
        $waived = $this->calculator->compute($property, $this->outcome('7688', '0'), enrolledPropertyCount: 3);
        $this->assertSame(0, $waived->feeCents);
        $this->assertTrue($waived->waived);
    }

    #[Test]
    public function zero_or_negative_savings_produce_no_fee(): void
    {
        $unchanged = $this->calculator->compute($this->property(false), $this->outcome('250000', '250000'), enrolledPropertyCount: 3);
        $increased = $this->calculator->compute($this->property(false), $this->outcome('250000', '260000'), enrolledPropertyCount: 3);

        $this->assertSame(0, $unchanged->feeCents);
        $this->assertSame(0, $increased->feeCents);
        $this->assertTrue($increased->savings->isNegative());
        $this->assertFalse($increased->waived);
    }

    private function outcome(string $noticed, string $final): OutcomeEvent
    {
        return new OutcomeEvent([
            'status' => OutcomeStatus::Final,
            'noticed_value' => $noticed,
            'final_value' => $final,
        ]);
    }

    /**
     * @param  list<string>  $codes
     */
    private function property(bool $homestead, array $codes = ['ISD', 'CNTY', 'CITY']): Property
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
