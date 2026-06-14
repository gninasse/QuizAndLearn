<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;

class LearnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyer les apprenants existants pour éviter les conflits de contraintes uniques
        $existing = \Modules\Core\Models\User::role('learner')->get();
        foreach ($existing as $u) {
            $u->delete();
        }

        $learnersData = [
            ['name' => 'Alice', 'last_name' => 'Lemaire', 'user_name' => 'alemaire', 'email' => 'a.lemaire@example.com', 'matricule' => 'APP-2026-001'],
            ['name' => 'Benoît', 'last_name' => 'Martin', 'user_name' => 'bmartin', 'email' => 'b.martin.design@workspace.net', 'matricule' => 'APP-2026-002'],
            ['name' => 'Camille', 'last_name' => 'Dupont', 'user_name' => 'cdupont', 'email' => 'camille.d@example.com', 'matricule' => 'APP-2026-003'],
            ['name' => 'Jean', 'last_name' => 'Durand', 'user_name' => 'jdurand', 'email' => 'j.durand@example.com', 'matricule' => 'APP-2026-004'],
            ['name' => 'Antoine', 'last_name' => 'Petit', 'user_name' => 'apetit', 'email' => 'a.petit@example.com', 'matricule' => 'APP-2026-005'],
            ['name' => 'Chloé', 'last_name' => 'Roux', 'user_name' => 'croux', 'email' => 'c.roux@example.com', 'matricule' => 'APP-2026-006'],
            ['name' => 'David', 'last_name' => 'Moreau', 'user_name' => 'dmoreau', 'email' => 'd.moreau@example.com', 'matricule' => 'APP-2026-007'],
            ['name' => 'Emma', 'last_name' => 'Simon', 'user_name' => 'esimon', 'email' => 'e.simon@example.com', 'matricule' => 'APP-2026-008'],
            ['name' => 'François', 'last_name' => 'Laurent', 'user_name' => 'flaurent', 'email' => 'f.laurent@example.com', 'matricule' => 'APP-2026-009'],
            ['name' => 'Gabrielle', 'last_name' => 'Michel', 'user_name' => 'gmichel', 'email' => 'g.michel@example.com', 'matricule' => 'APP-2026-010'],
            ['name' => 'Hugo', 'last_name' => 'Garcia', 'user_name' => 'hgarcia', 'email' => 'h.garcia@example.com', 'matricule' => 'APP-2026-011'],
            ['name' => 'Inès', 'last_name' => 'Martinez', 'user_name' => 'imartinez', 'email' => 'i.martinez@example.com', 'matricule' => 'APP-2026-012'],
            ['name' => 'Julien', 'last_name' => 'Thomas', 'user_name' => 'jthomas', 'email' => 'j.thomas@example.com', 'matricule' => 'APP-2026-013'],
            ['name' => 'Sarah', 'last_name' => 'Richard', 'user_name' => 'srichard', 'email' => 's.richard@example.com', 'matricule' => 'APP-2026-014'],
            ['name' => 'Thomas', 'last_name' => 'Petit', 'user_name' => 'tpetit', 'email' => 't.petit@example.com', 'matricule' => 'APP-2026-015'],
            ['name' => 'Zoé', 'last_name' => 'Bonnet', 'user_name' => 'zbonnet', 'email' => 'z.bonnet@example.com', 'matricule' => 'APP-2026-016'],
            ['name' => 'Paul', 'last_name' => 'Henry', 'user_name' => 'phenry', 'email' => 'p.henry@example.com', 'matricule' => 'APP-2026-017'],
            ['name' => 'Manon', 'last_name' => 'Legrand', 'user_name' => 'mlegrand', 'email' => 'm.legrand@example.com', 'matricule' => 'APP-2026-018'],
            ['name' => 'Lucas', 'last_name' => 'Gautier', 'user_name' => 'lgautier', 'email' => 'l.gautier@example.com', 'matricule' => 'APP-2026-019'],
            ['name' => 'Clara', 'last_name' => 'Chevalier', 'user_name' => 'cchevalier', 'email' => 'c.chevalier@example.com', 'matricule' => 'APP-2026-020'],
        ];

        foreach ($learnersData as $data) {
            $user = \Modules\Core\Models\User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'user_name' => $data['user_name'],
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'is_active' => true,
                ]
            );

            $user->assignRole('learner');

            \Modules\Core\Models\Learner::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'matricule' => $data['matricule'],
                ]
            );
        }
    }
}
