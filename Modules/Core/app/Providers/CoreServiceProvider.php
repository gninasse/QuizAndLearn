<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Core';

    protected string $moduleNameLower = 'core';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
        $this->registerBladeDirectives();
        $this->registerGates();

        $this->commands([
            \Modules\Core\Console\Commands\MakeSuperAdminCommand::class,
            \Modules\Core\Console\Commands\AssignRoleCommand::class,
            \Modules\Core\Console\Commands\CleanupPermissionsCommand::class,
            \Modules\Core\Console\Commands\ModuleStatsCommand::class,
            \Modules\Core\Console\Commands\SyncModulesCommand::class,
            \Modules\Core\Console\Commands\SyncPermissionsCommand::class,
            \Modules\Core\Console\Commands\SyncCommand::class,
            \Modules\Core\Console\Commands\UserPermissionsCommand::class,
            \Modules\Core\Console\Commands\CleanupExpiredActivitiesCommand::class,
            \Modules\Core\Console\Commands\ResetUserPassword::class,
            \Modules\Core\Console\Commands\CreateUserCommand::class,
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Enregistrer les services
        $this->app->singleton(\Modules\Core\Services\ModuleService::class);
        $this->app->singleton(\Modules\Core\Services\PermissionService::class);
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'config/config.php'),
            $this->moduleNameLower
        );
    }

    /**
     * Register views.
     */
    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     */
    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Register custom Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // Directive pour vérifier l'accès au module Core
        Blade::if('hasCoreAccess', function () {
            return auth()->check() && auth()->user()->hasModuleAccess('core');
        });

        // Directive pour afficher les alertes
        Blade::directive('coreAlert', function () {
            return "<?php if(session()->has('success')): ?>
                <div class='alert alert-success'>{{ session('success') }}</div>
            <?php endif; ?>
            <?php if(session()->has('error')): ?>
                <div class='alert alert-danger'>{{ session('error') }}</div>
            <?php endif; ?>
            <?php if(session()->has('info')): ?>
                <div class='alert alert-info'>{{ session('info') }}</div>
            <?php endif; ?>";
        });

        // Directive pour vérifier si on est en mode impersonnification
        Blade::if('impersonating', function () {
            return session()->has('impersonate');
        });
    }

    /**
     * Register custom gates.
     */
    protected function registerGates(): void
    {
        // Super-admin a accès à tout
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });

        // Empêcher la modification des rôles système
        Gate::define('edit-system-role', function ($user, $role) {
            if (in_array($role->name, ['super-admin'])) {
                return $user->hasRole('super-admin');
            }

            return true;
        });

        // Empêcher la suppression de son propre compte
        Gate::define('delete-user', function ($user, $targetUser) {
            return $user->id !== $targetUser->id;
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            \Modules\Core\Services\ModuleService::class,
            \Modules\Core\Services\PermissionService::class,
        ];
    }

    /**
     * Get publishable view paths.
     */
    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
