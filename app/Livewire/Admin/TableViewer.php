<?php

namespace App\Livewire\Admin;

use App\Models\BoundaryName;
use App\Models\CountyElectoralDivision;
use App\Models\Ward;
use App\Models\Parish;
use App\Models\LocalAuthorityDistrict;
use App\Models\Constituency;
use App\Models\Region;
use App\Models\PoliceForceArea;
use App\Models\WardHierarchyLookup;
use App\Models\ParishLookup;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Table Viewer')]
class TableViewer extends Component
{
    use WithPagination;

    public string $selectedTable = 'boundary_names';
    public string $searchTerm = '';

    /**
     * Available tables for viewing
     */
    protected array $tables = [
        'boundary_names' => [
            'label' => 'Boundary Names',
            'model' => BoundaryName::class,
            'columns' => ['gss_code', 'name', 'name_welsh'],
            'searchable' => ['gss_code', 'name', 'name_welsh'],
        ],
        'county_electoral_divisions' => [
            'label' => 'County Electoral Divisions',
            'model' => CountyElectoralDivision::class,
            'columns' => ['ced25cd', 'ced25nm', 'cty25cd'],
            'searchable' => ['ced25cd', 'ced25nm'],
        ],
        'wards' => [
            'label' => 'Electoral Wards',
            'model' => Ward::class,
            'columns' => ['wd25cd', 'wd25nm', 'lad25cd'],
            'searchable' => ['wd25cd', 'wd25nm'],
        ],
        'parishes' => [
            'label' => 'Parishes',
            'model' => Parish::class,
            'columns' => ['parncp25cd', 'parncp25nm', 'parncp25nmw', 'lad25cd'],
            'searchable' => ['parncp25cd', 'parncp25nm', 'parncp25nmw'],
        ],
        'local_authority_districts' => [
            'label' => 'Local Authority Districts',
            'model' => LocalAuthorityDistrict::class,
            'columns' => ['lad25cd', 'lad25nm', 'lad25nmw'],
            'searchable' => ['lad25cd', 'lad25nm', 'lad25nmw'],
        ],
        'constituencies' => [
            'label' => 'Parliamentary Constituencies',
            'model' => Constituency::class,
            'columns' => ['pcon24cd', 'pcon24nm'],
            'searchable' => ['pcon24cd', 'pcon24nm'],
        ],
        'regions' => [
            'label' => 'Regions',
            'model' => Region::class,
            'columns' => ['rgn25cd', 'rgn25nm'],
            'searchable' => ['rgn25cd', 'rgn25nm'],
        ],
        'police_force_areas' => [
            'label' => 'Police Force Areas',
            'model' => PoliceForceArea::class,
            'columns' => ['pfa23cd', 'pfa23nm'],
            'searchable' => ['pfa23cd', 'pfa23nm'],
        ],
        'ward_hierarchy_lookups' => [
            'label' => 'Ward Hierarchy Lookups',
            'model' => WardHierarchyLookup::class,
            'columns' => ['wd25cd', 'lad25cd', 'cty25cd'],
            'searchable' => ['wd25cd'],
        ],
        'parish_lookups' => [
            'label' => 'Parish Lookups',
            'model' => ParishLookup::class,
            'columns' => ['parncp25cd', 'lad25cd'],
            'searchable' => ['parncp25cd'],
        ],
    ];

    public function updatedSelectedTable()
    {
        $this->resetPage();
        $this->searchTerm = '';
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }

    public function getTableConfig(): array
    {
        return $this->tables[$this->selectedTable];
    }

    public function render()
    {
        $config = $this->getTableConfig();
        $modelClass = $config['model'];

        $query = $modelClass::query();

        // Apply search if search term is provided
        if (!empty($this->searchTerm)) {
            $query->where(function ($q) use ($config) {
                foreach ($config['searchable'] as $column) {
                    $q->orWhere($column, 'ilike', '%' . $this->searchTerm . '%');
                }
            });
        }

        $records = $query->paginate(25);

        return view('livewire.admin.table-viewer', [
            'records' => $records,
            'columns' => $config['columns'],
            'tableLabel' => $config['label'],
            'availableTables' => collect($this->tables)->map(fn($table) => $table['label'])->toArray(),
        ]);
    }
}
