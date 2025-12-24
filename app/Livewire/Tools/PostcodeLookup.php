<?php

namespace App\Livewire\Tools;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Postcode Lookup')]
class PostcodeLookup extends Component
{
    public $postcode = '';

    public function lookup()
    {
        $this->validate([
            'postcode' => 'required|string|min:5|max:8',
        ]);

        // Placeholder - API not yet built
        $this->dispatch('api-not-ready');
    }

    public function render()
    {
        return view('livewire.tools.postcode-lookup');
    }
}
