<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

/**
 * Rector en modo conservador: mantiene el código al nivel de PHP 8.1 (el mínimo declarado en composer.json) y aplica
 * limpiezas de código muerto y tipos. NO sube la versión de PHP ni de Laravel.
 * Uso: `composer rector` (solo muestra los cambios) / `composer rector:fix` (los aplica; revisar el diff antes de confirmar).
 */
return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/routes', __DIR__.'/database'])
    ->withSkip([__DIR__.'/database/migrations', __DIR__.'/tests/Browser'])
    ->withSets([LevelSetList::UP_TO_PHP_81])
    ->withPreparedSets(deadCode: true, typeDeclarations: false, earlyReturn: false);
