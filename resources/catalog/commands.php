<?php

declare(strict_types=1);

/*
 * The Artisan commands the package registers when it runs in the console, in the order the
 * service provider hands them to Laravel. Read by WireKitServiceProvider::boot(); this file is
 * data and holds no logic. A new command class needs an entry here, and the test suite fails the
 * build when one is missing.
 */

use Pushery\WireKit\Console\BoostSkillsCommand;
use Pushery\WireKit\Console\ClassByAreaCommand;
use Pushery\WireKit\Console\ComponentMakeCommand;
use Pushery\WireKit\Console\CspAuditCommand;
use Pushery\WireKit\Console\CursorRulesCommand;
use Pushery\WireKit\Console\DoctorA11yCommand;
use Pushery\WireKit\Console\DoctorPropsCommand;
use Pushery\WireKit\Console\EditorPresetCommand;
use Pushery\WireKit\Console\ExportApiMapCommand;
use Pushery\WireKit\Console\ExportBlocksCommand;
use Pushery\WireKit\Console\ExportJsonCommand;
use Pushery\WireKit\Console\GlassInstallCommand;
use Pushery\WireKit\Console\InstallCommand;
use Pushery\WireKit\Console\ListComponentsCommand;
use Pushery\WireKit\Console\ListFontsCommand;
use Pushery\WireKit\Console\ListIconsCommand;
use Pushery\WireKit\Console\MakeCommand;
use Pushery\WireKit\Console\McpServeCommand;
use Pushery\WireKit\Console\PublishFontsCommand;
use Pushery\WireKit\Console\PublishIconsCommand;
use Pushery\WireKit\Console\ShowComponentCommand;
use Pushery\WireKit\Console\ThemeCommand;
use Pushery\WireKit\Console\VerifyInstallationCommand;

return [
    BoostSkillsCommand::class,
    ClassByAreaCommand::class,
    ComponentMakeCommand::class,
    CursorRulesCommand::class,
    DoctorA11yCommand::class,
    DoctorPropsCommand::class,
    EditorPresetCommand::class,
    ExportApiMapCommand::class,
    ExportBlocksCommand::class,
    CspAuditCommand::class,
    ExportJsonCommand::class,
    GlassInstallCommand::class,
    InstallCommand::class,
    ListComponentsCommand::class,
    ListFontsCommand::class,
    ListIconsCommand::class,
    MakeCommand::class,
    McpServeCommand::class,
    PublishFontsCommand::class,
    PublishIconsCommand::class,
    ShowComponentCommand::class,
    ThemeCommand::class,
    VerifyInstallationCommand::class,
];
