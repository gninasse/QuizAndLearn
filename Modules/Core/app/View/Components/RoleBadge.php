<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;

class RoleBadge extends Component
{
    public $role;

    public $showCount;

    public function __construct($role, $showCount = false)
    {
        $this->role = $role;
        $this->showCount = $showCount;
    }

    public function getBadgeColor()
    {
        return match ($this->role->name) {
            'super-admin' => 'bg-danger',
            'admin' => 'bg-warning text-dark',
            'manager' => 'bg-info',
            default => 'bg-secondary',
        };
    }

    public function render()
    {
        return <<<'blade'
<span class="badge {{ $this->getBadgeColor() }}">
    {{ $role->name }}
    @if($showCount && $role->users_count)
        <span class="badge bg-white text-dark ms-1">{{ $role->users_count }}</span>
    @endif
</span>
blade;
    }
}
