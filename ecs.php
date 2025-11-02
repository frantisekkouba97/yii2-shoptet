<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/assets',
        __DIR__ . '/commands',
        __DIR__ . '/components',
        __DIR__ . '/config',
        __DIR__ . '/controllers',
        __DIR__ . '/mail',
        __DIR__ . '/models',
        __DIR__ . '/tests',
        __DIR__ . '/views',
        __DIR__ . '/web',
        __DIR__ . '/widgets',
    ])

    // add a single rule
    ->withRules([
        NoUnusedImportsFixer::class,
    ])
    ->withSets([
        SetList::PSR_12,
        SetList::CLEAN_CODE,
        SetList::COMMON,
        SetList::SPACES,
        SetList::SYMPLIFY,
    ]);

    // add sets - group of rules, from easiest to more complex ones
    // uncomment one, apply one, commit, PR, merge and repeat
    //->withPreparedSets(
    //      spaces: true,
    //      namespaces: true,
    //      docblocks: true,
    //      arrays: true,
    //      comments: true,
    //)

