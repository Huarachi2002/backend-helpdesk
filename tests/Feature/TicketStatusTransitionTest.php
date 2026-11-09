<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;
    protected TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::create([
            'name'       => 'Agente Test',
            'email'      => 'agente@test.com',
            'password'   => bcrypt('password'),
            'role'       => 'support',
            'department' => 'TI',
        ]);

        $this->category = TicketCategory::create([
            'name'        => 'Conectividad',
            'description' => 'Fallas de red',
        ]);

        Sanctum::actingAs($this->agent);
    }

    public function test_api_allows_valid_status_transition()
    {
        $ticket = Ticket::create([
            'title'       => 'Error de acceso',
            'description' => 'No puedo ingresar',
            'status'      => 'open',
            'priority'    => 'medium',
            'category_id' => $this->category->id,
            'created_by'  => $this->agent->id,
        ]);

        $response = $this->patchJson("/api/tickets/{$ticket->id}/status", [
            'status'   => 'in_progress',
            'comments' => 'Tomando el caso',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.status_label', 'En Proceso')
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                    'status_label',
                    'allowed_transitions',
                ]
            ]);

        $this->assertEquals('in_progress', $ticket->fresh()->status);
    }

    public function test_api_rejects_invalid_status_transition_with_422()
    {
        $ticket = Ticket::create([
            'title'       => 'Error de acceso',
            'description' => 'No puedo ingresar',
            'status'      => 'open',
            'priority'    => 'medium',
            'category_id' => $this->category->id,
            'created_by'  => $this->agent->id,
        ]);

        // Intentar pasar directamente de Abierto a Resuelto (inválido)
        $response = $this->patchJson("/api/tickets/{$ticket->id}/status", [
            'status'   => 'resolved',
            'comments' => 'Saltando estados',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'invalid_state_transition')
            ->assertJsonPath('current_status', 'open')
            ->assertJsonPath('target_status', 'resolved')
            ->assertJsonPath('allowed_transitions', ['in_progress']);

        $this->assertEquals('open', $ticket->fresh()->status);
    }
}

