<?php

namespace App\Livewire\AI;

use Livewire\Component;

/**
 * Shared placeholder for the AI & Automation pages that are not built yet.
 * The route supplies the heading and blurb via defaults(), the same way
 * RoleUserManagement is reused across the Admins/PMs/Writers routes.
 */
class ComingSoon extends Component
{
    public string $feature = '';

    public string $blurb = '';

    public function mount(string $feature = '', string $blurb = ''): void
    {
        $this->feature = $feature;
        $this->blurb = $blurb;
    }

    public function render()
    {
        return view('livewire.ai.coming-soon')
            ->layout('layouts.app', ['pageTitle' => $this->feature]);
    }
}
