<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'status', 'priority', 'category_id',
        'assigned_to', 'created_by', 'moodle_user_id', 'moodle_course',
        'moodle_role', 'first_response_at', 'resolved_at'
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(TicketCategory::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function history()
    {
        return $this->hasMany(TicketHistory::class)->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Obtiene la instancia del patrón State para el estado actual del ticket.
     */
    public function state(): \App\States\Ticket\TicketState
    {
        return \App\States\Ticket\TicketStateFactory::make($this);
    }

    /**
     * Transiciona el ticket a un nuevo estado aplicando las reglas del State Pattern.
     */
    public function transitionTo(string $targetStatus, ?User $user = null, ?string $comments = null): Ticket
    {
        return $this->state()->transitionTo($targetStatus, $user, $comments);
    }

    /**
     * Verifica si el ticket puede transicionar al estado indicado.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        return $this->state()->canTransitionTo($targetStatus);
    }

    /**
     * Retorna la lista de transiciones permitidas desde el estado actual.
     *
     * @return string[]
     */
    public function allowedTransitions(): array
    {
        return $this->state()->allowedTransitions();
    }
}
