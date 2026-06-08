<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Council Manager</flux:heading>

        @php
            $queueStatus = $this->getQueueStatus();
        @endphp

        @if($queueStatus['pending'] > 0 || $queueStatus['failed'] > 0 || $queueStatus['discovery_status'] !== 'idle')
            <div wire:poll.5s class="mb-4 flex flex-wrap items-center gap-3">
                @if($queueStatus['discovery_status'] === 'running')
                    <flux:badge variant="warning" size="sm">
                        <flux:icon.arrow-path class="inline h-3 w-3 animate-spin" />
                        {{ $queueStatus['discovery_message'] }}
                    </flux:badge>
                @elseif($queueStatus['discovery_status'] === 'completed')
                    <flux:badge variant="success" size="sm">{{ $queueStatus['discovery_message'] }}</flux:badge>
                @elseif($queueStatus['discovery_status'] === 'failed')
                    <flux:badge variant="danger" size="sm">{{ $queueStatus['discovery_message'] }}</flux:badge>
                @endif

                @if($queueStatus['pending'] > 0)
                    <button wire:click="openJobsModal" class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-300 dark:hover:bg-blue-800">
                        {{ $queueStatus['pending'] }} job(s) queued
                        <flux:icon.eye class="inline h-3 w-3" />
                    </button>
                @endif

                @if($queueStatus['failed'] > 0)
                    <button wire:click="openFailedJobsModal" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 hover:bg-red-200 dark:bg-red-900 dark:text-red-300 dark:hover:bg-red-800">
                        {{ $queueStatus['failed'] }} failed job(s)
                        <flux:icon.eye class="inline h-3 w-3" />
                    </button>
                @endif
            </div>
        @endif

        <flux:card>
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading>UK Local Authorities</flux:heading>
                    <flux:subheading>Manage councils and ModernGov status</flux:subheading>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="primary" icon="sparkles" wire:click="aiDiscoverModernGov()">
                        AI Discover
                    </flux:button>
                    <flux:badge variant="info">{{ $councils->total() }} councils</flux:badge>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <flux:input wire:model.live="search" placeholder="Search by name or GSS code..." icon="magnifying-glass" />

                <flux:select wire:model.live="nationFilter">
                    @foreach($nationOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="typeFilter">
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="modernGovFilter">
                    @foreach($modernGovOptions as $value => $label)
                        <option value="{{ $value }}">ModernGov: {{ $label }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="democracyClubFilter">
                    @foreach($democracyClubOptions as $value => $label)
                        <option value="{{ $value }}">Democracy Club: {{ $label }}</option>
                    @endforeach
                </flux:select>
            </div>

            @if($councils->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th wire:click="sortByField('name')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Name
                                    @if($sortBy === 'name')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('gss_code')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    GSS Code
                                    @if($sortBy === 'gss_code')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('council_type')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Type
                                    @if($sortBy === 'council_type')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('nation')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Nation
                                    @if($sortBy === 'nation')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('uses_modern_gov')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    ModernGov
                                    @if($sortBy === 'uses_modern_gov')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('uses_democracy_club')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Dem. Club
                                    @if($sortBy === 'uses_democracy_club')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('councillor_count')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Councillors
                                    @if($sortBy === 'councillor_count')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($councils as $council)
                                <tr wire:key="council-{{ $council->gss_code }}" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $council->name }}
                                        @if($council->name_welsh)
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $council->name_welsh }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-gray-500 dark:text-gray-400">
                                        {{ $council->gss_code }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        <flux:badge size="sm" variant="secondary">{{ str_replace('_', ' ', $council->council_type) }}</flux:badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ ucfirst($council->nation) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @php
                                            $mgovVariant = match($council->uses_modern_gov) {
                                                true => 'success',
                                                false => 'danger',
                                                default => 'warning',
                                            };
                                            $mgovLabel = match($council->uses_modern_gov) {
                                                true => 'Yes',
                                                false => 'No',
                                                default => 'Unknown',
                                            };
                                        @endphp
                                        <div class="flex items-center gap-2">
                                            <flux:badge :variant="$mgovVariant" size="sm">{{ $mgovLabel }}</flux:badge>
                                            @if($council->uses_modern_gov && $council->modern_gov_base_url)
                                                <flux:button
                                                    size="xs"
                                                    variant="ghost"
                                                    wire:click="syncModernGovCouncillors('{{ $council->gss_code }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="syncModernGovCouncillors('{{ $council->gss_code }}')"
                                                    title="Sync councillors from ModernGov"
                                                >
                                                    <flux:icon.arrow-path class="h-3 w-3" wire:loading.class="animate-spin" wire:target="syncModernGovCouncillors('{{ $council->gss_code }}')" />
                                                </flux:button>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @php
                                            $dcVariant = match($council->uses_democracy_club) {
                                                true => 'success',
                                                false => 'danger',
                                                default => 'warning',
                                            };
                                            $dcLabel = match($council->uses_democracy_club) {
                                                true => 'Yes',
                                                false => 'No',
                                                default => 'Unknown',
                                            };
                                        @endphp
                                        <flux:badge :variant="$dcVariant" size="sm">{{ $dcLabel }}</flux:badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $council->councillor_count ?? 0 }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <flux:dropdown align="end">
                                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="editCouncil('{{ $council->gss_code }}')">
                                                    Edit
                                                </flux:menu.item>
                                                <flux:menu.item icon="users" wire:click="viewCouncil('{{ $council->gss_code }}')">
                                                    View Councillors
                                                </flux:menu.item>
                                                @if($council->modern_gov_base_url)
                                                    <flux:menu.item icon="globe-alt" href="{{ $council->modern_gov_base_url }}" target="_blank">
                                                        ModernGov Site
                                                    </flux:menu.item>
                                                @endif
                                                @if($council->democracy_url)
                                                    <flux:menu.item icon="globe-alt" href="{{ $council->democracy_url }}" target="_blank">
                                                        Democracy Site
                                                    </flux:menu.item>
                                                @endif

                                                <flux:menu.separator />

                                                <flux:menu.item icon="magnifying-glass" wire:click="searchModernGov('{{ $council->gss_code }}')">
                                                    Search for ModernGov
                                                </flux:menu.item>

                                                <flux:menu.item icon="magnifying-glass" wire:click="searchDemocracyClub('{{ $council->gss_code }}')">
                                                    Search for Democracy Club
                                                </flux:menu.item>

                                                <flux:menu.item icon="sparkles" wire:click="aiDiscoverModernGov('{{ $council->gss_code }}')">
                                                    AI Discover ModernGov
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $councils->links() }}
                </div>
            @else
                <div class="py-12 text-center">
                    <flux:icon.inbox class="mx-auto h-12 w-12 text-gray-400" />
                    <flux:heading size="lg" class="mt-2">No councils found</flux:heading>
                    <flux:subheading>Try adjusting your filters or run `php artisan councils:seed`</flux:subheading>
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-2xl">
        @if($editingCouncil)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Edit {{ $editingCouncil->name }}</flux:heading>
                    <flux:subheading>{{ $editingCouncil->gss_code }} · {{ str_replace('_', ' ', $editingCouncil->council_type) }}</flux:subheading>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Website URL</flux:label>
                        <flux:input wire:model="editWebsiteUrl" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Democracy URL</flux:label>
                        <flux:input wire:model="editDemocracyUrl" />
                    </flux:field>

                    <flux:field>
                        <flux:label>ModernGov Base URL</flux:label>
                        <flux:input wire:model="editModernGovBaseUrl" />
                    </flux:field>

                    <flux:field>
                        <flux:label>ModernGov Status</flux:label>
                        <flux:select wire:model="editUsesModernGov">
                            <option value="">Unknown</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Democracy Club Org ID</flux:label>
                        <flux:input wire:model="editDemocracyClubOrgId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Democracy Club Status</flux:label>
                        <flux:select wire:model="editUsesDemocracyClub">
                            <option value="">Unknown</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </flux:select>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="cancelEdit">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="saveCouncil">Save</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- View Councillors Modal --}}
    <flux:modal wire:model="showViewModal" class="max-w-5xl">
        @if($viewingCouncil)
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ $viewingCouncil->name }} — Councillors</flux:heading>
                        <flux:subheading>{{ $viewingCouncil->councillors->count() }} councillor(s) on record</flux:subheading>
                    </div>
                    <flux:button variant="ghost" wire:click="closeView">Close</flux:button>
                </div>

                @if($viewingCouncil->councillors->count() > 0)
                    @php
                        $partyCounts = $viewingCouncil->councillors
                            ->groupBy(fn($c) => $c->party ?: 'Independent')
                            ->map(fn($group) => $group->count())
                            ->sortDesc();
                    @endphp
                    <div class="flex flex-wrap gap-3">
                        @foreach($partyCounts as $party => $count)
                            <flux:card class="flex items-center gap-3 px-4 py-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 text-sm font-bold">
                                    {{ $count }}
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $party }}</span>
                            </flux:card>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th wire:click="sortCouncillorsBy('name')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Name
                                        @if($councillorSortBy === 'name')
                                            <flux:icon.chevron-{{ $councillorSortDirection === 'asc' ? 'up' : 'down' }} class="inline h-3 w-3" />
                                        @endif
                                    </th>
                                    <th wire:click="sortCouncillorsBy('party')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Party
                                        @if($councillorSortBy === 'party')
                                            <flux:icon.chevron-{{ $councillorSortDirection === 'asc' ? 'up' : 'down' }} class="inline h-3 w-3" />
                                        @endif
                                    </th>
                                    <th wire:click="sortCouncillorsBy('ward')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Ward
                                        @if($councillorSortBy === 'ward')
                                            <flux:icon.chevron-{{ $councillorSortDirection === 'asc' ? 'up' : 'down' }} class="inline h-3 w-3" />
                                        @endif
                                    </th>
                                    <th wire:click="sortCouncillorsBy('email')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Email
                                        @if($councillorSortBy === 'email')
                                            <flux:icon.chevron-{{ $councillorSortDirection === 'asc' ? 'up' : 'down' }} class="inline h-3 w-3" />
                                        @endif
                                    </th>
                                    <th wire:click="sortCouncillorsBy('phone')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Phone
                                        @if($councillorSortBy === 'phone')
                                            <flux:icon.chevron-{{ $councillorSortDirection === 'asc' ? 'up' : 'down' }} class="inline h-3 w-3" />
                                        @endif
                                    </th>
                                    <th wire:click="sortCouncillorsBy('source')" class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Source
                                        @if($councillorSortBy === 'source')
                                            <flux:icon.chevron-{{ $councillorSortDirection === 'asc' ? 'up' : 'down' }} class="inline h-3 w-3" />
                                        @endif
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                @php
                                    $sortedCouncillors = $viewingCouncil->councillors->sortBy(
                                        fn($c) => match($councillorSortBy) {
                                            'ward' => $c->ward_gss_code ?? '',
                                            default => $c->{$councillorSortBy} ?? '',
                                        },
                                        SORT_REGULAR,
                                        $councillorSortDirection === 'desc'
                                    );
                                @endphp
                                @foreach($sortedCouncillors as $councillor)
                                    <tr wire:key="councillor-{{ $councillor->id }}">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                            <div class="flex items-center gap-3">
                                                @if($councillor->photo_url)
                                                    <img src="{{ $councillor->photo_url }}" alt="" class="h-10 w-10 rounded-full object-cover" />
                                                @else
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700">
                                                        <flux:icon.user class="h-5 w-5 text-gray-500" />
                                                    </div>
                                                @endif
                                                <div>
                                                    {{ $councillor->name }}
                                                    @if($councillor->profile_url)
                                                        <a href="{{ $councillor->profile_url }}" target="_blank" class="ml-1 text-blue-600 hover:underline"><flux:icon.arrow-top-right-on-square class="inline h-3 w-3" /></a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $councillor->party ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $councillor->wardName() ?? $councillor->ward_gss_code }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            @if($councillor->email)
                                                <a href="mailto:{{ $councillor->email }}" class="text-blue-600 hover:underline">{{ $councillor->email }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $councillor->phone ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            <flux:badge size="sm" variant="{{ $councillor->source === 'democracy_club' ? 'success' : 'secondary' }}">
                                                {{ $councillor->source }}
                                            </flux:badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-8 text-center">
                        <flux:icon.inbox class="mx-auto h-10 w-10 text-gray-400" />
                        <flux:subheading class="mt-2">No councillors on record for this council yet.</flux:subheading>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>

    {{-- Pending Jobs Modal --}}
    <flux:modal wire:model="showJobsModal" class="max-w-4xl">
        <div wire:poll.3s class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Queue Jobs</flux:heading>
                <flux:button variant="ghost" size="sm" wire:click="closeJobsModal">Close</flux:button>
            </div>

            @php
                $pendingJobs = $this->getPendingJobs();
                $discoveryProgress = \Illuminate\Support\Facades\Cache::get('moderngov_discovery_progress');
            @endphp

            @if(count($pendingJobs) > 0)
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Job</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Details</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Progress</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Queued</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($pendingJobs as $job)
                                <@php
                                    $isDiscovery = str_contains($job['class'], 'DiscoverModernGovCouncilsJob');
                                    $progress = $isDiscovery ? $discoveryProgress : null;
                                @endphp
                                <tr wire:key="job-{{ $job['id'] }}">
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ class_basename($job['class']) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $job['description'] ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-sm">
                                        @if($isDiscovery && $progress && $progress['status'] === 'running')
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <flux:icon.arrow-path class="h-3 w-3 animate-spin text-blue-500" />
                                                    <span class="text-xs text-gray-600 dark:text-gray-300">
                                                        Batch {{ $progress['current_batch'] }}/{{ $progress['total_batches'] }}
                                                    </span>
                                                </div>
                                                <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div class="h-1.5 rounded-full bg-blue-500 transition-all" style="width: {{ ($progress['current_batch'] / max($progress['total_batches'], 1)) * 100 }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">
                                                    {{ $progress['total_discovered'] }} discovered · {{ $progress['total_updated'] }} updated
                                                </span>
                                            </div>
                                        @elseif($isDiscovery && $progress)
                                            <span class="text-xs text-gray-500">{{ $progress['status'] }}</span>
                                        @else
                                            <span class="text-xs text-gray-400">Waiting...</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($job['created_at'])->diffForHumans() }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <flux:button size="xs" variant="danger" wire:click="cancelJob({{ $job['id'] }})" title="Cancel job">
                                            <flux:icon.x-mark class="h-3 w-3" />
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon.inbox class="mx-auto h-10 w-10 text-gray-400" />
                    <flux:subheading class="mt-2">No pending jobs.</flux:subheading>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Failed Jobs Modal --}}
    <flux:modal wire:model="showFailedJobsModal" class="max-w-4xl">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Failed Jobs</flux:heading>
                <div class="flex items-center gap-2">
                    @if(count($this->getFailedJobs()) > 0)
                        <flux:button size="sm" variant="primary" wire:click="retryFailedJobs">Retry All</flux:button>
                    @endif
                    <flux:button variant="ghost" size="sm" wire:click="closeFailedJobsModal">Close</flux:button>
                </div>
            </div>

            @php
                $failedJobs = $this->getFailedJobs();
            @endphp

            @if(count($failedJobs) > 0)
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Job</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Details</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Error</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Failed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($failedJobs as $job)
                                <tr wire:key="failed-job-{{ $job['id'] }}">
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ class_basename($job['class']) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $job['description'] ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-red-600 dark:text-red-400 max-w-xs truncate">
                                        {{ \Illuminate\Support\Str::limit($job['exception'], 120) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon.inbox class="mx-auto h-10 w-10 text-gray-400" />
                    <flux:subheading class="mt-2">No failed jobs.</flux:subheading>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
