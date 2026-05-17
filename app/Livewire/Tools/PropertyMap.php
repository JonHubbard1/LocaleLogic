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
#[Title('Property Map')]
class PropertyMap extends Component
{
    public string $postcode = '';
    public ?array $result = null;
    public ?string $error = null;
    public string $mapView = 'markers';

    public ?float $offsetLat = 0;
    public ?float $offsetLng = 0;

    public function mount()
    {
        $user = auth()->user();
        $this->offsetLat = $user?->coordinate_offset_lat ?? 0;
        $this->offsetLng = $user?->coordinate_offset_lng ?? 0;
    }

    public function lookup(PostcodeLookupService $service): void
    {
        $this->validate([
            'postcode' => 'required|string|min:5|max:8',
        ]);

        $this->error = null;
        $this->result = null;

        try {
            $this->result = $service->lookup($this->postcode, true);

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
                ])->values()->toArray(),
                geography: $this->result['geography'] ?? null,
                coordinates: $this->result['coordinates'] ?? null,
            );
        } catch (PostcodeNotFoundException $e) {
            $this->error = "Postcode '{$this->postcode}' not found in database";
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        } catch (\Exception $e) {
            $this->error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }

    public function updatedMapView(): void
    {
        if ($this->result) {
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
                ])->values()->toArray(),
                geography: $this->result['geography'] ?? null,
                coordinates: $this->result['coordinates'] ?? null,
            );
        }
    }

    public function clear(): void
    {
        $this->reset(['postcode', 'result', 'error']);
        $this->dispatch('clearMap');
    }

    public function render()
    {
        return view('livewire.tools.property-map');
    }
}
