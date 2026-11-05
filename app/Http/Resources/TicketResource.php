<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority,

            'moodle_data' => [
                'user_id' => $this->moodle_user_id,
                'course'  => $this->moodle_course,
                'role'    => $this->moodle_role,
            ],

            'category' => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),

            'assigned_to' => $this->whenLoaded('assignee', fn() => $this->assignee ? [
                'id'    => $this->assignee->id,
                'name'  => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null),

            'created_by' => $this->whenLoaded('creator', fn() => $this->creator ? [
                'id'    => $this->creator->id,
                'name'  => $this->creator->name,
                'email' => $this->creator->email,
            ] : null),

            'history' => $this->whenLoaded('history', fn() =>
                $this->history->map(fn($h) => [
                    'old_status' => $h->old_status,
                    'new_status' => $h->new_status,
                    'comments'   => $h->comments,
                    'changed_by' => $h->changer ? $h->changer->name : null,
                    'changed_at' => $h->created_at->toDateTimeString(),
                ])
            ),

            'times' => [
                'first_response_at' => $this->first_response_at?->toDateTimeString(),
                'resolved_at'       => $this->resolved_at?->toDateTimeString(),
                'created_at'        => $this->created_at->toDateTimeString(),
                'updated_at'        => $this->updated_at->toDateTimeString(),
            ],
        ];
    }
}
