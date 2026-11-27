<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title'          => 'required_without:asunto|string|max:255',
            'asunto'         => 'required_without:title|string|max:255',
            'description'    => 'required_without:descripcion|string',
            'descripcion'    => 'required_without:description|string',
            'category_id'    => 'nullable|exists:categorias,id',
            'categoria_id'   => 'nullable|exists:categorias,id',
            'priority'         => 'nullable|string',
            'prioridad_codigo' => 'nullable|exists:ticket_prioridad,codigo',
            'prioridad_id'     => 'nullable|exists:ticket_prioridad,codigo',
            'moodle_user_id' => 'nullable|string|max:50',
            'moodle_course'  => 'nullable|string|max:255',
            'moodle_role'    => 'nullable|string|max:50',
        ];
    }

    public function messages()
    {
        return [
            'title.required_without' => 'El título o asunto del ticket es obligatorio.',
            'asunto.required_without' => 'El título o asunto del ticket es obligatorio.',
            'description.required_without' => 'Debes proporcionar una descripción del problema.',
            'descripcion.required_without' => 'Debes proporcionar una descripción del problema.',
        ];
    }
}
