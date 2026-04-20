<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ThemeCommand extends Command
{
    protected $signature = 'wirekit:theme {preset : Theme preset name}';

    protected $description = 'Apply a WireKit theme preset to your app.css';

    /** @var array<string, array{label: string, vars: string}> */
    private const PRESETS = [
        'minimal' => [
            'label' => 'Minimal',
            'vars' => <<<'CSS'
    /* Minimal — clean, borderless aesthetic */
    --radius-wk-sm: 0px;
    --radius-wk-md: 0px;
    --radius-wk-lg: 0px;
    --radius-wk-xl: 0px;
    --radius-wk-full: 0px;
    --ring-wk-width: 2px;
    --shadow-wk-sm: none;
    --shadow-wk-md: none;
    --shadow-wk-lg: none;
CSS,
        ],
        'soft' => [
            'label' => 'Soft',
            'vars' => <<<'CSS'
    /* Soft — rounded, gentle shadows */
    --radius-wk-sm: 0.5rem;
    --radius-wk-md: 0.75rem;
    --radius-wk-lg: 1rem;
    --radius-wk-xl: 1.5rem;
    --color-wk-accent: oklch(0.541 0.281 293.009);
CSS,
        ],
        'material' => [
            'label' => 'Material',
            'vars' => <<<'CSS'
    /* Material — Google Material Design 3 inspired */
    --radius-wk-sm: 0.25rem;
    --radius-wk-md: 0.5rem;
    --radius-wk-lg: 0.75rem;
    --color-wk-accent: oklch(0.457 0.24 277.023);
    --transition-wk-easing: cubic-bezier(0, 0, 0.2, 1);
CSS,
        ],
        'brutalist' => [
            'label' => 'Brutalist',
            'vars' => <<<'CSS'
    /* Brutalist — bold borders, no shadows */
    --radius-wk-sm: 0px;
    --radius-wk-md: 0px;
    --radius-wk-lg: 0px;
    --radius-wk-xl: 0px;
    --border-wk-width: 2px;
    --shadow-wk-sm: none;
    --shadow-wk-md: none;
    --shadow-wk-lg: none;
CSS,
        ],
        'retro-terminal' => [
            'label' => 'Retro Terminal',
            'vars' => <<<'CSS'
    /* Retro Terminal — green-on-black hacker aesthetic */
    --color-wk-accent: oklch(0.723 0.219 149.579);
    --radius-wk-sm: 0px;
    --radius-wk-md: 0px;
    --radius-wk-lg: 0px;
    --radius-wk-xl: 0px;
CSS,
        ],
        'cupertino' => [
            'label' => 'Cupertino',
            'vars' => <<<'CSS'
    /* Cupertino — Apple HIG inspired */
    --radius-wk-sm: 0.375rem;
    --radius-wk-md: 0.625rem;
    --radius-wk-lg: 0.875rem;
    --radius-wk-xl: 1.75rem;
    --color-wk-accent: oklch(0.546 0.245 262.881);
    --transition-wk-easing: cubic-bezier(0, 0, 0.58, 1);
CSS,
        ],
    ];

    public function handle(): int
    {
        $preset = $this->argument('preset');

        if (! isset(self::PRESETS[$preset])) {
            $this->error("Unknown preset: {$preset}");
            $this->line('  Available: '.implode(', ', array_keys(self::PRESETS)));

            return self::FAILURE;
        }

        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->error('resources/css/app.css not found');

            return self::FAILURE;
        }

        $content = file_get_contents($appCss);
        $theme = self::PRESETS[$preset];

        // Remove existing WireKit theme block if present
        $content = preg_replace('/\/\* wirekit:theme start \*\/.*?\/\* wirekit:theme end \*\/\n?/s', '', $content);

        $themeBlock = "\n/* wirekit:theme start */\n@theme {\n{$theme['vars']}\n}\n/* wirekit:theme end */\n";
        $content .= $themeBlock;

        File::put($appCss, $content);

        $this->info("Applied theme: {$theme['label']}");
        $this->line('  Theme variables injected into resources/css/app.css');

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    public static function availablePresets(): array
    {
        return array_keys(self::PRESETS);
    }
}
