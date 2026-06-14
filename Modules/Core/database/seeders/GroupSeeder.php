<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groupsData = [
            [
                'name' => 'Design UI/UX - Soirée',
                'description' => 'Formation intensive en design d\'interface utilisateur et expérience utilisateur, cours du soir.',
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-31',
                'trainers' => ['marie.dubois@example.com', 'thomas.leroy@example.com'],
                'learners' => ['a.lemaire@example.com', 'b.martin.design@workspace.net', 'camille.d@example.com'],
            ],
            [
                'name' => 'Développement Web Fullstack',
                'description' => 'Cursus complet de développement web, de l\'intégration HTML/CSS aux frameworks backend/frontend.',
                'start_date' => '2026-05-15',
                'end_date' => '2026-11-15',
                'trainers' => ['lucie.bernard@example.com', 'jean-marc.barre@example.com'],
                'learners' => ['j.durand@example.com', 'a.petit@example.com', 'c.roux@example.com', 'd.moreau@example.com', 'e.simon@example.com'],
            ],
            [
                'name' => 'Introduction à Laravel 12',
                'description' => 'Maîtriser les bases du framework Laravel version 12 : routage, Eloquent ORM, et Blade.',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
                'trainers' => ['jean-marc.barre@example.com'],
                'learners' => ['f.laurent@example.com', 'g.michel@example.com', 'h.garcia@example.com', 'i.martinez@example.com'],
            ],
            [
                'name' => 'DevOps & Cloud Computing',
                'description' => 'Déploiement continu, conteneurisation Docker, orchestration Kubernetes et cloud Azure/AWS.',
                'start_date' => '2026-06-15',
                'end_date' => '2026-09-15',
                'trainers' => ['nicolas.durand@example.com'],
                'learners' => ['j.thomas@example.com', 's.richard@example.com', 't.petit@example.com', 'z.bonnet@example.com'],
            ],
            [
                'name' => 'Gestion de Projet Agile',
                'description' => 'Méthodologies agiles Scrum et Kanban appliquées au management de projets numériques.',
                'start_date' => '2026-09-01',
                'end_date' => '2026-10-31',
                'trainers' => ['sophie.martin@example.com', 'thomas.leroy@example.com'],
                'learners' => ['p.henry@example.com', 'm.legrand@example.com', 'l.gautier@example.com', 'c.chevalier@example.com'],
            ],
        ];

        // Nettoyer les groupes existants pour repartir sur une base propre
        \Modules\Core\Models\Group::query()->delete();

        foreach ($groupsData as $data) {
            $group = \Modules\Core\Models\Group::updateOrCreate(
                ['name' => $data['name']],
                [
                    'description' => $data['description'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'is_active' => true,
                ]
            );

            // Find trainers IDs
            $trainerIds = [];
            foreach ($data['trainers'] as $email) {
                $user = \Modules\Core\Models\User::where('email', $email)->first();
                if ($user && $user->trainer) {
                    $trainerIds[] = $user->trainer->id;
                }
            }
            $group->trainers()->sync($trainerIds);

            // Find learners IDs
            $learnerIds = [];
            foreach ($data['learners'] as $email) {
                $user = \Modules\Core\Models\User::where('email', $email)->first();
                if ($user && $user->learner) {
                    $learnerIds[] = $user->learner->id;
                }
            }
            $group->learners()->sync($learnerIds);
        }
    }
}
