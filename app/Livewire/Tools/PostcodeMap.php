<?php

namespace App\Livewire\Tools;

use App\Exceptions\PostcodeNotFoundException;
use App\Services\PostcodeLookupService;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Postcode Map Demo')]
class PostcodeMap extends Component
{
    public $postcode = '';
    public $result = null;
    public $error = null;
    public $mapView = 'markers'; // 'markers' or 'route'

    public function lookup(PostcodeLookupService $service)
    {
        $this->validate([
            'postcode' => 'required|string|min:5|max:8',
        ]);

        $this->error = null;
        $this->result = null;

        try {
            // Always include UPRNs for mapping
            $this->result = $service->lookup($this->postcode, true);

            // Dispatch event to JavaScript to update map
            $this->dispatch('updateMap',
                uprns: $this->result['uprns'],
                mapView: $this->mapView,
                postcode: $this->result['postcode']
            );
        } catch (PostcodeNotFoundException $e) {
            $this->error = "Postcode '{$this->postcode}' not found in database";
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        } catch (\Exception $e) {
            $this->error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }

    public function updatedMapView()
    {
        // When map view changes, update the map if we have results
        if ($this->result) {
            $this->dispatch('updateMap',
                uprns: $this->result['uprns'],
                mapView: $this->mapView,
                postcode: $this->result['postcode']
            );
        }
    }

    public function clear()
    {
        $this->reset(['postcode', 'result', 'error']);
        $this->dispatch('clearMap');
    }

    public function render()
    {
        return view('livewire.tools.postcode-map');
    }
}
