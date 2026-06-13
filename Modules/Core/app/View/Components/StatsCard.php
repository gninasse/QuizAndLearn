<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;

class StatsCard extends Component
{
    public $title;

    public $value;

    public $icon;

    public $color;

    public $subtitle;

    public $link;

    public function __construct($title, $value, $icon, $color = 'primary', $subtitle = null, $link = null)
    {
        $this->title = $title;
        $this->value = $value;
        $this->icon = $icon;
        $this->color = $color;
        $this->subtitle = $subtitle;
        $this->link = $link;
    }

    public function render()
    {
        return <<<'blade'
<div class="card border-0 shadow-sm h-100">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6 class="text-muted mb-2">{{ $title }}</h6>
                <h3 class="mb-0">{{ $value }}</h3>
                @if($subtitle)
                    <small class="text-{{ $color }}">{{ $subtitle }}</small>
                @endif
            </div>
            <div class="bg-{{ $color }} bg-opacity-10 p-3 rounded">
                <i class="{{ $icon }} text-{{ $color }} fa-2x"></i>
            </div>
        </div>
        @if($link)
            <a href="{{ $link }}" class="btn btn-sm btn-link p-0 mt-2">
                Voir tous →
            </a>
        @endif
    </div>
</div>
blade;
    }
}
