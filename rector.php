<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/routes',
    ])
    ->withSkip([
        __DIR__.'/app/Console/Commands/RestaurarEstadoCeroCommand.php',
        __DIR__.'/app/Console/Commands/BackupDatabaseCommand.php',
    ])
    ->withPhpSets(php83: true)
    ->withComposerBased(laravel: true);
