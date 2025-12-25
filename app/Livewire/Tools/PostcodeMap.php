<?php

namespace App\Livewire\Tools;

use App\Exceptions\PostcodeNotFoundException;
use App\Models\UprnCoordinateOverride;
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

    // User's saved coordinate offset
    public $offsetLat = 0;
    public $offsetLng = 0;

    public function mount()
    {
        // Load user's saved coordinate offset
        $user = auth()->user();
        $this->offsetLat = $user->coordinate_offset_lat ?? 0;
        $this->offsetLng = $user->coordinate_offset_lng ?? 0;
    }

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

            // Load any existing UPRN-specific overrides for this user
            $overrides = UprnCoordinateOverride::where('user_id', auth()->id())
                ->whereIn('uprn', collect($this->result['uprns'])->pluck('uprn'))
                ->get()
                ->keyBy('uprn');

            // Dispatch event to JavaScript to update map with user's offset and overrides
            $this->dispatch('updateMap',
                uprns: $this->result['uprns'],
                mapView: $this->mapView,
                postcode: $this->result['postcode'],
                offsetLat: $this->offsetLat,
                offsetLng: $this->offsetLng,
                overrides: $overrides->map(fn($o) => [
                    'uprn' => $o->uprn,
                    'lat' => (float) $o->override_lat,
                    'lng' => (float) $o->override_lng,
                ])->values()->toArray()
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
            // Reload overrides
            $overrides = UprnCoordinateOverride::where('user_id', auth()->id())
                ->whereIn('uprn', collect($this->result['uprns'])->pluck('uprn'))
                ->get()
                ->keyBy('uprn');

            $this->dispatch('updateMap',
                uprns: $this->result['uprns'],
                mapView: $this->mapView,
                postcode: $this->result['postcode'],
                offsetLat: $this->offsetLat,
                offsetLng: $this->offsetLng,
                overrides: $overrides->map(fn($o) => [
                    'uprn' => $o->uprn,
                    'lat' => (float) $o->override_lat,
                    'lng' => (float) $o->override_lng,
                ])->values()->toArray()
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
