<?php

namespace App\Livewire\Admin;

use App\Models\GeographyVersion;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Geography Versions')]
class GeographyVersionTable extends Component
{
    use WithPagination;

    public string $typeFilter = 'all';
    public string $statusFilter = 'all';
    public string $sortBy = 'imported_at';
    public string $sortDirection = 'desc';

    public function updatedTypeFilter()
    {
        $this->resetPage();
    }

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
        $version = GeographyVersion::findOrFail($id);
        $version->update(['status' => 'archived']);
        $this->dispatch('version-archived');
    }

    public function deleteVersion($id)
    {
        GeographyVersion::findOrFail($id)->delete();
        $this->dispatch('version-deleted');
    }

    public function render()
    {
        $query = GeographyVersion::query();

        if ($this->typeFilter !== 'all') {
            $query->where('geography_type', $this->typeFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $versions = $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);

        return view('livewire.admin.geography-version-table', [
            'versions' => $versions,
        ]);
    }
}
