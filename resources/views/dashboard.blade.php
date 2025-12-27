<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <flux:heading size="xl" class="mb-6">Dashboard</flux:heading>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <flux:card>
                    <flux:heading>Welcome to LocaleLogic</flux:heading>
                    <flux:subheading>UK Geography Microservice</flux:subheading>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        Access powerful UK geography data including postcode lookups, property coordinates, and boundary visualization.
                    </p>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ number_format(\App\Models\Property::count()) }}</flux:heading>
                            <flux:subheading>Properties</flux:subheading>
                        </div>
                        <flux:icon.home class="size-12 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            @php
                                $currentVersion = \App\Models\DataVersion::where('dataset', 'ONSUD')
                                    ->where('status', 'current')
                                    ->first();
                            @endphp
                            <flux:heading size="lg">{{ $currentVersion ? 'Epoch ' . $currentVersion->epoch : 'N/A' }}</flux:heading>
                            <flux:subheading>Current ONSUD</flux:subheading>
                        </div>
                        <flux:icon.circle-stack class="size-12 text-green-500" />
                    </div>
                </flux:card>
            </div>

            @php
                // Check for outdated boundary files
                $updateSchedule = [
                    'wards' => ['month' => 'December', 'frequency' => 'annual'],
                    'parishes' => ['month' => 'April', 'frequency' => 'annual'],
                    'lad' => ['month' => 'April', 'frequency' => 'annual'],
                    'ced' => ['month' => 'May', 'frequency' => 'annual'],
                    'constituencies' => ['month' => 'July', 'frequency' => 'varies'],
                    'police_force_areas' => ['month' => 'December', 'frequency' => 'varies'],
                    'region' => ['month' => 'May', 'frequency' => 'annual'],
                    'counties' => ['month' => 'December', 'frequency' => 'annual'],
                ];

                $boundaryTypeLabels = [
                    'wards' => 'Electoral Ward Boundaries',
                    'parishes' => 'Parish Boundaries',
                    'lad' => 'Local Authority District Boundaries',
                    'ced' => 'County Electoral Division Boundaries',
                    'constituencies' => 'Westminster Parliamentary Constituencies',
                    'police_force_areas' => 'Police Force Area Boundaries',
                    'region' => 'Region Boundaries',
                    'counties' => 'Counties and Unitary Authorities',
                ];

                $outdatedBoundaries = [];
                $monthMap = [
                    'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
                    'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
                    'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12,
                ];

                foreach ($updateSchedule as $type => $schedule) {
                    $geometry = \Illuminate\Support\Facades\DB::table('boundary_geometries')
                        ->where('boundary_type', $type)
                        ->orderBy('version_date', 'desc')
                        ->first();

                    if ($geometry && $geometry->version_date) {
                        $expectedMonthNum = $monthMap[$schedule['month']] ?? null;
                        if ($expectedMonthNum) {
                            $currentYear = now()->year;
                            $currentMonth = now()->month;

                            if ($currentMonth >= $expectedMonthNum) {
                                $expectedYear = $currentYear;
                            } else {
                                $expectedYear = $currentYear - 1;
                            }

                            $expectedVersion = sprintf('%d-%02d-01', $expectedYear, $expectedMonthNum);

                            try {
                                $currentDate = new \DateTime($geometry->version_date);
                                $expectedDate = new \DateTime($expectedVersion);

                                if ($expectedDate > $currentDate) {
                                    $outdatedBoundaries[] = [
                                        'type' => $type,
                                        'label' => $boundaryTypeLabels[$type],
                                        'current' => \Carbon\Carbon::parse($geometry->version_date)->format('F Y'),
                                        'expected' => \Carbon\Carbon::parse($expectedVersion)->format('F Y'),
                                    ];
                                }
                            } catch (\Exception $e) {
                                // Skip on error
                            }
                        }
                    }
                }
            @endphp

            @if(count($outdatedBoundaries) > 0)
                <div class="mt-6">
                    <flux:card class="border-l-4 border-red-500">
                        <div class="flex items-start gap-3">
                            <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400 flex-shrink-0 mt-1" />
                            <div class="flex-1">
                                <flux:heading>Boundary Updates Available</flux:heading>
                                <flux:subheading>{{ count($outdatedBoundaries) }} boundary {{ count($outdatedBoundaries) === 1 ? 'type' : 'types' }} may have newer versions on ONS</flux:subheading>

                                <div class="mt-4 space-y-2">
                                    @foreach($outdatedBoundaries as $boundary)
                                        <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2 text-sm">
                                            <div>
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $boundary['label'] }}</span>
                                                <span class="text-gray-600 dark:text-gray-400 ml-2">
                                                    Current: {{ $boundary['current'] }} → Expected: {{ $boundary['expected'] }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-4">
                                    <flux:button href="{{ route('admin.boundaries') }}" icon="arrow-up-tray" variant="danger">
                                        Update Boundary Data
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </flux:card>
                </div>
            @endif

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <flux:card>
                    <flux:heading>Quick Actions</flux:heading>
                    <div class="mt-4 flex flex-wrap gap-4">
                        <flux:button href="{{ route('admin.import') }}" icon="arrow-up-tray">Import ONSUD Data</flux:button>
                        <flux:button href="{{ route('tools.lookup') }}" icon="magnifying-glass" variant="ghost">Lookup Postcode</flux:button>
                        <flux:button href="{{ route('admin.versions') }}" icon="clock" variant="ghost">View History</flux:button>
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading>API Access</flux:heading>
                    <flux:subheading>REST API for external applications</flux:subheading>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        Access LocaleLogic data from your applications using our REST API. View documentation and manage API tokens.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-4">
                        <flux:button href="{{ route('api-docs') }}" icon="book-open" variant="primary" target="_blank">View API Docs</flux:button>
                        <flux:button href="{{ route('admin.api-tokens') }}" icon="key" variant="ghost">Manage Tokens</flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </div>
</x-app-layout>
