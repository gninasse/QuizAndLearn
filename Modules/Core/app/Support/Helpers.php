<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Core\Models\Module;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Helpers et fonctions utilitaires pour le module Core
 *
 * Fichier: Modules/Core/app/Support/Helpers.php
 */
if (! function_exists('core_user')) {
    /**
     * Obtenir l'utilisateur actuellement connecté
     */
    function core_user(): ?User
    {
        return auth()->user();
    }
}

if (! function_exists('has_core_access')) {
    /**
     * Vérifier si l'utilisateur a accès au module Core
     */
    function has_core_access(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->hasModuleAccess('core');
    }
}

if (! function_exists('has_module_access')) {
    /**
     * Vérifier si l'utilisateur a accès à un module
     */
    function has_module_access(string $module): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->hasModuleAccess($module);
    }
}

if (! function_exists('active_modules')) {
    /**
     * Obtenir tous les modules actifs
     */
    function active_modules(): Collection
    {
        return Module::active()->orderBy('sort_order')->get();
    }
}

if (! function_exists('get_module')) {
    /**
     * Obtenir un module par son slug
     */
    function get_module(string $slug): ?Module
    {
        return Module::where('slug', $slug)->first();
    }
}

if (! function_exists('is_module_active')) {
    /**
     * Vérifier si un module est actif
     */
    function is_module_active(string $slug): bool
    {
        $module = get_module($slug);

        return $module && $module->is_active;
    }
}

if (! function_exists('get_user_modules')) {
    /**
     * Obtenir les modules accessibles par l'utilisateur
     */
    function get_user_modules(?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return collect();
        }

        return $user->getAccessibleModules();
    }
}

if (! function_exists('permission_exists')) {
    /**
     * Vérifier si une permission existe
     */
    function permission_exists(string $name): bool
    {
        return Permission::where('name', $name)->exists();
    }
}

if (! function_exists('role_exists')) {
    /**
     * Vérifier si un rôle existe
     */
    function role_exists(string $name): bool
    {
        return Role::where('name', $name)->exists();
    }
}

if (! function_exists('get_permissions_by_module')) {
    /**
     * Obtenir les permissions groupées par module
     */
    function get_permissions_by_module(): Collection
    {
        return Permission::all()->groupBy('module');
    }
}

if (! function_exists('get_module_permissions')) {
    /**
     * Obtenir les permissions d'un module spécifique
     */
    function get_module_permissions(string $module): Collection
    {
        return Permission::where('module', $module)->get();
    }
}

if (! function_exists('is_super_admin')) {
    /**
     * Vérifier si l'utilisateur est super-admin
     */
    function is_super_admin(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super-admin');
    }
}

if (! function_exists('is_impersonating')) {
    /**
     * Vérifier si on est en mode impersonnification
     */
    function is_impersonating(): bool
    {
        return session()->has('impersonate');
    }
}

if (! function_exists('original_user_id')) {
    /**
     * Obtenir l'ID de l'utilisateur original en mode impersonnification
     */
    function original_user_id(): ?int
    {
        return session()->get('impersonate');
    }
}

if (! function_exists('format_permission_name')) {
    /**
     * Formater un nom de permission (module.resource.action)
     */
    function format_permission_name(string $module, string $resource, string $action): string
    {
        return strtolower("{$module}.{$resource}.{$action}");
    }
}

if (! function_exists('parse_permission_name')) {
    /**
     * Parser un nom de permission en ses composants
     */
    function parse_permission_name(string $permission): array
    {
        $parts = explode('.', $permission);

        return [
            'module' => $parts[0] ?? null,
            'resource' => $parts[1] ?? null,
            'action' => $parts[2] ?? null,
        ];
    }
}

if (! function_exists('core_stats')) {
    /**
     * Obtenir les statistiques du module Core
     */
    function core_stats(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'active' => User::whereNotNull('email_verified_at')->count(),
            ],
            'roles' => [
                'total' => Role::count(),
            ],
            'permissions' => [
                'total' => Permission::count(),
                'by_module' => Permission::selectRaw('module, COUNT(*) as count')
                    ->whereNotNull('module')
                    ->groupBy('module')
                    ->pluck('count', 'module')
                    ->toArray(),
            ],
            'modules' => [
                'total' => Module::count(),
                'active' => Module::active()->count(),
            ],
        ];
    }
}

if (! function_exists('can_manage_users')) {
    /**
     * Vérifier si l'utilisateur peut gérer les utilisateurs
     */
    function can_manage_users(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->can('cores.users.view')
            || auth()->user()->can('cores.users.create')
            || auth()->user()->can('cores.users.edit');
    }
}

if (! function_exists('can_manage_roles')) {
    /**
     * Vérifier si l'utilisateur peut gérer les rôles
     */
    function can_manage_roles(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->can('cores.roles.view')
            || auth()->user()->can('cores.roles.create')
            || auth()->user()->can('cores.roles.edit');
    }
}

if (! function_exists('can_manage_modules')) {
    /**
     * Vérifier si l'utilisateur peut gérer les modules
     */
    function can_manage_modules(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->can('cores.modules.view')
            || auth()->user()->can('cores.modules.enable');
    }
}

if (! function_exists('get_role_badge_color')) {
    /**
     * Obtenir la couleur du badge pour un rôle
     */
    function get_role_badge_color(string $roleName): string
    {
        return match ($roleName) {
            'super-admin' => 'danger',
            'admin' => 'warning',
            'manager' => 'info',
            'user' => 'secondary',
            default => 'primary',
        };
    }
}

if (! function_exists('get_permission_category_icon')) {
    /**
     * Obtenir l'icône pour une catégorie de permission
     */
    function get_permission_category_icon(string $category): string
    {
        return match ($category) {
            'view' => 'fa-eye',
            'create' => 'fa-plus',
            'edit' => 'fa-edit',
            'delete' => 'fa-trash',
            'manage' => 'fa-cog',
            default => 'fa-shield-alt',
        };
    }
}

if (! function_exists('generate_crud_permissions')) {
    /**
     * Générer les permissions CRUD standard pour un module
     */
    function generate_crud_permissions(string $module, string $resource): array
    {
        return [
            format_permission_name($module, $resource, 'view'),
            format_permission_name($module, $resource, 'create'),
            format_permission_name($module, $resource, 'edit'),
            format_permission_name($module, $resource, 'delete'),
        ];
    }
}

if (! function_exists('sync_user_modules')) {
    /**
     * Synchroniser les modules d'un utilisateur avec ses permissions
     */
    function sync_user_modules(User $user): void
    {
        $modules = $user->getAccessibleModules();

        // Vous pouvez stocker cela en cache ou ailleurs si nécessaire
        cache()->put("user.{$user->id}.modules", $modules, now()->addDay());
    }
}

if (! function_exists('clear_permissions_cache')) {
    /**
     * Vider le cache des permissions
     */
    function clear_permissions_cache(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        cache()->tags(['permissions'])->flush();
    }
}

if (! function_exists('module_navigation')) {
    /**
     * Générer la navigation pour un module
     */
    function module_navigation(string $module): array
    {
        $config = config("modules.{$module}.navigation", []);

        return collect($config)
            ->filter(fn ($item) => auth()->check() && auth()->user()->can($item['permission'] ?? ''))
            ->toArray();
    }
}

if (! function_exists('breadcrumb')) {
    /**
     * Générer un fil d'Ariane
     */
    function breadcrumb(array $items): string
    {
        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';

        foreach ($items as $label => $url) {
            if ($url) {
                $html .= "<li class='breadcrumb-item'><a href='{$url}'>{$label}</a></li>";
            } else {
                $html .= "<li class='breadcrumb-item active'>{$label}</li>";
            }
        }

        $html .= '</ol></nav>';

        return $html;
    }
}

if (! function_exists('permission_description')) {
    /**
     * Obtenir la description d'une permission
     */
    function permission_description(string $permissionName): ?string
    {
        $permission = Permission::where('name', $permissionName)->first();

        return $permission?->description;
    }
}

if (! function_exists('user_has_any_permission')) {
    /**
     * Vérifier si l'utilisateur a au moins une des permissions
     */
    function user_has_any_permission(array $permissions, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('user_has_all_permissions')) {
    /**
     * Vérifier si l'utilisateur a toutes les permissions
     */
    function user_has_all_permissions(array $permissions, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
