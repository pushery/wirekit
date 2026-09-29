<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Composer\InstalledVersions;
use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its environment tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class InstalledPackageMatchesLockCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkInstalledPackageMatchesLock();
    }

    /**
     * Is the WireKit in `vendor/` the one this app's lockfile names?
     *
     * A developer checking whether an upstream capability has landed reads the source in
     * `vendor/`, and that tree can be OLDER than the lockfile beside it while agreeing with
     * it about the version. It happens when something updates the lock — a sync action, a
     * merged dependency pull request, a colleague's commit — and `composer install` has not
     * run locally since. Reading the vendor source then says a prop does not exist when it
     * shipped days ago, and nothing in the repository looks wrong.
     *
     * It compares `source.reference`, never `dist.reference`, and that is the difference
     * between a useful check and a permanent false alarm. A path repository — which is how
     * this package's own sample app installs it — has no `source` key at all and a
     * `dist.reference` that is a CONTENT HASH, not a commit. Comparing that against the
     * installed reference reports a mismatch on every healthy run.
     *
     * So a path install is reported as NOT MEASURED rather than as clean. An answer nobody
     * could give and an all-clear must not print the same way.
     */
    private function checkInstalledPackageMatchesLock(): void
    {
        if (! class_exists(InstalledVersions::class)) {
            $this->reportInfo('Installed-vs-locked check skipped (Composer runtime API unavailable)');

            return;
        }

        $lockPath = base_path('composer.lock');

        if (! is_file($lockPath)) {
            $this->reportInfo('No composer.lock beside the app — nothing to compare the installed package against');

            return;
        }

        $lock = json_decode((string) file_get_contents($lockPath), true);

        if (! is_array($lock)) {
            $this->reportWarn('composer.lock could not be read as JSON, so the installed WireKit was not compared against it.');

            return;
        }

        $locked = null;

        foreach ([...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])] as $package) {
            if (($package['name'] ?? null) === 'pushery/wirekit') {
                $locked = $package;
                break;
            }
        }

        if ($locked === null) {
            $this->reportInfo('pushery/wirekit is not in composer.lock — installed some other way, nothing to compare');

            return;
        }

        $lockedReference = $locked['source']['reference'] ?? null;

        if (! is_string($lockedReference) || $lockedReference === '') {
            // A path repository, and the ordinary case for this package's own sample app.
            $this->reportInfo('WireKit is installed from a path repository, so there is no commit to compare — installed-vs-locked NOT measured');

            return;
        }

        $installedReference = InstalledVersions::getReference('pushery/wirekit');

        if (! is_string($installedReference) || $installedReference === '') {
            $this->reportInfo('Composer reports no reference for the installed WireKit — installed-vs-locked NOT measured');

            return;
        }

        if ($installedReference === $lockedReference) {
            $this->reportPass('The installed WireKit is the commit composer.lock names');

            return;
        }

        $this->reportWarn(sprintf(
            'The WireKit in vendor/ is not the commit composer.lock names — locked %s, installed %s. '
            .'The lockfile moved and `composer install` has not run since, so reading the vendor source '
            .'will describe an older package than your project depends on. Run: composer install',
            substr($lockedReference, 0, 12),
            substr($installedReference, 0, 12)
        ));
    }
}
