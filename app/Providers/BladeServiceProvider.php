<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Directive pour vérifier l'accès à un module
        Blade::if('hasModuleAccess', function ($module) {
            return auth()->check() && auth()->user()->hasModuleAccess($module);
        });

        // Directive pour vérifier une permission modulaire
        Blade::directive('canModule', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->can({$expression})): ?>";
        });

        Blade::directive('endcanModule', function () {
            return '<?php endif; ?>';
        });

        // Directive pour vérifier plusieurs permissions d'un module
        Blade::directive('hasAnyModulePermission', function ($expression) {
            return "<?php if(auth()->check() && collect({$expression})->some(fn(\$p) => auth()->user()->can(\$p))): ?>";
        });

        Blade::directive('endhasAnyModulePermission', function () {
            return '<?php endif; ?>';
        });

        // Directive pour afficher un élément de menu de module
        Blade::directive('moduleMenu', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->hasModuleAccess({$expression})): ?>
                <?php echo view('partials.module-menu-item', ['module' => {$expression}]); ?>
            <?php endif; ?>";
        });
    }
}
