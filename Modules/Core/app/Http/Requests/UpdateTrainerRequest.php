<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainerRequest extends FormRequest
{
    public function rules(): array
    {
        $trainerUserId = $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'user_name' => 'required|string|max:255|unique:users,user_name,'.$trainerUserId,
            'email' => 'required|email|unique:users,email,'.$trainerUserId,
            'phone' => 'nullable|string|max:255',
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
            'avatar' => 'nullable|image|max:2048',
            'specialty' => 'nullable|string|max:255',
            'biography' => 'nullable|string',
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
            'name.required' => 'Le prénom est obligatoire',
            'last_name.required' => 'Le nom est obligatoire',
            'user_name.required' => "Le nom d'utilisateur est obligatoire",
            'user_name.unique' => "Ce nom d'utilisateur existe déjà",
            'email.required' => "L'email est obligatoire",
            'email.email' => "L'email doit être valide",
            'email.unique' => 'Cet email existe déjà',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.confirmed' => 'Les mots de passe ne correspondent pas',
        ];
    }
}
