<?php

namespace App\Exceptions;

use Exception;

class InvalidStateTransitionException extends Exception
{
    protected string $currentState;
    protected string $targetState;
    protected array $allowedTransitions;

    public function __construct(string $currentState, string $targetState, array $allowedTransitions = [])
    {
        $allowed = empty($allowedTransitions)
            ? 'ninguno (estado final)'
            : implode(', ', $allowedTransitions);

        $message = "Transición de estado inválida: no es posible cambiar de '{$currentState}' a '{$targetState}'. Transiciones permitidas: [{$allowed}].";

        parent::__construct($message, 422);

        $this->currentState = $currentState;
        $this->targetState = $targetState;
        $this->allowedTransitions = $allowedTransitions;
    }

    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    public function getTargetState(): string
    {
        return $this->targetState;
    }

    public function getAllowedTransitions(): array
    {
        return $this->allowedTransitions;
    }

    public function render($request)
    {
        return response()->json([
            'message'             => $this->getMessage(),
            'error'               => 'invalid_state_transition',
            'current_status'      => $this->currentState,
            'target_status'       => $this->targetState,
            'allowed_transitions' => $this->allowedTransitions,
        ], 422);
    }
}

