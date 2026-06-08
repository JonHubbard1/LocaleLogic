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

    public bool $showJobsModal = false;
    public bool $showFailedJobsModal = false;

    /**
     * Get queue status for display.
     *
     * @return array{pending:int,failed:int,discoveryStatus:string,discoveryMessage:string}
     */
    public function getQueueStatus(): array
    {
        $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        $discovery = Cache::get('moderngov_discovery_status');

        return [
            'pending' => $pending,
            'failed' => $failed,
            'discovery_status' => $discovery['status'] ?? 'idle',
            'discovery_message' => match ($discovery['status'] ?? 'idle') {
                'running' => 'AI discovery in progress...',
                'completed' => 'AI discovery completed. ' . ($discovery['summary'] ?? ''),
                'failed' => 'AI discovery failed. ' . ($discovery['error'] ?? ''),
                default => '',
            },
            'discovery_time' => $discovery['started_at'] ?? $discovery['finished_at'] ?? null,
        ];
    }

    /**
     * Get pending jobs with decoded details.
     *
     * @return array<array{id:int,class:string,description:string,created_at:string}>
     */
    public function getPendingJobs(): array
    {
        return \Illuminate\Support\Facades\DB::table('jobs')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'queue', 'payload', 'created_at'])
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);

                return [
                    'id' => $job->id,
                    'class' => $payload['displayName'] ?? 'Unknown',
                    'description' => $this->extractJobDescription($payload),
                    'queue' => $job->queue,
                    'created_at' => $job->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get failed jobs with decoded details.
     *
     * @return array<array{id:int,class:string,exception:string,failed_at:string}>
     */
    public function getFailedJobs(): array
    {
        return \Illuminate\Support\Facades\DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(50)
            ->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);

                return [
                    'id' => $job->id,
                    'class' => $payload['displayName'] ?? 'Unknown',
                    'description' => $this->extractJobDescription($payload),
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                ];
            })
            ->toArray();
    }

    private function extractJobDescription(array $payload): string
    {
        $command = $payload['data']['command'] ?? null;
        if (! $command) {
            return '';
        }

        $decoded = @unserialize($command);
        if (! is_object($decoded)) {
            return '';
        }

        // Try to extract meaningful properties from common jobs
        $props = [];
        if (property_exists($decoded, 'councilGssCode')) {
            $props[] = 'GSS: ' . $decoded->councilGssCode;
        }
        if (property_exists($decoded, 'gssCode')) {
            $props[] = 'GSS: ' . $decoded->gssCode;
        }
        if (property_exists($decoded, 'nation')) {
            $props[] = 'Nation: ' . ($decoded->nation ?: 'All');
        }
        if (property_exists($decoded, 'region')) {
            $props[] = 'Region: ' . ($decoded->region ?: 'All');
        }

        return implode(' · ', $props);
    }

    public function openJobsModal(): void
    {
        $this->showJobsModal = true;
    }

    public function closeJobsModal(): void
    {
        $this->showJobsModal = false;
    }

    public function openFailedJobsModal(): void
    {
        $this->showFailedJobsModal = true;
    }

    public function closeFailedJobsModal(): void
    {
        $this->showFailedJobsModal = false;
    }

    public function retryFailedJobs(): void
    {
        \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => 'all']);
        $this->dispatch('toast', message: 'Failed jobs requeued.');
    }

    public function cancelJob(int $jobId): void
    {
        \Illuminate\Support\Facades\DB::table('jobs')
            ->where('id', $jobId)
            ->delete();

        $this->dispatch('toast', message: 'Job cancelled.');
    }

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

    public function aiDiscoverModernGov(?string $gssCode = null): void
    {
        if ($gssCode) {
            $council = Council::findOrFail($gssCode);
            \App\Jobs\DiscoverModernGovCouncilsJob::dispatch(
                nation: $council->nation,
                noCheck: false,
            );

            $this->dispatch('toast', message: $council->name . ' — AI ModernGov discovery queued for all ' . ucfirst($council->nation) . ' councils.');
        } else {
            \App\Jobs\DiscoverModernGovCouncilsJob::dispatch();

            $this->dispatch('toast', message: 'AI ModernGov discovery queued for all UK councils.');
        }
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
