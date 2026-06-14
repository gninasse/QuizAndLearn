<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::findOrCreate('trainer');
        Role::findOrCreate('learner');

        $this->call([
            TrainerSeeder::class,
            LearnerSeeder::class,
            GroupSeeder::class,
        ]);
    }
}
