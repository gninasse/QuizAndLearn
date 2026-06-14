<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArticleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,id',
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
            'title.required' => "Le titre de l'article est obligatoire.",
            'title.max' => 'Le titre ne doit pas dépasser 255 caractères.',
            'group_ids.array' => 'Format de groupes invalide.',
            'group_ids.*.exists' => "Un groupe sélectionné n'existe pas.",
        ];
    }
}
