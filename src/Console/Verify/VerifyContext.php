<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify;

use Illuminate\Support\Facades\File;
use Pushery\WireKit\Console\VerifyInstallationCommand;

/**
 * What the checks of one `wirekit:verify` run share: the command, its report, the Blade files
 * the run reads, and the two findings one check leaves for the next.
 *
 * One context per run, for the same reason as the report: a memoized file list that outlived its
 * run would audit the list the first run saw, and a file created in between would be invisible.
 *
 * @internal
 */
final class VerifyContext
{
    /** Whether the Blade directive check found each directive in any Blade file at all. */
    public bool $stylesFoundAnywhere = false;

    public bool $scriptsFoundAnywhere = false;

    /** @var string[]|null */
    private ?array $allBladeFiles = null;

    public function __construct(
        public readonly VerifyInstallationCommand $command,
        public readonly VerifyReport $report,
    ) {}

    /**
     * Every Blade file in resources/views/, recursively, read once per run.
     * Scans beyond layout directories to catch directives in any template.
     *
     * @return string[]
     */
    public function allBladeFiles(): array
    {
        if ($this->allBladeFiles !== null) {
            return $this->allBladeFiles;
        }

        $viewsPath = resource_path('views');

        if (! is_dir($viewsPath)) {
            $this->allBladeFiles = [];

            return [];
        }

        $this->allBladeFiles = collect(File::allFiles($viewsPath))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();

        return $this->allBladeFiles;
    }
}
