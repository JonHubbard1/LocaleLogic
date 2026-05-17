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

    public string $statusFilter = 'all';
    public string $typeFilter = 'all';
    public string $sortBy = 'imported_at';
    public string $sortDirection = 'desc';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function sortByField(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        $query = GeographyVersion::query();

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('geography_type', $this->typeFilter);
        }

        $versions = $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);

        $types = GeographyVersion::query()
            ->select('geography_type')
            ->distinct()
            ->orderBy('geography_type')
            ->pluck('geography_type');

        return view('livewire.admin.geography-version-table', [
            'versions' => $versions,
            'types' => $types,
        ]);
    }
}
