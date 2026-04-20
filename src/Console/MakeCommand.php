<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeCommand extends Command
{
    protected $signature = 'wirekit:make {template : Template to scaffold (page:dashboard, page:settings, page:login)}';

    protected $description = 'Scaffold a page using WireKit components';

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

    public function handle(): int
    {
        $template = $this->argument('template');

        if (! isset(self::TEMPLATES[$template])) {
            $this->error("Unknown template: {$template}");
            $this->line('  Available: '.implode(', ', array_keys(self::TEMPLATES)));

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

        File::put($livewireClassPath, $this->generateClass($className));
        File::put($viewPath, $this->generateView($template));

        $this->info("Created: {$livewireClassPath}");
        $this->info("Created: {$viewPath}");

        return self::SUCCESS;
    }

    private function generateClass(string $className): string
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
                return view('livewire.'.Str::kebab('{$className}'));
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
}
