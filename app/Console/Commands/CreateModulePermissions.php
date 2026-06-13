<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateModulePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module-permissions {module? : The name of the module}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a permissions configuration file for a module';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $module = $this->argument('module');

        if (! $module) {
            $module = $this->ask('For which module do you want to create the permissions file?');
        }

        if (! $module) {
            $this->error('Module name is required.');

            return;
        }

        // Normalize module name (e.g. core -> Core)
        $module = \Illuminate\Support\Str::studly($module);

        $directory = base_path("Modules/{$module}/config");
        $path = "{$directory}/permissions.php";

        if (file_exists($path)) {
            $this->error("File already exists: {$path}");

            return;
        }

        if (! is_dir($directory)) {
            \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
        }

        $content = <<<'PHP'
<?php

return [
    // Dashboard
   // 'cores.dashboard.view' => 'Accéder au tableau de bord Core', 
    
];
PHP;

        \Illuminate\Support\Facades\File::put($path, $content);

        $this->info("Permissions file created successfully at: {$path}");
    }
}
