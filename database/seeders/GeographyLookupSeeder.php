<?php

namespace Database\Seeders;

use App\Models\Constituency;
use App\Models\County;
use App\Models\CountyElectoralDivision;
use App\Models\LocalAuthorityDistrict;
use App\Models\Parish;
use App\Models\PoliceForceArea;
use App\Models\Region;
use App\Models\Ward;
use Illuminate\Database\Seeder;

/**
 * Geography Lookup Tables Seeder
 *
 * Seeds lookup tables with sample UK geography data for development/testing.
 * For production, use: php artisan geography:import --all
 */
class GeographyLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding geography lookup tables...');

        $this->seedRegions();
        $this->seedCounties();
        $this->seedLocalAuthorityDistricts();
        $this->seedWards();
        $this->seedCountyElectoralDivisions();
        $this->seedParishes();
        $this->seedConstituencies();
        $this->seedPoliceForceAreas();

        $this->command->info('Geography lookup tables seeded successfully!');
    }

    protected function seedRegions(): void
    {
        $regions = [
            ['rgn25cd' => 'E12000001', 'rgn25nm' => 'North East'],
            ['rgn25cd' => 'E12000002', 'rgn25nm' => 'North West'],
            ['rgn25cd' => 'E12000003', 'rgn25nm' => 'Yorkshire and The Humber'],
            ['rgn25cd' => 'E12000004', 'rgn25nm' => 'East Midlands'],
            ['rgn25cd' => 'E12000005', 'rgn25nm' => 'West Midlands'],
            ['rgn25cd' => 'E12000006', 'rgn25nm' => 'East of England'],
            ['rgn25cd' => 'E12000007', 'rgn25nm' => 'London'],
            ['rgn25cd' => 'E12000008', 'rgn25nm' => 'South East'],
            ['rgn25cd' => 'E12000009', 'rgn25nm' => 'South West'],
        ];

        foreach ($regions as $region) {
            Region::create($region);
        }

        $this->command->info('✓ Seeded ' . count($regions) . ' regions');
    }

    protected function seedCounties(): void
    {
        $counties = [
            ['cty25cd' => 'E10000003', 'cty25nm' => 'Cambridgeshire'],
            ['cty25cd' => 'E10000006', 'cty25nm' => 'Cumbria'],
            ['cty25cd' => 'E10000007', 'cty25nm' => 'Derbyshire'],
            ['cty25cd' => 'E10000008', 'cty25nm' => 'Devon'],
            ['cty25cd' => 'E10000011', 'cty25nm' => 'East Sussex'],
            ['cty25cd' => 'E10000012', 'cty25nm' => 'Essex'],
            ['cty25cd' => 'E10000013', 'cty25nm' => 'Gloucestershire'],
            ['cty25cd' => 'E10000014', 'cty25nm' => 'Hampshire'],
            ['cty25cd' => 'E10000015', 'cty25nm' => 'Hertfordshire'],
            ['cty25cd' => 'E10000016', 'cty25nm' => 'Kent'],
            ['cty25cd' => 'E10000017', 'cty25nm' => 'Lancashire'],
            ['cty25cd' => 'E10000023', 'cty25nm' => 'North Yorkshire'],
            ['cty25cd' => 'E10000027', 'cty25nm' => 'Somerset'],
            ['cty25cd' => 'E10000029', 'cty25nm' => 'Suffolk'],
            ['cty25cd' => 'E10000030', 'cty25nm' => 'Surrey'],
            ['cty25cd' => 'E10000034', 'cty25nm' => 'Worcestershire'],
        ];

        foreach ($counties as $county) {
            County::create($county);
        }

        $this->command->info('✓ Seeded ' . count($counties) . ' counties');
    }

    protected function seedLocalAuthorityDistricts(): void
    {
        $lads = [
            // London Boroughs
            ['lad25cd' => 'E09000001', 'lad25nm' => 'City of London', 'lad25nmw' => null, 'rgn25cd' => 'E12000007'],
            ['lad25cd' => 'E09000007', 'lad25nm' => 'Camden', 'lad25nmw' => null, 'rgn25cd' => 'E12000007'],
            ['lad25cd' => 'E09000033', 'lad25nm' => 'Westminster', 'lad25nmw' => null, 'rgn25cd' => 'E12000007'],

            // Unitary Authorities
            ['lad25cd' => 'E06000054', 'lad25nm' => 'Wiltshire', 'lad25nmw' => null, 'rgn25cd' => 'E12000009'],
            ['lad25cd' => 'E06000015', 'lad25nm' => 'Derby', 'lad25nmw' => null, 'rgn25cd' => 'E12000004'],
            ['lad25cd' => 'E06000023', 'lad25nm' => 'Bristol, City of', 'lad25nmw' => null, 'rgn25cd' => 'E12000009'],

            // Welsh Authorities
            ['lad25cd' => 'W06000015', 'lad25nm' => 'Cardiff', 'lad25nmw' => 'Caerdydd', 'rgn25cd' => null],
            ['lad25cd' => 'W06000011', 'lad25nm' => 'Swansea', 'lad25nmw' => 'Abertawe', 'rgn25cd' => null],

            // Scottish Authorities
            ['lad25cd' => 'S12000033', 'lad25nm' => 'Aberdeen City', 'lad25nmw' => null, 'rgn25cd' => null],
            ['lad25cd' => 'S12000036', 'lad25nm' => 'City of Edinburgh', 'lad25nmw' => null, 'rgn25cd' => null],

            // District Councils
            ['lad25cd' => 'E07000228', 'lad25nm' => 'Mid Suffolk', 'lad25nmw' => null, 'rgn25cd' => 'E12000006'],
            ['lad25cd' => 'E07000246', 'lad25nm' => 'Somerset West and Taunton', 'lad25nmw' => null, 'rgn25cd' => 'E12000009'],
        ];

        foreach ($lads as $lad) {
            LocalAuthorityDistrict::create($lad);
        }

        $this->command->info('✓ Seeded ' . count($lads) . ' local authority districts');
    }

    protected function seedWards(): void
    {
        $wards = [
            // Westminster wards
            ['wd25cd' => 'E05013806', 'wd25nm' => 'St James\'s', 'lad25cd' => 'E09000033'],
            ['wd25cd' => 'E05013807', 'wd25nm' => 'Marylebone High Street', 'lad25cd' => 'E09000033'],
            ['wd25cd' => 'E05013808', 'wd25nm' => 'Knightsbridge & Belgravia', 'lad25cd' => 'E09000033'],

            // Wiltshire wards
            ['wd25cd' => 'E05008027', 'wd25nm' => 'Melksham Central', 'lad25cd' => 'E06000054'],
            ['wd25cd' => 'E05008028', 'wd25nm' => 'Melksham North', 'lad25cd' => 'E06000054'],
            ['wd25cd' => 'E05008029', 'wd25nm' => 'Melksham South', 'lad25cd' => 'E06000054'],

            // Cardiff wards
            ['wd25cd' => 'W05001167', 'wd25nm' => 'Cathays', 'lad25cd' => 'W06000015'],
            ['wd25cd' => 'W05001168', 'wd25nm' => 'Gabalfa', 'lad25cd' => 'W06000015'],

            // Edinburgh wards
            ['wd25cd' => 'S13003134', 'wd25nm' => 'City Centre', 'lad25cd' => 'S12000036'],
            ['wd25cd' => 'S13003135', 'wd25nm' => 'Leith', 'lad25cd' => 'S12000036'],
        ];

        foreach ($wards as $ward) {
            Ward::create($ward);
        }

        $this->command->info('✓ Seeded ' . count($wards) . ' wards');
    }

    protected function seedCountyElectoralDivisions(): void
    {
        $ceds = [
            ['ced25cd' => 'E58000001', 'ced25nm' => 'Abbey', 'cty25cd' => 'E10000003'],
            ['ced25cd' => 'E58000002', 'ced25nm' => 'Arbury', 'cty25cd' => 'E10000003'],
            ['ced25cd' => 'E58000100', 'ced25nm' => 'Allerdale North', 'cty25cd' => 'E10000006'],
            ['ced25cd' => 'E58000200', 'ced25nm' => 'Alfreton & Somercotes', 'cty25cd' => 'E10000007'],
        ];

        foreach ($ceds as $ced) {
            CountyElectoralDivision::create($ced);
        }

        $this->command->info('✓ Seeded ' . count($ceds) . ' county electoral divisions');
    }

    protected function seedParishes(): void
    {
        $parishes = [
            ['parncp25cd' => 'E04012690', 'parncp25nm' => 'Melksham Without', 'parncp25nmw' => null, 'lad25cd' => 'E06000054'],
            ['parncp25cd' => 'E04012691', 'parncp25nm' => 'Melksham', 'parncp25nmw' => null, 'lad25cd' => 'E06000054'],
            ['parncp25cd' => 'E04001546', 'parncp25nm' => 'Chippenham Without', 'parncp25nmw' => null, 'lad25cd' => 'E06000054'],
            ['parncp25cd' => 'E04001547', 'parncp25nm' => 'Chippenham', 'parncp25nmw' => null, 'lad25cd' => 'E06000054'],
            ['parncp25cd' => 'W04000001', 'parncp25nm' => 'Aberdare', 'parncp25nmw' => 'Aberdâr', 'lad25cd' => 'W06000015'],
            ['parncp25cd' => 'W04000002', 'parncp25nm' => 'Aberavon', 'parncp25nmw' => 'Aberafan', 'lad25cd' => 'W06000011'],
        ];

        foreach ($parishes as $parish) {
            Parish::create($parish);
        }

        $this->command->info('✓ Seeded ' . count($parishes) . ' parishes');
    }

    protected function seedConstituencies(): void
    {
        $constituencies = [
            ['pcon24cd' => 'E14000639', 'pcon24nm' => 'Cities of London and Westminster'],
            ['pcon24cd' => 'E14000673', 'pcon24nm' => 'Holborn and St Pancras'],
            ['pcon24cd' => 'E14000558', 'pcon24nm' => 'Melksham and Devizes'],
            ['pcon24cd' => 'E14000530', 'pcon24nm' => 'Chippenham'],
            ['pcon24cd' => 'W07000074', 'pcon24nm' => 'Cardiff Central'],
            ['pcon24cd' => 'W07000078', 'pcon24nm' => 'Swansea West'],
            ['pcon24cd' => 'S14000024', 'pcon24nm' => 'Edinburgh Central'],
            ['pcon24cd' => 'S14000001', 'pcon24nm' => 'Aberdeen Central'],
        ];

        foreach ($constituencies as $constituency) {
            Constituency::create($constituency);
        }

        $this->command->info('✓ Seeded ' . count($constituencies) . ' constituencies');
    }

    protected function seedPoliceForceAreas(): void
    {
        $police = [
            ['pfa23cd' => 'E23000001', 'pfa23nm' => 'Metropolitan Police'],
            ['pfa23cd' => 'E23000007', 'pfa23nm' => 'Wiltshire'],
            ['pfa23cd' => 'E23000010', 'pfa23nm' => 'Avon and Somerset'],
            ['pfa23cd' => 'W15000004', 'pfa23nm' => 'South Wales'],
            ['pfa23cd' => 'S23000009', 'pfa23nm' => 'Police Scotland'],
        ];

        foreach ($police as $pfa) {
            PoliceForceArea::create($pfa);
        }

        $this->command->info('✓ Seeded ' . count($police) . ' police force areas');
    }
}
