<?php

/**
 * Formr can be constructed under four spellings. All four must reach Flick's
 * array config; the leading-backslash form was silently left with Formr's
 * positional arguments, swallowing 'hush' and re-enabling echo mode.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('a leading-backslash constructor converts its arguments to array config', function () {
    $input = <<<'PHP'
<?php
$form = new \Formr\Formr('bootstrap5', 'hush');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("'views' => 'bootstrap5'")
        ->and($output)->toContain("'echo' => false")
        ->and($output)->not->toContain("'hush'");
});

test('a leading-backslash constructor with no arguments still converts', function () {
    $input = <<<'PHP'
<?php
$form = new \Formr\Formr();
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('new \Flick\Flick()')
        ->and($output)->not->toContain('Formr');
});

test('a qualified constructor with no import stays qualified', function () {
    $input = <<<'PHP'
<?php
$form = new Formr\Formr();
echo $form->input_text('name', 'Name');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('new \Flick\Flick()')
        ->and($output)->not->toMatch('/new Flick\(\)/');
});

test('a qualified constructor with arguments stays qualified', function () {
    $input = <<<'PHP'
<?php
$form = new Formr\Formr('bulma');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("new \Flick\Flick(['views' => 'bulma'])");
});

test('a leading-backslash constructor with arguments stays qualified', function () {
    $input = <<<'PHP'
<?php
$form = new \Formr\Formr('bootstrap5', 'hush');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("new \Flick\Flick(['views' => 'bootstrap5', 'echo' => false])");
});

test('an unqualified constructor under a use statement stays unqualified', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr('bootstrap');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('use Flick\Flick;')
        ->and($output)->toContain("new Flick(['views' => 'bootstrap'])")
        ->and($output)->not->toContain('new \Flick\Flick');
});
