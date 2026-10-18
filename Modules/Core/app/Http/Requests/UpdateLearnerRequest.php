<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Http\Requests\Concerns\NormalizesLearnerPhone;

class UpdateLearnerRequest extends FormRequest
{
    use NormalizesLearnerPhone;

    public function rules(): array
    {
        $userId = $this->route('id');
        $learner = \Modules\Core\Models\Learner::where('user_id', $userId)->first();
        $learnerId = $learner ? $learner->id : null;

        return [
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'user_name' => 'nullable|string|max:255|unique:users,user_name,'.$userId,
            'email' => 'nullable|required_without:phone|email|unique:users,email,'.$userId,
            'phone' => ['nullable', 'required_without:email', 'regex:/^\+\d{8,15}$/', 'unique:users,phone,'.$userId],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
            'avatar' => 'nullable|image|max:2048',
            'matricule' => [
                'nullable',
                'string',
                \Illuminate\Validation\Rule::unique('learners', 'matricule')->ignore($learnerId),
            ],
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
        return $this->identityMessages() + [
            'name.required' => 'Le prénom est obligatoire',
            'last_name.required' => 'Le nom est obligatoire',
            'user_name.unique' => "Ce nom d'utilisateur existe déjà",
            'email.email' => "L'email doit être valide",
            'email.unique' => 'Cet email existe déjà',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.confirmed' => 'Les mots de passe ne correspondent pas',
            'matricule.unique' => 'Ce matricule existe déjà',
        ];
    }
}
