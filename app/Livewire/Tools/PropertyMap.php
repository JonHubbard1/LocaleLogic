<?php

namespace App\Livewire\Tools;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Property Map')]
class PropertyMap extends Component
{
    public $postcode = '';
    public $mapReady = false;

    public function loadMap()
    {
        $this->validate([
            'postcode' => 'required|string|min:5|max:8',
        ]);

        // Placeholder - API and map integration not yet built
        $this->dispatch('api-not-ready');
    }

    public function render()
    {
        return view('livewire.tools.property-map');
    }
}
