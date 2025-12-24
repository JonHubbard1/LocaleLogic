<?php

namespace App\Livewire\Tools;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Boundary Viewer')]
class BoundaryViewer extends Component
{
    public $geographyType = '';
    public $geographyCode = '';

    public $geographyTypes = [
        'wd25cd' => 'Ward 2025',
        'lad25cd' => 'Local Authority District 2025',
        'pcon25cd' => 'Parliamentary Constituency 2025',
        'par25cd' => 'Parish 2025',
        'eer25cd' => 'Electoral Region 2025',
        'ctry25cd' => 'Country 2025',
    ];

    public function loadBoundary()
    {
        $this->validate([
            'geographyType' => 'required|string',
            'geographyCode' => 'required|string|min:9|max:9',
        ]);

        // Placeholder - API not yet built
        $this->dispatch('api-not-ready');
    }

    public function render()
    {
        return view('livewire.tools.boundary-viewer');
    }
}
