<?php

namespace App\Livewire\Admin;

use App\Models\DataVersion;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Versions')]
class DataVersionTable extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public string $sortBy = 'epoch';
    public string $sortDirection = 'desc';

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function sortByField($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function archiveVersion($id)
    {
        $version = DataVersion::findOrFail($id);
        $version->update(['status' => 'archived']);
        $this->dispatch('version-archived');
    }

    public function deleteVersion($id)
    {
        DataVersion::findOrFail($id)->delete();
        $this->dispatch('version-deleted');
    }

    public function render()
    {
        $query = DataVersion::where('dataset', 'ONSUD');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $versions = $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);

        return view('livewire.admin.data-version-table', [
            'versions' => $versions,
        ]);
    }
}
