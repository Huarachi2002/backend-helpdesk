<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;

class TicketAssignmentService
{
    public function assign(Ticket $ticket): ?User
    {
        $agent = User::whereIn('role', ['admin', 'support'])
            ->withCount(['assignedTickets' => function ($query) use ($ticket) {
                $query->whereIn('status', ['open', 'in_progress', 'waiting']);
            }])
            ->orderBy('assigned_tickets_count', 'asc')
            ->first();
        
        if ($agent) {
            $ticket->assigned_to = $agent->id;
            $ticket->save();
            return $agent;
        }

        $ticket->assigned_to = null;
        $ticket->save();
        return null;
    }
}