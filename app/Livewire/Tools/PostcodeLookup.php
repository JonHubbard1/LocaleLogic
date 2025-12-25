<?php

namespace App\Livewire\Tools;

use App\Exceptions\PostcodeNotFoundException;
use App\Services\PostcodeLookupService;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Postcode Lookup')]
class PostcodeLookup extends Component
{
    public $postcode = '';
    public $result = null;
    public $error = null;
    public $includeUprns = false;

    public function lookup(PostcodeLookupService $service)
    {
        $this->validate([
            'postcode' => 'required|string|min:5|max:8',
        ]);

        $this->error = null;
        $this->result = null;

        try {
            $this->result = $service->lookup($this->postcode, $this->includeUprns);
        } catch (PostcodeNotFoundException $e) {
            $this->error = "Postcode '{$this->postcode}' not found in database";
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        } catch (\Exception $e) {
            $this->error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }

    public function clear()
    {
        $this->reset(['postcode', 'result', 'error', 'includeUprns']);
    }

    public function render()
    {
        return view('livewire.tools.postcode-lookup');
    }
}
