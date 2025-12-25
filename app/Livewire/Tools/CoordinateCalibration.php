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
#[Title('Coordinate Calibration')]
class CoordinateCalibration extends Component
{
    public $postcode = '';
    public $result = null;
    public $error = null;

    // Current offset being tested (in decimal degrees)
    public $offsetLat = 0;
    public $offsetLng = 0;

    // Meters per arrow key press (default 2.5m)
    public $stepSize = 2.5;

    public function mount()
    {
        // Load user's current saved offset
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

            // Dispatch event to JavaScript to update map with current offset and overrides
            $this->dispatch('updateMap',
                uprns: $this->result['uprns'],
                offsetLat: $this->offsetLat,
                offsetLng: $this->offsetLng,
                postcode: $this->result['postcode'],
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

    public function adjustOffset($direction)
    {
        // Convert meters to approximate degrees
        // At UK latitudes (~52°N): 1° lat ≈ 111,000m, 1° lng ≈ 70,000m
        $latDegreesPerMeter = 1 / 111000;
        $lngDegreesPerMeter = 1 / 70000;

        $latDelta = $this->stepSize * $latDegreesPerMeter;
        $lngDelta = $this->stepSize * $lngDegreesPerMeter;

        switch ($direction) {
            case 'up':
                $this->offsetLat += $latDelta;
                break;
            case 'down':
                $this->offsetLat -= $latDelta;
                break;
            case 'left':
                $this->offsetLng -= $lngDelta;
                break;
            case 'right':
                $this->offsetLng += $lngDelta;
                break;
        }

        // Round to 8 decimal places to avoid floating point errors
        $this->offsetLat = round($this->offsetLat, 8);
        $this->offsetLng = round($this->offsetLng, 8);

        // Update the map if we have results
        if ($this->result) {
            $this->dispatch('updateMap',
                uprns: $this->result['uprns'],
                offsetLat: $this->offsetLat,
                offsetLng: $this->offsetLng,
                postcode: $this->result['postcode']
            );
        }
    }

    public function resetOffset()
    {
        $this->offsetLat = 0;
        $this->offsetLng = 0;

        // Update the map if we have results
        if ($this->result) {
            $this->dispatch('updateMap',
                uprns: $this->result['uprns'],
                offsetLat: $this->offsetLat,
                offsetLng: $this->offsetLng,
                postcode: $this->result['postcode']
            );
        }
    }

    public function saveOffset()
    {
        $user = auth()->user();
        $user->coordinate_offset_lat = $this->offsetLat;
        $user->coordinate_offset_lng = $this->offsetLng;
        $user->save();

        $this->dispatch('offsetSaved');
    }

    public function saveUprnOverride($uprn, $lat, $lng)
    {
        UprnCoordinateOverride::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'uprn' => $uprn,
            ],
            [
                'override_lat' => $lat,
                'override_lng' => $lng,
            ]
        );

        $this->dispatch('uprnOverrideSaved', uprn: $uprn);
    }

    public function deleteUprnOverride($uprn)
    {
        UprnCoordinateOverride::where('user_id', auth()->id())
            ->where('uprn', $uprn)
            ->delete();

        $this->dispatch('uprnOverrideDeleted', uprn: $uprn);
    }

    public function clear()
    {
        $this->reset(['postcode', 'result', 'error']);
        $this->dispatch('clearMap');
    }

    public function render()
    {
        return view('livewire.tools.coordinate-calibration');
    }
}
