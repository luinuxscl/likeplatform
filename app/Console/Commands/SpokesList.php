<?php

namespace App\Console\Commands;

use App\Core\Spokes\SpokeRegistry;
use App\Models\Spoke;
use Illuminate\Console\Command;

class SpokesList extends Command
{
    protected $signature = 'spokes:list {--all : Include inactive spokes}';

    protected $description = 'List all Spokes in the platform catalogue.';

    public function handle(SpokeRegistry $registry): int
    {
        $spokes = $this->option('all')
            ? Spoke::orderBy('sort_order')->get()
            : $registry->all();

        if ($spokes->isEmpty()) {
            $this->info('No spokes found in the catalogue.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Icon', 'Plans'],
            $spokes->map(fn (Spoke $spoke) => [
                $spoke->id,
                $spoke->name,
                $spoke->slug,
                $spoke->icon ?? '-',
                $spoke->plans->pluck('name')->join(', '),
            ]),
        );

        return self::SUCCESS;
    }
}
