<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        'nullable_type_declaration' => ['syntax' => 'union'],
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'all',
            'strict' => true,
        ],
    ])
    ->setCacheFile('./runtime/php-cs-fixer.cache')
    ->setFinder(
        new Finder()
            // 💡 root folder to check
            // 💡 additional files, eg bin entry file
            // ->append([__DIR__.'/bin-entry-file'])
            // 💡 folders to exclude, if any
            // ->exclude([/* ... */])
            // 💡 path patterns to exclude, if any
            // ->notPath([/* ... */])
            // 💡 extra configs
            // ->ignoreDotFiles(false) // true by default in v3, false in v4 or future mode
            // ->ignoreVCS(true) // true by default
            ->in(__DIR__)
            ->exclude('runtime')
            ->append([__FILE__]),
    )
;
