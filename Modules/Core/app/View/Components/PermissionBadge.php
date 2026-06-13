<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;

class PermissionBadge extends Component
{
    public $permission;

    public $showCategory;

    public function __construct($permission, $showCategory = false)
    {
        $this->permission = $permission;
        $this->showCategory = $showCategory;
    }

    public function getBadgeColor()
    {
        return match ($this->permission->category) {
            'view' => 'bg-primary',
            'create' => 'bg-success',
            'edit' => 'bg-warning',
            'delete' => 'bg-danger',
            'manage' => 'bg-info',
            default => 'bg-secondary',
        };
    }

    public function getCategoryIcon()
    {
        return match ($this->permission->category) {
            'view' => 'fa-eye',
            'create' => 'fa-plus',
            'edit' => 'fa-edit',
            'delete' => 'fa-trash',
            'manage' => 'fa-cog',
            default => 'fa-shield-alt',
        };
    }

    public function render()
    {
        return <<<'blade'
<span class="badge {{ $this->getBadgeColor() }} d-inline-flex align-items-center">
    @if($showCategory)
        <i class="fas {{ $this->getCategoryIcon() }} me-1"></i>
    @endif
    {{ $permission->name }}
</span>
blade;
    }
}
