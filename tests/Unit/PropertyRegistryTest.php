<?php

declare(strict_types=1);

use Flick\Migrate\FormrMigrator;

/*
 * Audit 2026-08-16, M1-B — property migration classifies every `$form->prop =`
 * assignment by name-membership in five separately-declared registries
 * (propertyMap, proProperties, noEquivalentProperties, uploadPropertyMap,
 * uploadPerUploadProperties). Correctness relies on no property appearing in
 * two registries — an invariant that was never checked anywhere. This pins it:
 * a property added to a second list would classify differently depending on
 * which pass saw it first, silently.
 */

it('never lists a property in more than one classification registry', function () {
    $reflection = new ReflectionClass(FormrMigrator::class);
    $migrator = new FormrMigrator;

    $registries = [
        'propertyMap' => array_keys($reflection->getProperty('propertyMap')->getValue($migrator)),
        'proProperties' => array_values($reflection->getProperty('proProperties')->getValue($migrator)),
        'noEquivalentProperties' => array_values($reflection->getProperty('noEquivalentProperties')->getValue($migrator)),
        'uploadPropertyMap' => array_keys($reflection->getProperty('uploadPropertyMap')->getValue($migrator)),
        'uploadPerUploadProperties' => array_keys($reflection->getProperty('uploadPerUploadProperties')->getValue($migrator)),
    ];

    $seen = [];
    foreach ($registries as $registry => $names) {
        foreach ($names as $name) {
            if (isset($seen[$name])) {
                $this->fail("'{$name}' is in both {$seen[$name]} and {$registry} - classification would depend on pass order");
            }
            $seen[$name] = $registry;
        }
    }

    expect(count($seen))->toBeGreaterThan(20);
});
