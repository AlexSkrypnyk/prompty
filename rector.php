<?php

/**
 * @file
 * Rector configuration.
 *
 * Usage:
 * ./vendor/bin/rector process .
 */

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
  // Filtered by existence because the embed tests copy this config into a
  // workspace holding only the file under test, and Rector aborts when a
  // configured path is missing.
  ->withPaths(array_filter([
    __DIR__ . '/Prompty.php',
    __DIR__ . '/PromptyTestTrait.php',
    __DIR__ . '/playground',
    __DIR__ . '/embed.php',
    __DIR__ . '/starter.php',
    __DIR__ . '/tests/phpunit',
    __DIR__ . '/rector.php',
  ], file_exists(...)))
  ->withPhpSets(php82: TRUE)
  ->withPreparedSets(
    deadCode: TRUE,
    codeQuality: TRUE,
    codingStyle: TRUE,
    typeDeclarations: TRUE,
    naming: TRUE,
    instanceOf: TRUE,
    earlyReturn: TRUE,
    phpunitCodeQuality: TRUE,
  )
  ->withComposerBased(phpunit: TRUE)
  ->withRules([
    DeclareStrictTypesRector::class,
  ])
  ->withSkip([
    // Rules added by Rector's rule sets.
    CatchExceptionNameMatchingTypeRector::class,
    ChangeSwitchToMatchRector::class,
    CompleteDynamicPropertiesRector::class,
    InlineArrayReturnAssignRector::class,
    NewlineAfterStatementRector::class,
    NewlineBeforeNewAssignSetRector::class,
    NewlineBetweenClassLikeStmtsRector::class,
    RemoveAlwaysTrueIfConditionRector::class,
    RenameForeachValueVariableToMatchMethodCallReturnTypeRector::class,
    RenameVariableToMatchMethodCallReturnTypeRector::class,
    RenameVariableToMatchNewTypeRector::class,
    SimplifyEmptyCheckOnEmptyArrayRector::class,
    // Dependencies.
    '*/vendor/*',
    '*/node_modules/*',
    // Built on demand by embed.php and not committed.
    __DIR__ . '/playground/flow-embed.dist.php',
  ])
  ->withFileExtensions([
    'php',
    'inc',
  ])
  ->withImportNames(importNames: TRUE, importDocBlockNames: FALSE, importShortClasses: FALSE);
