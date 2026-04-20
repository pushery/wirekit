<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'wirekit:install {--preset=default : Theme preset (default, minimal, soft, material, brutalist, retro-terminal, cupertino)}';

    protected $description = 'Install WireKit into your Laravel application';

    public function handle(): int
    {
        $this->info('Installing WireKit...');
        $this->line('');

        $this->publishConfig();
        $this->publishAssets();
        $this->addTailwindSource();
        $this->addBladeDirectives();
        $this->addGitignoreEntry();

        $preset = $this->option('preset');
        if ($preset !== 'default') {
            $this->call('wirekit:theme', ['preset' => $preset]);
        }

        $this->line('');
        $this->call('wirekit:verify');

        $this->line('');
        $this->info('WireKit installed successfully!');

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        if (file_exists(config_path('wirekit.php'))) {
            $this->line('  <fg=yellow>!</> config/wirekit.php already exists — skipping');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-config']);
        $this->line('  <fg=green>✓</> Published config/wirekit.php');
    }

    private function publishAssets(): void
    {
        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-assets', '--force' => true]);
        $this->line('  <fg=green>✓</> Published assets to public/vendor/wirekit/');
    }

    private function addTailwindSource(): void
    {
        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->line('  <fg=yellow>!</> resources/css/app.css not found — add @source manually');

            return;
        }

        $content = file_get_contents($appCss);

        if (str_contains($content, 'wirekit') && str_contains($content, '@source')) {
            $this->line('  <fg=yellow>!</> Tailwind @source already configured — skipping');

            return;
        }

        $sourceLine = "@source '../../vendor/pushery/wirekit/resources/views/**/*.blade.php';";

        // Insert after @import 'tailwindcss' if present, otherwise append
        if (str_contains($content, "@import 'tailwindcss'") || str_contains($content, '@import "tailwindcss"')) {
            $content = preg_replace(
                '/(@import\s+[\'"]tailwindcss[\'"];?)/',
                "$1\n{$sourceLine}",
                $content,
                1
            );
        } else {
            $content .= "\n{$sourceLine}\n";
        }

        File::put($appCss, $content);
        $this->line('  <fg=green>✓</> Added @source for WireKit to app.css');
    }

    private function addBladeDirectives(): void
    {
        $layoutPaths = [
            resource_path('views/components/layouts/app.blade.php'),
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/components/layout.blade.php'),
        ];

        $layoutFile = null;
        foreach ($layoutPaths as $path) {
            if (file_exists($path)) {
                $layoutFile = $path;

                break;
            }
        }

        if (! $layoutFile) {
            $this->line('  <fg=yellow>!</> No layout file found — add @wirekitStyles/@wirekitScripts manually');

            return;
        }

        $content = file_get_contents($layoutFile);
        $modified = false;

        if (! str_contains($content, '@wirekitStyles')) {
            // Add before </head> or @vite
            if (str_contains($content, '</head>')) {
                $content = str_replace('</head>', "    @wirekitStyles\n</head>", $content);
                $modified = true;
            }
        }

        if (! str_contains($content, '@wirekitScripts')) {
            // Add before </body>
            if (str_contains($content, '</body>')) {
                $content = str_replace('</body>', "    @wirekitScripts\n</body>", $content);
                $modified = true;
            }
        }

        if ($modified) {
            File::put($layoutFile, $content);
            $this->line('  <fg=green>✓</> Added Blade directives to '.basename($layoutFile));
        } else {
            $this->line('  <fg=yellow>!</> Blade directives already present in layout');
        }
    }

    private function addGitignoreEntry(): void
    {
        $gitignorePath = base_path('.gitignore');

        if (! file_exists($gitignorePath)) {
            return;
        }

        $content = file_get_contents($gitignorePath);

        if (str_contains($content, 'vendor/wirekit')) {
            return;
        }

        File::append($gitignorePath, "\n/public/vendor/wirekit\n");
        $this->line('  <fg=green>✓</> Added public/vendor/wirekit to .gitignore');
    }
}
