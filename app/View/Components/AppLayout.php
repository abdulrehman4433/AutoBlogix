<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Create the layout component.
     *
     * @param  string|null  $title  Page title shown in the top bar and browser tab.
     * @param  array<int, string|array{label: string, href?: string|null, icon?: string|null}>|null  $breadcrumb  Trail shown under the top bar.
     */
    public function __construct(
        public ?string $title = null,
        public ?array $breadcrumb = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
