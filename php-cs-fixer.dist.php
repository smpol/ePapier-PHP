<?php declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRules(
        [
            '@Symfony' => true
        ]
    )->setFinder(
        (new Finder())
            ->in(__DIR__)
            ->exclude('var')
            ->exclude('vendor')

    );
