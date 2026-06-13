<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;

class ModuleCard extends Component
{
    public $module;

    public $showActions;

    public function __construct($module, $showActions = true)
    {
        $this->module = $module;
        $this->showActions = $showActions;
    }

    public function render()
    {
        return <<<'blade'
<div class="card border-0 shadow-sm h-100 {{ !$module->is_active ? 'opacity-75' : '' }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="d-flex align-items-center">
                @if($module->icon)
                    <i class="{{ $module->icon }} fa-2x text-primary me-3"></i>
                @else
                    <i class="fas fa-cube fa-2x text-primary me-3"></i>
                @endif
                <div>
                    <h5 class="mb-0">{{ $module->name }}</h5>
                    <small class="text-muted">v{{ $module->version }}</small>
                </div>
            </div>
            @if($module->is_active)
                <span class="badge bg-success">Actif</span>
            @else
                <span class="badge bg-secondary">Inactif</span>
            @endif
        </div>

        <p class="text-muted small">{{ $module->description ?? 'Aucune description' }}</p>

        @if($module->is_required)
            <span class="badge bg-danger me-1">Requis</span>
        @endif

        @if($showActions)
            <div class="mt-3">
                <a href="{{ route('cores.modules.show', $module) }}" class="btn btn-sm btn-outline-primary">
                    Voir détails
                </a>
            </div>
        @endif
    </div>
</div>
blade;
    }
}
