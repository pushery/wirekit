<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\WireKit;

class MakeCommand extends Command
{
    // `docs.wirekit.app/blueprints/recipes`, not `/recipes`: there has never been a
    // `docs/recipes` directory, and the shipped stubs have always carried the longer
    // path. Only the two strings this command printed were short, and both of them
    // landed a developer on a 404 at the end of a successful scaffold.
    protected $signature = 'wirekit:make {template : Template to scaffold — page:dashboard|page:settings|page:login OR recipe:<name> (see `wirekit:list --category=Marketing` or docs.wirekit.app/blueprints/recipes for the catalog)}';

    protected $description = 'Scaffold a page or recipe using WireKit components';

    /** @var array<string, array{class: string, view: string}> */
    private const TEMPLATES = [
        'page:dashboard' => [
            'class' => 'Dashboard',
            'view' => 'dashboard',
        ],
        'page:settings' => [
            'class' => 'Settings',
            'view' => 'settings',
        ],
        'page:login' => [
            'class' => 'Login',
            'view' => 'login',
        ],
    ];

    /**
     * Recipe templates ship stubs under src/Console/stubs/recipes/<name>.blade.php
     * — derived from the corresponding docs/blueprints/recipes/<name>.md preview blocks.
     * Each scaffold generates a Livewire class + Blade view; the developer
     * adapts the recipe to their data shape.
     *
     * The list and the published recipe catalog are one-to-one in BOTH directions, and
     * only one of them used to be checked. A published page with no stub is a
     * `recipe:<name>` a developer reads about, runs, and is told is unknown — which is
     * what `on-page-toc` and `reading-sidebar` were while the reference page claimed
     * every recipe mirrors a scaffold.
     *
     * @var list<string>
     */
    private const RECIPES = [
        'documentation-reader',
        'feature-numbered-marker',
        'hero-with-code-aside',
        'live-kpi-strip',
        'long-form-article',
        'marketing-landing-page',
        'marketing-landing-toc',
        'on-page-toc',
        'reading-sidebar',
        'stat-with-sparkline',
        'toolbar-filter-bar',
    ];

    public function handle(): int
    {
        $template = $this->argument('template');

        // Recipe templates route through a separate scaffold path so the
        // stub-file loader can read from src/Console/stubs/recipes/.
        if (str_starts_with($template, 'recipe:')) {
            return $this->scaffoldRecipe(substr($template, strlen('recipe:')));
        }

        if (! isset(self::TEMPLATES[$template])) {
            $this->error("Unknown template: {$template}");
            $this->line('  Available pages: '.implode(', ', array_keys(self::TEMPLATES)));
            $this->line('  Available recipes: '.implode(', ', array_map(fn ($r) => "recipe:{$r}", self::RECIPES)));

            return self::FAILURE;
        }

        $meta = self::TEMPLATES[$template];
        $className = $meta['class'];
        $viewName = $meta['view'];

        $livewireClassPath = app_path("Livewire/{$className}.php");
        $viewPath = resource_path("views/livewire/{$viewName}.blade.php");

        if (file_exists($livewireClassPath)) {
            $this->error("File already exists: {$livewireClassPath}");

            return self::FAILURE;
        }

        if (file_exists($viewPath)) {
            $this->error("File already exists: {$viewPath}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($livewireClassPath));
        File::ensureDirectoryExists(dirname($viewPath));

        File::put($livewireClassPath, $this->generateClass($className, $viewName));
        File::put($viewPath, $this->generateView($template));

        $this->info("Created: {$livewireClassPath}");
        $this->info("Created: {$viewPath}");

        return self::SUCCESS;
    }

    /**
     * The generated class renders the view this command actually WROTE.
     *
     * It used to call `Str::kebab($className)` at runtime instead, which was wrong
     * twice over. It named a class the stub never imported — inside
     * `namespace App\Livewire;` an unimported name resolves against that namespace,
     * so it meant `App\Livewire\Str`, a fatal on the FIRST render while the command
     * printed "Created: …" and exited 0. And it derived the view name from a second
     * source: the file is written to `$meta['view']`, so the two agreed only by
     * coincidence, and any template whose view name is not the kebab of its class
     * would have rendered a view that does not exist.
     *
     * Passing the same value that named the file removes both.
     */
    private function generateClass(string $className, string $viewName): string
    {
        $namespace = $this->laravel->getNamespace().'Livewire';

        return <<<PHP
        <?php

        namespace {$namespace};

        use Livewire\Component;

        class {$className} extends Component
        {
            public function render()
            {
                return view('livewire.{$viewName}');
            }
        }
        PHP;
    }

    private function generateView(string $template): string
    {
        return match ($template) {
            'page:dashboard' => <<<'BLADE'
            <div>
                <x-wirekit::heading level="1">Dashboard</x-wirekit::heading>
                <x-wirekit::text variant="muted">Welcome to your dashboard.</x-wirekit::text>

                <x-wirekit::divider />

                <x-wirekit::stats cols="3">
                    <x-wirekit::stat label="Users" value="1,234" />
                    <x-wirekit::stat label="Revenue" value="$12.3k" />
                    <x-wirekit::stat label="Orders" value="89" />
                </x-wirekit::stats>
            </div>
            BLADE,

            'page:settings' => <<<'BLADE'
            <div>
                <x-wirekit::heading level="1">Settings</x-wirekit::heading>
                <x-wirekit::text variant="muted">Manage your account settings.</x-wirekit::text>

                <x-wirekit::divider />

                <x-wirekit::stack gap="lg">
                    <x-wirekit::card>
                        <x-wirekit::heading level="2" size="lg">Profile</x-wirekit::heading>
                        <x-wirekit::stack gap="md">
                            <x-wirekit::input label="Name" />
                            <x-wirekit::input label="Email" type="email" />
                            <x-wirekit::button>Save</x-wirekit::button>
                        </x-wirekit::stack>
                    </x-wirekit::card>
                </x-wirekit::stack>
            </div>
            BLADE,

            'page:login' => <<<'BLADE'
            <div>
                <x-wirekit::center class="min-h-screen">
                    <x-wirekit::card class="w-full max-w-md">
                        <x-wirekit::stack gap="lg">
                            <x-wirekit::heading level="1" size="xl">Sign In</x-wirekit::heading>
                            <x-wirekit::input label="Email" type="email" />
                            <x-wirekit::password-input label="Password" />
                            <x-wirekit::button class="w-full">Sign In</x-wirekit::button>
                        </x-wirekit::stack>
                    </x-wirekit::card>
                </x-wirekit::center>
            </div>
            BLADE,

            default => '<div>{{ $slot }}</div>',
        };
    }

    /**
     * Scaffold a Livewire page from a shipped recipe stub.
     *
     * Each recipe corresponds to a `docs/blueprints/recipes/<name>.md` page; the
     * Blade stub under `src/Console/stubs/recipes/<name>.blade.php`
     * captures the recipe's structural skeleton with cross-link to
     * docs.wirekit.app for the full composition.
     */
    private function scaffoldRecipe(string $recipe): int
    {
        if (! in_array($recipe, self::RECIPES, true)) {
            $this->error("Unknown recipe: {$recipe}");
            $this->line('  Available: '.implode(', ', self::RECIPES));

            $hint = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein($recipe, self::RECIPES)
            );
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        // Derive the class name from the recipe slug — kebab to PascalCase.
        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $recipe)));
        $viewName = $recipe;

        $livewireClassPath = app_path("Livewire/{$className}.php");
        $viewPath = resource_path("views/livewire/{$viewName}.blade.php");

        if (file_exists($livewireClassPath)) {
            $this->error("File already exists: {$livewireClassPath}");

            return self::FAILURE;
        }
        if (file_exists($viewPath)) {
            $this->error("File already exists: {$viewPath}");

            return self::FAILURE;
        }

        $stubPath = __DIR__.'/stubs/recipes/'.$recipe.'.blade.php';
        if (! file_exists($stubPath)) {
            $this->error("Recipe stub missing: {$stubPath}");
            $this->line('  This is a packaging bug — please report at https://github.com/pushery/wirekit/issues.');

            return self::FAILURE;
        }
        $viewBody = (string) file_get_contents($stubPath);

        File::ensureDirectoryExists(dirname($livewireClassPath));
        File::ensureDirectoryExists(dirname($viewPath));

        File::put($livewireClassPath, $this->generateClass($className, $viewName));
        File::put($viewPath, $viewBody);

        $this->info("Created: {$livewireClassPath}");
        $this->info("Created: {$viewPath}");
        $this->line('  Recipe reference: '.WireKit::DOCS_URL."/blueprints/recipes/{$recipe}");

        return self::SUCCESS;
    }
}
