<?php

namespace Modules\Core\Http\Requests\Concerns;

use Modules\Core\Support\PhoneNumber;

trait NormalizesLearnerPhone
{
    protected function prepareForValidation(): void
    {
        $raw = $this->input('phone');
        $raw = is_string($raw) ? trim($raw) : null;

        $this->merge([
            'phone' => $raw === null || $raw === '' ? null : (PhoneNumber::normalize($raw) ?? $raw),
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
            'user_name' => $this->filled('user_name') ? trim((string) $this->input('user_name')) : null,
        ]);
    }

    protected function identityMessages(): array
    {
        return [
            'email.required_without' => "L'email ou le téléphone est obligatoire",
            'phone.required_without' => "Le téléphone ou l'email est obligatoire",
            'phone.regex' => 'Le numéro de téléphone est invalide.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
        ];
    }
}
