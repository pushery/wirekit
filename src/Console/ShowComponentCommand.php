<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;

class ShowComponentCommand extends Command
{
    protected $signature = 'wirekit:show {name : Component name (e.g. button, modal)}';

    protected $description = 'Show details for a WireKit component (props, category, usage)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $meta = ComponentRegistry::get($name);

        if ($meta === null) {
            $this->error("Unknown component: {$name}");

            // Suggest similar names
            $all = array_keys(ComponentRegistry::all());
            $suggestions = array_filter($all, fn ($c) => str_contains($c, $name) || str_contains($name, $c));
            if ($suggestions !== []) {
                $this->line('  Did you mean: '.implode(', ', $suggestions));
            }

            return self::FAILURE;
        }

        $this->info("Component: {$name}");
        $this->line('');
        $this->line("  <fg=yellow>Category:</>    {$meta['category']}");
        $this->line("  <fg=yellow>Description:</> {$meta['description']}");
        $this->line("  <fg=yellow>Tag:</>         <x-wirekit::{$name}>");
        $this->line('');

        // Extract props from blade file
        $props = ComponentRegistry::extractProps($name);

        if ($props !== []) {
            $this->line('  <fg=yellow>Props:</>');

            foreach ($props as $propName => $default) {
                $this->line("    <fg=green>{$propName}</> = {$default}");
            }
        } else {
            $this->line('  <fg=yellow>Props:</> (none or class-based)');
        }

        $this->line('');

        // Check for sub-components
        $subDir = __DIR__.'/../../resources/views/components/'.$name;
        if (is_dir($subDir)) {
            $subFiles = glob("{$subDir}/*.blade.php") ?: [];
            if ($subFiles !== []) {
                $this->line('  <fg=yellow>Sub-components:</>');
                foreach ($subFiles as $file) {
                    $subName = basename($file, '.blade.php');
                    if ($subName === 'index') {
                        continue;
                    }
                    $this->line("    <fg=green><x-wirekit::{$name}.{$subName}></>");
                }
                $this->line('');
            }
        }

        $this->line("  <fg=yellow>Docs:</> docs/components/{$name}.md");

        return self::SUCCESS;
    }
}
