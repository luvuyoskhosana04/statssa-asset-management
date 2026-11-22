<?php

namespace Database\Seeders;

use App\Models\Statuslabel;
use App\Support\OperationalAssetStatuses;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OperationalAssetStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (OperationalAssetStatuses::DEFINITIONS as $name => $flags) {
                $label = Statuslabel::firstOrCreate(['name' => $name], $flags + [
                    'notes' => 'Operational status restored from the legacy Stats SA status catalogue.',
                ]);
                if (!$label->exists) {
                    throw new \RuntimeException('Operational status validation failed: '.$label->getErrors()->first());
                }
                // Expose the catalogue without rewriting existing labels, defaults or assets.
                $label->show_in_nav = 1;
                if (!$label->save()) {
                    throw new \RuntimeException('Operational status validation failed: '.$label->getErrors()->first());
                }
            }
        });
        $this->command?->info('All ten operational asset statuses are available in navigation. Existing assets and defaults were preserved.');
    }
}
