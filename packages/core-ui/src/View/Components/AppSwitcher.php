<?php

namespace CoreUi\View\Components;

use App\Core\Spokes\SpokeRegistry;
use App\Models\Spoke;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class AppSwitcher extends Component
{
    /** @var Collection<int, Spoke> */
    public Collection $spokes;

    public ?Spoke $currentSpoke;

    public function __construct(SpokeRegistry $registry)
    {
        $tenant = app()->bound('current.tenant') ? app('current.tenant') : null;
        $currentSpoke = app()->bound('current.spoke') ? app('current.spoke') : null;

        $this->spokes = $tenant ? $registry->forTenant($tenant) : collect();
        $this->currentSpoke = $currentSpoke;
    }

    public function render(): View
    {
        return view('core-ui::app-switcher');
    }
}
