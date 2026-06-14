<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
            'is_active' => 'nullable|boolean',
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
            'title.required' => 'Le titre du quiz est obligatoire.',
            'title.max' => 'Le titre ne doit pas dépasser 255 caractères.',
            'duration.integer' => 'La durée doit être un nombre entier.',
            'duration.min' => 'La durée minimale est de 1 minute.',
            'passing_score.required' => 'Le score de réussite est obligatoire.',
            'passing_score.integer' => 'Le score de réussite doit être un nombre entier.',
            'passing_score.min' => 'Le score de réussite ne peut pas être inférieur à 0%.',
            'passing_score.max' => 'Le score de réussite ne peut pas être supérieur à 100%.',
        ];
    }
}
