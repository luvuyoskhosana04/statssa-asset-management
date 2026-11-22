<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $reference = Project::withoutGlobalScopes()->where('code', 'STATSSA-414-2026')->first();
        $locations = Location::query()->where(function ($query) use ($reference) {
            $query->whereNull('company_id');
            if ($reference?->company_id) {
                $query->orWhere('company_id', $reference->company_id);
            }
        })->orderBy('name')->pluck('id');
        $names = [
            'Census Field Operations', 'Household Survey Programme', 'Quarterly Labour Survey',
            'Community Mapping', 'Agricultural Statistics', 'Business Register Review',
            'Price Collection Programme', 'Provincial Data Quality', 'Population Estimates',
            'Digital Fieldwork Pilot', 'Office Equipment Renewal', 'Regional Training Programme',
            'Census Readiness Planning', 'Survey Technology Refresh', 'Community Outreach Planning',
            'Rural Enumeration Pilot', 'Field Logistics Review', 'Data Capture Modernisation',
            'Enumeration Archive Review', 'Regional Survey Closeout',
        ];
        $created = DB::transaction(function () use ($reference, $locations, $names) {
            $created = 0;
            foreach ($names as $index => $name) {
                $status = $index < 12 ? 'active' : ($index < 16 ? 'planned' : ($index < 18 ? 'suspended' : 'ended'));
                $start = $status === 'planned' ? today()->addMonths($index - 11) : today()->subMonths($status === 'ended' ? 18 : ($index % 6) + 1);
                $end = $status === 'ended' ? today()->subMonths($index - 16) : today()->addMonths($status === 'planned' ? 12 + $index - 11 : 3 + $index % 9);
                $project = Project::withoutGlobalScopes()->firstOrCreate(
                    ['code' => sprintf('STATSSA-DEMO-%03d', $index + 1)],
                    [
                        'name' => 'Demo: '.$name,
                        'status' => $status,
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                        'company_id' => $reference?->company_id,
                        'location_id' => $locations->isEmpty() ? null : $locations[$index % $locations->count()],
                        'created_by' => $reference?->created_by,
                        'notes' => 'Fictional demo project for testing and demonstrations. No employees or inventory have been assigned by this seeder.',
                    ],
                );
                if (!$project->exists) {
                    throw new \RuntimeException('Demo project validation failed: '.$project->getErrors()->first());
                }
                $created += (int) $project->wasRecentlyCreated;
            }
            return $created;
        });
        $this->command?->info($created.' demo projects added. Existing projects and inventory were preserved.');
    }
}
