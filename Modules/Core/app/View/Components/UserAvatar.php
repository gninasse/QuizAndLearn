<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;

class UserAvatar extends Component
{
    public $user;

    public $size;

    public function __construct($user, $size = 'md')
    {
        $this->user = $user;
        $this->size = $size;
    }

    public function getSizeClass()
    {
        return match ($this->size) {
            'sm' => 'width: 30px; height: 30px; font-size: 12px;',
            'md' => 'width: 40px; height: 40px; font-size: 16px;',
            'lg' => 'width: 60px; height: 60px; font-size: 24px;',
            default => 'width: 40px; height: 40px; font-size: 16px;',
        };
    }

    public function render()
    {
        return <<<'blade'
<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
     style="{{ $this->getSizeClass() }}">
    {{ strtoupper(substr($user->name, 0, 1)) }}
</div>
blade;
    }
}
