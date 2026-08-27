<?php

/**
 * open() exists in both libraries with incompatible signatures:
 *
 *   Formr: open($name, $id, $action, $method, $string, $hidden)
 *   Flick: open($action, $method, $attributes)
 *
 * form_open() carries Formr's signature too, and the method map renames it to
 * open() without touching the arguments. Either way Formr's action landed in
 * Flick's attributes slot and the form rendered as
 *
 *   <form action="/" method="" id="myForm" index.php?q=admin>
 *
 * -- wrong target, empty method (which browsers treat as GET), and the URL
 * emitted as a bare attribute.
 *
 * form_open() is Formr-only, so any argument count is unambiguously Formr's.
 * A bare open() is ambiguous below four arguments, because Flick's own
 * three-argument shape is already correct, so those are left alone.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('the action moves from the third argument to the first', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->open('', '', 'index.php?q=admin', '', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("->open('index.php?q=admin')");
});

test('form_open carries the same reshaping', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->form_open('f', 'f', 'index.php?q=admin', '', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("->open('index.php?q=admin'");
});

test('form_open reshapes below four arguments too', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->form_open('myform', '', '/save');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("->open('/save'");
});

test('an explicit method is carried into the second argument', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->open('', '', '/save', 'POST', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("->open('/save', 'POST')");
});

test('an attribute string is carried into the third argument', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->open('', '', '/save', 'POST', 'class="row"');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('class="row"');
});

test('a name or id that cannot be carried is flagged, not dropped', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->open('myform', 'myform', '/save', '', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('TODO: FLICK MIGRATION')
        ->and($output)->toContain('myform');
});

test('a Flick-shaped open() call is left alone', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->open('/save', 'POST');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("->open('/save', 'POST')")
        ->and($output)->not->toContain('TODO: FLICK MIGRATION');
});

test('a no-argument form_open is left alone', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->form_open();
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('->open()')
        ->and($output)->not->toContain('TODO: FLICK MIGRATION');
});

test('open() on an unrelated object is untouched', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr();
$zip->open('archive.zip', '', 'x', '', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("\$zip->open('archive.zip', '', 'x', '', '')");
});

test('the rewrite is idempotent', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->open('', '', '/save', '', '');
PHP;
    $once = $this->migrator->migrate($input);
    $twice = $this->migrator->migrate($once);

    expect($twice)->toBe($once);
});

test('the reshaped form renders the right action and method through Flick', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->open('', '', '/save', 'POST', '');
PHP;
    $output = $this->migrator->migrate($input);

    $body = preg_replace('/^<\?php\s*/', '', $output);
    $body = preg_replace('/\$form = new [^;]+;/', '', $body);

    $rendered = renderThroughFlick($body);
    if ($rendered === null) {
        expect(true)->toBeTrue();  // sibling flick package absent

        return;
    }

    expect($rendered)->toContain('action="/save"')
        ->and($rendered)->toContain('method="POST"')
        ->and($rendered)->not->toContain('method=""');
});
