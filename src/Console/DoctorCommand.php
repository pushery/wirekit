<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

/**
 * `wirekit:doctor` command alias.
 *
 * Same diagnostic checks as `wirekit:verify` under a
 * more conventional Laravel-ecosystem name (`php artisan {package}:doctor`
 * is the de-facto standard for diagnostic commands; `:verify` was the
 * original choice but readers expect `:doctor`).
 *
 * Both commands stay registered in parallel for back-compat — existing
 * CI scripts and docs that reference `wirekit:verify` keep working.
 *
 * Implementation: extends VerifyInstallationCommand and overrides only
 * the signature so every check (asset publishing, freshness, Tailwind
 *
 * @source, Blade directives, Alpine, bundle config, view staleness,
 * fonts, CSS @import anti-pattern, optional deps) runs identically.
 */
class DoctorCommand extends VerifyInstallationCommand
{
    protected $signature = 'wirekit:doctor';

    protected $description = 'Diagnose WireKit integration health (alias for wirekit:verify)';
}
