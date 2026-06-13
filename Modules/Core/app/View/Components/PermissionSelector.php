<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;

class PermissionSelector extends Component
{
    public $permissions;

    public $selected;

    public $name;

    public function __construct($permissions, $selected = [], $name = 'permissions')
    {
        $this->permissions = $permissions;
        $this->selected = $selected;
        $this->name = $name;
    }

    public function render()
    {
        return <<<'blade'
<div class="permission-selector">
    @foreach($permissions->groupBy('module') as $module => $modulePermissions)
        <div class="mb-4">
            <h6 class="border-bottom pb-2 mb-3">
                <i class="fas fa-cube me-2 text-primary"></i>
                {{ ucfirst($module ?? 'Général') }}
            </h6>
            <div class="row">
                @foreach($modulePermissions as $permission)
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="{{ $name }}[]" 
                                   value="{{ $permission->id }}" 
                                   id="perm-{{ $permission->id }}"
                                   {{ in_array($permission->id, $selected) ? 'checked' : '' }}>
                            <label class="form-check-label" for="perm-{{ $permission->id }}">
                                {{ $permission->name }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
blade;
    }
}
