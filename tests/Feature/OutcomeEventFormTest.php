<?php

namespace Tests\Feature;

use App\Enums\OutcomeStatus;
use App\Models\OutcomeEvent;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutcomeEventFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_form_lists_properties(): void
    {
        $property = Property::factory()->inJurisdictions()->create();

        $this->get(route('outcomes.create'))
            ->assertOk()
            ->assertSee($property->id);
    }

    #[Test]
    public function it_records_a_final_outcome(): void
    {
        $property = Property::factory()->inJurisdictions()->create();

        $this->post(route('outcomes.store'), [
            'property_id' => $property->id,
            'recorded_at' => '2026-07-04',
            'status' => 'final',
            'noticed_value' => '300000',
            'final_value' => '270000.50',
        ])->assertRedirect(route('clients.show', $property->client_id))
            ->assertSessionHas('status');

        $event = OutcomeEvent::sole();
        $this->assertNull($event->source_id);
        $this->assertSame(OutcomeStatus::Final, $event->status);
        $this->assertSame('270000.50', $event->final_value);
    }

    #[Test]
    public function it_records_a_pending_outcome_with_a_blank_final_value(): void
    {
        $property = Property::factory()->inJurisdictions()->create();

        $this->post(route('outcomes.store'), [
            'property_id' => $property->id,
            'recorded_at' => '2026-07-04',
            'status' => 'pending',
            'noticed_value' => '300000',
            'final_value' => '',
        ])->assertRedirect();

        $this->assertNull(OutcomeEvent::sole()->final_value);
    }

    #[Test]
    public function a_final_outcome_requires_a_final_value(): void
    {
        $property = Property::factory()->inJurisdictions()->create();

        $this->from(route('outcomes.create'))->post(route('outcomes.store'), [
            'property_id' => $property->id,
            'recorded_at' => '2026-07-04',
            'status' => 'final',
            'noticed_value' => '300000',
            'final_value' => '',
        ])->assertRedirect(route('outcomes.create'))
            ->assertSessionHasErrors(['final_value']);

        $this->assertSame(0, OutcomeEvent::count());
    }

    #[Test]
    public function a_pending_outcome_cannot_have_a_final_value(): void
    {
        $property = Property::factory()->inJurisdictions()->create();

        $this->post(route('outcomes.store'), [
            'property_id' => $property->id,
            'recorded_at' => '2026-07-04',
            'status' => 'pending',
            'noticed_value' => '300000',
            'final_value' => '250000',
        ])->assertSessionHasErrors(['final_value']);
    }

    #[Test]
    public function it_validates_the_other_fields(): void
    {
        $this->post(route('outcomes.store'), [
            'property_id' => 'UNKNOWN',
            'recorded_at' => 'not a date',
            'status' => 'maybe',
            'noticed_value' => '-5',
        ])->assertSessionHasErrors(['property_id', 'recorded_at', 'status', 'noticed_value']);
    }
}
