<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\States\Ticket\ClosedState;
use App\States\Ticket\InProgressState;
use App\States\Ticket\OpenState;
use App\States\Ticket\ResolvedState;
use App\States\Ticket\WaitingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'       => 'Test User',
            'email'      => 'test@example.com',
            'password'   => bcrypt('secret'),
            'role'       => 'support',
            'department' => 'TI',
        ]);

        $this->category = TicketCategory::create([
            'name'        => 'Test Category',
            'description' => 'Test Desc',
        ]);
    }

    protected function createTicket(string $status = 'open'): Ticket
    {
        return Ticket::create([
            'title'       => 'Ticket de prueba',
            'description' => 'Descripción de prueba',
            'status'      => $status,
            'priority'    => 'medium',
            'category_id' => $this->category->id,
            'created_by'  => $this->user->id,
        ]);
    }

    public function test_open_ticket_can_transition_to_in_progress()
    {
        $ticket = $this->createTicket('open');

        $this->assertInstanceOf(OpenState::class, $ticket->state());
        $this->assertEquals(['in_progress'], $ticket->allowedTransitions());
        $this->assertTrue($ticket->canTransitionTo('in_progress'));
        $this->assertFalse($ticket->canTransitionTo('resolved'));
        $this->assertFalse($ticket->canTransitionTo('closed'));
        $this->assertFalse($ticket->canTransitionTo('waiting'));

        $ticket->transitionTo('in_progress', $this->user, 'Iniciando atención');

        $this->assertEquals('in_progress', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->assertDatabaseHas('tickets_history', [
            'ticket_id'  => $ticket->id,
            'old_status' => 'open',
            'new_status' => 'in_progress',
            'comments'   => 'Iniciando atención',
        ]);
    }

    public function test_open_ticket_cannot_transition_directly_to_resolved_or_closed()
    {
        $ticket = $this->createTicket('open');

        $this->expectException(InvalidStateTransitionException::class);
        $ticket->transitionTo('resolved', $this->user);
    }

    public function test_in_progress_can_transition_to_waiting_resolved_and_closed()
    {
        // 1. In Progress -> Waiting
        $ticket = $this->createTicket('in_progress');
        $this->assertInstanceOf(InProgressState::class, $ticket->state());
        $this->assertEquals(['waiting', 'resolved', 'closed'], $ticket->allowedTransitions());

        $ticket->transitionTo('waiting', $this->user, 'Esperando info del usuario');
        $this->assertEquals('waiting', $ticket->fresh()->status);

        // 2. In Progress -> Resolved
        $ticket2 = $this->createTicket('in_progress');
        $ticket2->transitionTo('resolved', $this->user, 'Solucionado con éxito');
        $this->assertEquals('resolved', $ticket2->fresh()->status);
        $this->assertNotNull($ticket2->fresh()->resolved_at);

        // 3. In Progress -> Closed (sin requerir resolved_at)
        $ticket3 = $this->createTicket('in_progress');
        $ticket3->transitionTo('closed', $this->user, 'Cerrado sin solución o duplicado');
        $this->assertEquals('closed', $ticket3->fresh()->status);
    }

    public function test_waiting_can_transition_to_in_progress_resolved_and_closed()
    {
        // 1. Waiting -> In Progress
        $ticket = $this->createTicket('waiting');
        $this->assertInstanceOf(WaitingState::class, $ticket->state());
        $this->assertEquals(['in_progress', 'resolved', 'closed'], $ticket->allowedTransitions());

        $ticket->transitionTo('in_progress', $this->user, 'Usuario respondió');
        $this->assertEquals('in_progress', $ticket->fresh()->status);

        // 2. Waiting -> Resolved
        $ticket2 = $this->createTicket('waiting');
        $ticket2->transitionTo('resolved', $this->user, 'Resuelto');
        $this->assertEquals('resolved', $ticket2->fresh()->status);
        $this->assertNotNull($ticket2->fresh()->resolved_at);

        // 3. Waiting -> Closed
        $ticket3 = $this->createTicket('waiting');
        $ticket3->transitionTo('closed', $this->user, 'No respondió en 30 días');
        $this->assertEquals('closed', $ticket3->fresh()->status);
    }

    public function test_waiting_cannot_transition_to_open()
    {
        $ticket = $this->createTicket('waiting');

        $this->expectException(InvalidStateTransitionException::class);
        $ticket->transitionTo('open', $this->user);
    }

    public function test_resolved_and_closed_are_terminal_states()
    {
        $resolvedTicket = $this->createTicket('resolved');
        $this->assertInstanceOf(ResolvedState::class, $resolvedTicket->state());
        $this->assertEmpty($resolvedTicket->allowedTransitions());
        $this->assertFalse($resolvedTicket->canTransitionTo('in_progress'));

        $closedTicket = $this->createTicket('closed');
        $this->assertInstanceOf(ClosedState::class, $closedTicket->state());
        $this->assertEmpty($closedTicket->allowedTransitions());
        $this->assertFalse($closedTicket->canTransitionTo('open'));
    }
}

