<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;

class ListComponentsCommand extends Command
{
    protected $signature = 'wirekit:list {--category= : Filter by category}';

    protected $description = 'List all WireKit components grouped by category';

    public function handle(): int
    {
        $categoryFilter = $this->option('category');
        $components = ComponentRegistry::all();

        if ($categoryFilter) {
            $filtered = ComponentRegistry::category($categoryFilter);
            if ($filtered === []) {
                $this->error("Unknown category: {$categoryFilter}");
                $this->line('  Available: '.implode(', ', ComponentRegistry::categories()));

                return self::FAILURE;
            }
            $components = $filtered;
        }

        $grouped = [];
        foreach ($components as $name => $meta) {
            $grouped[$meta['category']][$name] = $meta;
        }

        ksort($grouped);

        $total = count($components);
        $this->info("WireKit Components ({$total})");
        $this->line('');

        foreach ($grouped as $category => $items) {
            ksort($items);
            $this->line("  <fg=yellow>{$category}</> (".count($items).')');

            foreach ($items as $name => $meta) {
                $this->line("    <fg=green>{$name}</> — {$meta['description']}");
            }

            $this->line('');
        }

        $this->line('  Use <fg=cyan>php artisan wirekit:show {name}</> for details.');

        return self::SUCCESS;
    }
}
