<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(
        public bool $wide = false,
    ) {}

    /**
     * Get the view / contents that represents the layout.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
