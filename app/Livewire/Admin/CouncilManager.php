<?php

namespace App\Livewire\Admin;

use App\Models\Council;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Council Manager')]
class CouncilManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $nationFilter = 'all';
    public string $typeFilter = 'all';
    public string $modernGovFilter = 'all';
    public string $democracyClubFilter = 'all';
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    public ?Council $editingCouncil = null;
    public bool $showEditModal = false;

    public ?Council $viewingCouncil = null;
    public bool $showViewModal = false;

    public function mount(): void
    {
        $this->search = request()->query('search', '');
        $this->nationFilter = request()->query('nationFilter', 'all');
        $this->typeFilter = request()->query('typeFilter', 'all');
        $this->modernGovFilter = request()->query('modernGovFilter', 'all');
        $this->democracyClubFilter = request()->query('democracyClubFilter', 'all');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedNationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedModernGovFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDemocracyClubFilter(): void
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

    public function toggleModernGov(string $gssCode): void
    {
        $council = Council::findOrFail($gssCode);
        $council->update([
            'uses_modern_gov' => ! $council->uses_modern_gov,
        ]);

        $this->dispatch('toast', message: $council->name . ' ModernGov status updated.');
    }

    public function editCouncil(string $gssCode): void
    {
        $this->editingCouncil = Council::findOrFail($gssCode);

        // Cast booleans to strings so <select> options match
        $this->editingCouncil->uses_modern_gov = match ($this->editingCouncil->uses_modern_gov) {
            true => '1',
            false => '0',
            null => '',
            default => '',
        };
        $this->editingCouncil->uses_democracy_club = match ($this->editingCouncil->uses_democracy_club) {
            true => '1',
            false => '0',
            null => '',
            default => '',
        };

        $this->showEditModal = true;
    }

    public function saveCouncil(): void
    {
        if (! $this->editingCouncil) {
            return;
        }

        // Convert string values back to booleans before saving
        $this->editingCouncil->uses_modern_gov = match ($this->editingCouncil->uses_modern_gov) {
            '1' => true,
            '0' => false,
            default => null,
        };
        $this->editingCouncil->uses_democracy_club = match ($this->editingCouncil->uses_democracy_club) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $this->editingCouncil->save();
        $this->showEditModal = false;
        $this->dispatch('toast', message: $this->editingCouncil->name . ' updated.');
    }

    public function cancelEdit(): void
    {
        $this->showEditModal = false;
        $this->editingCouncil = null;
    }

    public function viewCouncil(string $gssCode): void
    {
        $this->viewingCouncil = Council::findOrFail($gssCode);
        $this->showViewModal = true;
    }

    public function closeView(): void
    {
        $this->showViewModal = false;
        $this->viewingCouncil = null;
    }

    public function searchModernGov(string $gssCode): void
    {
        $council = Council::findOrFail($gssCode);
        \App\Jobs\DiscoverCouncilSystemJob::dispatch($gssCode);

        $this->dispatch('toast', message: $council->name . ' — ModernGov discovery queued. Refresh in a moment to see results.');
    }

    public function searchDemocracyClub(string $gssCode): void
    {
        $council = Council::findOrFail($gssCode);
        \App\Jobs\DiscoverCouncilSystemJob::dispatch($gssCode);

        $this->dispatch('toast', message: $council->name . ' — Democracy Club discovery queued. Refresh in a moment to see results.');
    }

    public function getNationOptions(): array
    {
        return [
            'all' => 'All Nations',
            'england' => 'England',
            'scotland' => 'Scotland',
            'wales' => 'Wales',
            'northern_ireland' => 'Northern Ireland',
        ];
    }

    public function getTypeOptions(): array
    {
        return [
            'all' => 'All Types',
            'unitary' => 'Unitary',
            'district' => 'District',
            'metropolitan' => 'Metropolitan',
            'london_borough' => 'London Borough',
            'county' => 'County',
            'scottish' => 'Scottish',
            'welsh' => 'Welsh',
            'ni' => 'Northern Ireland',
        ];
    }

    public function getModernGovOptions(): array
    {
        return [
            'all' => 'All',
            'unknown' => 'Unknown',
            'yes' => 'Yes',
            'no' => 'No',
        ];
    }

    public function getDemocracyClubOptions(): array
    {
        return [
            'all' => 'All',
            'unknown' => 'Unknown',
            'yes' => 'Yes',
            'no' => 'No',
        ];
    }

    public function render()
    {
        $query = Council::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'ilike', '%' . $this->search . '%')
                    ->orWhere('gss_code', 'ilike', '%' . $this->search . '%');
            });
        }

        if ($this->nationFilter !== 'all') {
            $query->where('nation', $this->nationFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('council_type', $this->typeFilter);
        }

        if ($this->modernGovFilter !== 'all') {
            $query->where('uses_modern_gov', $this->modernGovFilter === 'yes' ? true : ($this->modernGovFilter === 'no' ? false : null));
        }

        if ($this->democracyClubFilter !== 'all') {
            $query->where('uses_democracy_club', $this->democracyClubFilter === 'yes' ? true : ($this->democracyClubFilter === 'no' ? false : null));
        }

        $query->select('councils.*');
        $query->selectRaw('(SELECT COUNT(*) FROM councillors WHERE councillors.council_gss_code = councils.gss_code) as councillor_count');

        if (in_array($this->sortBy, ['councillor_count', 'uses_modern_gov', 'uses_democracy_club'], true)) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } else {
            $query->orderBy('councils.' . $this->sortBy, $this->sortDirection);
        }

        $councils = $query->paginate(20);

        // Eager-load councillor counts
        $councillorCounts = Cache::remember('council_councillor_counts', 300, function () {
            return \App\Models\Councillor::query()
                ->selectRaw('council_gss_code, COUNT(*) as count')
                ->groupBy('council_gss_code')
                ->pluck('count', 'council_gss_code')
                ->toArray();
        });

        return view('livewire.admin.council-manager', [
            'councils' => $councils,
            'councillorCounts' => $councillorCounts,
            'nationOptions' => $this->getNationOptions(),
            'typeOptions' => $this->getTypeOptions(),
            'modernGovOptions' => $this->getModernGovOptions(),
            'democracyClubOptions' => $this->getDemocracyClubOptions(),
        ]);
    }
}
