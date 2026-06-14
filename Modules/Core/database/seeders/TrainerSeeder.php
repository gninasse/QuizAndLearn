<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;

class TrainerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyer les formateurs existants pour éviter les conflits de contraintes uniques
        $existing = \Modules\Core\Models\User::role('trainer')->get();
        foreach ($existing as $u) {
            $u->delete();
        }

        $trainersData = [
            [
                'name' => 'Marie',
                'last_name' => 'Dubois',
                'user_name' => 'mdubois',
                'email' => 'marie.dubois@example.com',
                'phone' => '0612345678',
                'specialty' => 'Design UI/UX & Intégration',
                'biography' => 'Formatrice principale spécialisée en design d\'interface, ergonomie web et design system.',
            ],
            [
                'name' => 'Thomas',
                'last_name' => 'Leroy',
                'user_name' => 'tleroy',
                'email' => 'thomas.leroy@example.com',
                'phone' => '0623456789',
                'specialty' => 'Assistant Design & Web',
                'biography' => 'Assistant pédagogique, accompagne les apprenants sur les technologies front-end et maquettage.',
            ],
            [
                'name' => 'Jean-Marc',
                'last_name' => 'Barré',
                'user_name' => 'jmbarre',
                'email' => 'jean-marc.barre@example.com',
                'phone' => '0634567890',
                'specialty' => 'Développement Laravel & PHP',
                'biography' => 'Expert PHP/Laravel avec 10 ans d\'expérience en développement d\'applications complexes.',
            ],
            [
                'name' => 'Sophie',
                'last_name' => 'Martin',
                'user_name' => 'smartin',
                'email' => 'sophie.martin@example.com',
                'phone' => '0645678901',
                'specialty' => 'Gestion de Projet Agile',
                'biography' => 'Coach agile certifiée, accompagne les apprenants sur Scrum, Kanban et gestion de produit.',
            ],
            [
                'name' => 'Nicolas',
                'last_name' => 'Durand',
                'user_name' => 'ndurand',
                'email' => 'nicolas.durand@example.com',
                'phone' => '0656789012',
                'specialty' => 'DevOps & Cloud',
                'biography' => 'Ingénieur système expert en Docker, Kubernetes, CI/CD et administration Linux.',
            ],
            [
                'name' => 'Lucie',
                'last_name' => 'Bernard',
                'user_name' => 'lbernard',
                'email' => 'lucie.bernard@example.com',
                'phone' => '0667890123',
                'specialty' => 'JavaScript & React',
                'biography' => 'Développeuse Fullstack passionnée par l\'écosystème JS moderne et les SPA.',
            ],
        ];

        foreach ($trainersData as $data) {
            $user = \Modules\Core\Models\User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'user_name' => $data['user_name'],
                    'phone' => $data['phone'],
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'is_active' => true,
                ]
            );

            $user->assignRole('trainer');

            \Modules\Core\Models\Trainer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty' => $data['specialty'],
                    'biography' => $data['biography'],
                ]
            );
        }
    }
}
