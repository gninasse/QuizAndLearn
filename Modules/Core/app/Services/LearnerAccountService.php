<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Learner;
use Modules\Core\Models\User;
use Modules\Core\Support\Credentials;
use Spatie\Permission\Models\Role;

class LearnerAccountService
{
    /**
     * Crée l'utilisateur et son profil apprenant.
     *
     * @param  array  $data  name, last_name, user_name?, email?, phone?, password?, avatar?, matricule?
     * @return array{user: User, learner: Learner, password: ?string}
     */
    public function create(array $data, ?array $groupIds, bool $temporaryPassword): array
    {
        $plain = $temporaryPassword ? Credentials::temporaryPassword() : ($data['password'] ?? null);

        return DB::transaction(function () use ($data, $groupIds, $temporaryPassword, $plain) {
            $userName = ! empty($data['user_name'])
                ? $data['user_name']
                : Credentials::uniqueUserName($data['name'], $data['last_name']);

            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'],
                'user_name' => $userName,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($plain),
                'avatar' => $data['avatar'] ?? null,
                'is_active' => true,
            ]);

            if ($temporaryPassword) {
                $user->forceFill(['must_change_password' => true])->save();
            }

            Role::findOrCreate('learner');
            $user->assignRole('learner');
            $user->logRoleToggle('learner', 'assigned');

            $learner = Learner::create([
                'user_id' => $user->id,
                'matricule' => $data['matricule'] ?? null,
            ]);

            if ($groupIds !== null) {
                $learner->groups()->sync($groupIds);
            }

            return [
                'user' => $user,
                'learner' => $learner,
                'password' => $temporaryPassword ? $plain : null,
            ];
        });
    }
}
