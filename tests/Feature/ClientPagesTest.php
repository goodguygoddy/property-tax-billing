<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('billing:import', ['file' => 'database/data/sample.json']);
        $this->artisan('billing:run', ['--as-of' => '2026-07-01']);
        $this->artisan('billing:run', ['--as-of' => '2026-09-01']);
    }

    #[Test]
    public function the_client_list_shows_totals(): void
    {
        $this->get(route('clients.index'))
            ->assertOk()
            ->assertSeeInOrder(['C1', '$212.88', '+$53.22', '$266.10'])
            ->assertSee('$2,569.38')
            ->assertSee('25%');
    }

    #[Test]
    public function the_client_page_explains_each_charge(): void
    {
        $this->get(route('clients.show', 'C2'))
            ->assertOk()
            ->assertSee('INV-00002')
            ->assertSee('$2,569.38')
            ->assertSee('$69.375') // raw fee before rounding
            ->assertSee('Capped')
            ->assertSee('Taxable at noticed');
    }

    #[Test]
    public function the_client_page_shows_adjustments_against_the_original_invoice(): void
    {
        $this->get(route('clients.show', 'C1'))
            ->assertOk()
            ->assertSee('ADJ-00001')
            ->assertSee('adjusts INV-00001')
            ->assertSee('+$53.22')
            ->assertSee('Adjusted by ADJ-00001');
    }

    #[Test]
    public function the_home_page_redirects_to_clients(): void
    {
        $this->get('/')->assertRedirect(route('clients.index'));
    }

    #[Test]
    public function unknown_clients_are_not_found(): void
    {
        $this->get(route('clients.show', 'C999'))->assertNotFound();
    }
}
