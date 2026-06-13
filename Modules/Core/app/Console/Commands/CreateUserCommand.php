<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cores:create-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un nouvel utilisateur interactivement';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text(
            label: 'Prénom',
            placeholder: 'ex: Jean',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 2 => 'Le prénom doit contenir au moins 2 caractères.',
                default => null
            }
        );

        $lastName = text(
            label: 'Nom',
            placeholder: 'ex: Dupont',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 2 => 'Le nom doit contenir au moins 2 caractères.',
                default => null
            }
        );

        $userName = text(
            label: 'Nom d\'utilisateur',
            placeholder: 'ex: jdupont',
            required: true,
            validate: fn (string $value) => match (true) {
                User::where('user_name', $value)->exists() => 'Ce nom d\'utilisateur est déjà pris.',
                strlen($value) < 3 => 'Le nom d\'utilisateur doit contenir au moins 3 caractères.',
                default => null
            }
        );

        $email = text(
            label: 'Adresse email',
            placeholder: 'ex: jean.dupont@exemple.com',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'L\'adresse email n\'est pas valide.',
                User::where('email', $value)->exists() => 'Cette adresse email est déjà utilisée.',
                default => null
            }
        );

        $pass = password(
            label: 'Mot de passe',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 8 => 'Le mot de passe doit contenir au moins 8 caractères.',
                default => null
            }
        );

        $roles = Role::pluck('name')->toArray();

        $role = null;
        if (! empty($roles)) {
            $role = select(
                label: 'Attribuer un rôle ?',
                options: array_merge(['Aucun'], $roles),
                default: 'Aucun'
            );
        } else {
            warning('Aucun rôle défini dans la base de données.');
        }

        if (confirm('Voulez-vous créer cet utilisateur ?', true)) {
            $user = User::create([
                'name' => $name,
                'last_name' => $lastName,
                'user_name' => $userName,
                'email' => $email,
                'password' => Hash::make($pass),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            if ($role && $role !== 'Aucun') {
                $user->assignRole($role);
                info("✓ Rôle '{$role}' assigné.");
            }

            info("✓ Utilisateur '{$userName}' créé avec succès !");
        } else {
            error('Opération annulée.');
        }

        return 0;
    }
}
