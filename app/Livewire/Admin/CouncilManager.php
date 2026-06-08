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

    // Edit form fields (kept separate from the model to avoid hydration issues)
    public string $editWebsiteUrl = '';
    public string $editDemocracyUrl = '';
    public string $editModernGovBaseUrl = '';
    public string $editUsesModernGov = '';
    public string $editDemocracyClubOrgId = '';
    public string $editUsesDemocracyClub = '';

    public ?Council $viewingCouncil = null;
    public bool $showViewModal = false;

    public string $councillorSortBy = 'name';
    public string $councillorSortDirection = 'asc';

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
        $council = Council::findOrFail($gssCode);
        $this->editingCouncil = $council;

        // Populate separate form properties so we never mutate the Eloquent model
        $this->editWebsiteUrl = $council->website_url ?? '';
        $this->editDemocracyUrl = $council->democracy_url ?? '';
        $this->editModernGovBaseUrl = $council->modern_gov_base_url ?? '';
        $this->editDemocracyClubOrgId = $council->democracy_club_org_id ?? '';

        $this->editUsesModernGov = match ($council->uses_modern_gov) {
            true => '1',
            false => '0',
            null => '',
            default => '',
        };
        $this->editUsesDemocracyClub = match ($council->uses_democracy_club) {
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

        $council = Council::findOrFail($this->editingCouncil->gss_code);

        $council->update([
            'website_url' => $this->editWebsiteUrl ?: null,
            'democracy_url' => $this->editDemocracyUrl ?: null,
            'modern_gov_base_url' => $this->editModernGovBaseUrl ?: null,
            'democracy_club_org_id' => $this->editDemocracyClubOrgId ?: null,
            'uses_modern_gov' => match ($this->editUsesModernGov) {
                '1' => true,
                '0' => false,
                default => null,
            },
            'uses_democracy_club' => match ($this->editUsesDemocracyClub) {
                '1' => true,
                '0' => false,
                default => null,
            },
        ]);

        $this->showEditModal = false;
        $this->dispatch('toast', message: $council->name . ' updated.');
    }

    public function cancelEdit(): void
    {
        $this->showEditModal = false;
        $this->editingCouncil = null;
        $this->editWebsiteUrl = '';
        $this->editDemocracyUrl = '';
        $this->editModernGovBaseUrl = '';
        $this->editUsesModernGov = '';
        $this->editDemocracyClubOrgId = '';
        $this->editUsesDemocracyClub = '';
    }

    public function viewCouncil(string $gssCode): void
    {
        $this->viewingCouncil = Council::with('councillors')->findOrFail($gssCode);
        $this->councillorSortBy = 'name';
        $this->councillorSortDirection = 'asc';
        $this->showViewModal = true;
    }

    public function closeView(): void
    {
        $this->showViewModal = false;
        $this->viewingCouncil = null;
        $this->councillorSortBy = 'name';
        $this->councillorSortDirection = 'asc';
    }

    public function sortCouncillorsBy(string $field): void
    {
        if ($this->councillorSortBy === $field) {
            $this->councillorSortDirection = $this->councillorSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->councillorSortBy = $field;
            $this->councillorSortDirection = 'asc';
        }
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

    public function syncModernGovCouncillors(string $gssCode): void
    {
        $council = Council::findOrFail($gssCode);

        if (! $council->uses_modern_gov || ! $council->modern_gov_base_url) {
            $this->dispatch('toast', message: $council->name . ' is not connected to ModernGov.');

            return;
        }

        \App\Jobs\ImportCouncillorJob::dispatch($gssCode);

        $this->dispatch('toast', message: $council->name . ' — Councillor import queued. Refresh in a moment to see updated counts.');
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
