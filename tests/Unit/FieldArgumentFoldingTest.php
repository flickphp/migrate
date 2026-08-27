<?php

/**
 * migrateTextInputMethods() folds Formr's id + attribute arguments into
 * Flick's 4th-argument array, but its patterns require every argument to be a
 * string literal. Real code puts an expression in the value slot, so
 *
 *   input_email('email', 'Email', $user->email, '', 'disabled')
 *
 * matched nothing, fell through to the plain rename, and reached Flick as a
 * five-argument call. PHP discards the extras on a userland method without a
 * word, so the field rendered quietly not disabled.
 *
 * Same root cause as the select fallback: fold the call rather than flag it.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('an attribute survives when the value argument is an expression', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_email('email', 'Email', $user->email, '', 'disabled');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("'disabled' => true")
        ->and($output)->toContain('$user->email');
});

test('a class attribute survives an expression value', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_text('lat', 'Latitude', $prefs->latitude, '', 'class="form-control-lg"');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("'class' => 'form-control-lg'");
});

test('a custom id survives an expression value', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_text('lat', 'Latitude', $prefs->latitude, 'custom-id', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("'id' => 'custom-id'");
});

test('an id equal to the field name is not repeated', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_text('lat', 'Latitude', $prefs->latitude, 'lat', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("text('lat', 'Latitude', \$prefs->latitude)");
});

test('a three-argument call is left as is', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_text('latitude', 'Latitude', $prefs->latitude);
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("text('latitude', 'Latitude', \$prefs->latitude)")
        ->and($output)->not->toContain('[]');
});

test('trailing blank arguments fold away entirely', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_text('lat', 'Latitude', $v, '', '');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("text('lat', 'Latitude', \$v)");
});

test('a field call on an unrelated object is untouched', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr();
$pdf->text('hello', 'x', $y, '', 'bold');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("\$pdf->text('hello', 'x', \$y, '', 'bold')");
});

test('the rewrite is idempotent', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_email('email', 'Email', $user->email, '', 'disabled');
PHP;
    $once = $this->migrator->migrate($input);

    expect($this->migrator->migrate($once))->toBe($once);
});

test('the folded field renders the attribute through Flick', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_email('email', 'Email', $addr, '', 'disabled');
PHP;
    $output = $this->migrator->migrate($input);

    $body = "\$addr = 'a@b.c';\n".preg_replace('/^<\?php\s*/', '', $output);
    $body = preg_replace('/\$form = new [^;]+;/', '', $body);

    $rendered = renderThroughFlick($body);
    if ($rendered === null) {
        expect(true)->toBeTrue();  // sibling flick package absent

        return;
    }

    expect($rendered)->toContain('disabled');
});
