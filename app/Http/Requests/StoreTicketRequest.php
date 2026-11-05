<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority' => 'nullable|in:low,medium,high,critical',
            // Datos de Moodle
            'moodle_user_id' => 'nullable|string|max:50',
            'moodle_course' => 'nullable|string|max:255',
            'moodle_role' => 'nullable|string|max:50',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'El titulo del ticket es obligatorio.',
            'description.required' => 'Debes proporcionar una descripción del problema.',
            'category_id.exists' => 'La categoria seleccionada no es valida.'
        ];
    }
}
