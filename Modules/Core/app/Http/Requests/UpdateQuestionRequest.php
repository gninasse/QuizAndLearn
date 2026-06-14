<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'question_text' => 'required|string',
            'type' => 'required|string|in:single_choice,multiple_choice,true_false,open_text,matching,fill_in_the_blank',
            'points' => 'required|integer|min:0',
            'options' => 'nullable|array',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'question_text.required' => 'Le texte de la question est obligatoire.',
            'type.required' => 'Le type de question est obligatoire.',
            'type.in' => 'Le type de question sélectionné est invalide.',
            'points.required' => 'Le nombre de points est obligatoire.',
            'points.integer' => 'Le nombre de points doit être un nombre entier.',
            'points.min' => 'Le nombre de points ne peut pas être négatif.',
        ];
    }
}
