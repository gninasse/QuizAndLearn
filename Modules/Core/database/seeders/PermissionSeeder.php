<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // User Management
            ['name' => 'view users', 'label' => 'Voir les utilisateurs'],
            ['name' => 'create users', 'label' => 'Créer des utilisateurs'],
            ['name' => 'edit users', 'label' => 'Modifier des utilisateurs'],
            ['name' => 'delete users', 'label' => 'Supprimer des utilisateurs'],

            // Role Management
            ['name' => 'view roles', 'label' => 'Voir les rôles'],
            ['name' => 'create roles', 'label' => 'Créer des rôles'],
            ['name' => 'edit roles', 'label' => 'Modifier des rôles'],
            ['name' => 'delete roles', 'label' => 'Supprimer des rôles'],

            // Permission Management
            ['name' => 'manage permissions', 'label' => 'Gérer les permissions'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['label' => $permission['label'], 'guard_name' => 'web']
            );
        }
    }
}
