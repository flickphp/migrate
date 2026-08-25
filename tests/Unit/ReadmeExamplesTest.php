<?php

/**
 * The README's before/after pairs must be real migrator output.
 *
 * They are the first thing anyone evaluating the tool reads, and they drifted:
 * Example 1's "Before" block called button_submit(), which Formr does not have,
 * and the inline-syntax example showed a `required` the tool never adds. Prose
 * can go stale quietly; this makes the README fail the build instead.
 */

use Flick\Migrate\FormrMigrator;

it('reproduces every before/after pair in the README', function () {
    $readme = file_get_contents(__DIR__.'/../../README.md');

    expect($readme)->not->toBeFalse();

    // .gitattributes says `* text=auto`, so a Windows checkout has CRLF here
    // while the patterns below are written with \n. Left alone, the match found
    // nothing and the "not empty" guard on the next line was the only thing
    // that stopped this test passing vacuously.
    $readme = str_replace("\r\n", "\n", $readme);

    preg_match_all(
        '/\*\*Before \(Formr\):\*\*\s*```php\n(.*?)```.*?\*\*After \(Flick\):\*\*\s*```php\n(.*?)```/s',
        $readme,
        $pairs,
        PREG_SET_ORDER
    );

    // A parser that silently finds nothing would make this test vacuous.
    expect($pairs)->not->toBeEmpty();

    $migrator = new FormrMigrator;

    foreach ($pairs as $index => $pair) {
        $actual = rtrim($migrator->migrate(rtrim($pair[1], "\n")), "\n");

        expect($actual)->toBe(
            rtrim($pair[2], "\n"),
            'README before/after pair #'.($index + 1).' no longer matches migrator output'
        );
    }
});

it('does not document a Formr method that Formr lacks', function () {
    $readme = file_get_contents(__DIR__.'/../../README.md');

    // button_submit() was documented for months as an unmapped Formr method to
    // migrate by hand. Formr has submit_button(), input_submit() and
    // input_button_submit() - all three mapped. There is no button_submit().
    // The lookbehind matters: input_button_submit() ends with the same letters.
    expect($readme)->not->toMatch('/(?<!input_)button_submit/');
});
