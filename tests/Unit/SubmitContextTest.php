<?php

/**
 * Two defects around submit(), fixed together because the second depends on
 * the first.
 *
 * ORDERING. migrate() ran migrateSubmitMethods() -- which rewrites
 * input_submit( to submit( -- before migrateMethods(), which then applied its
 * submit-to-submitted context regexes over text that now contained freshly
 * created buttons. One of those regexes matches a return context, so
 *
 *   return $form->input_submit('submit', '', 'Send');
 *
 * became `return $form->submitted('Send');` -- a submission check where a
 * button belonged. submit_button escaped only because it lives in the method
 * map, which is applied after those regexes. The comment on the block already
 * claimed the premise this violated: "in original Formr code every ->submit(
 * is the submission check (buttons are input_submit/submit_button, not yet
 * renamed)". Running the check conversion first restores it.
 *
 * REMAINDER. The six conversions cover if/elseif/while, negation, both sides
 * of a boolean operator, ternary and return -- broader than the README says,
 * but not exhaustive. An assignment or a bare statement is not a
 * value-consuming condition, so it was left silently, and Flick's submit()
 * renders a button and is always truthy.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('a returned submit button stays a button', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
return $form->input_submit('submit', '', 'Send');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("return \$form->submit('Send')")
        ->and($output)->not->toContain('submitted');
});

test('a returned submit_button stays a button', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
return $form->submit_button('Send');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("return \$form->submit('Send')")
        ->and($output)->not->toContain('submitted');
});

test('a returned submission check still becomes submitted()', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
return $form->submit();
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('return $form->submitted()');
});

test('submit() inside a condition still becomes submitted()', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
if ($form->submit()) { echo 'posted'; }
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('$form->submitted()')
        ->and($output)->not->toContain('TODO: FLICK MIGRATION');
});

test('a negated submit() still becomes submitted()', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
if (!$form->submit()) { echo 'no'; }
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('!$form->submitted()')
        ->and($output)->not->toContain('TODO: FLICK MIGRATION');
});

test('submit() assigned to a variable is flagged', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$posted = $form->submit();
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('TODO: FLICK MIGRATION')
        ->and($output)->toContain('Use submitted() if you meant the check');
});

test('a bare submit() statement is flagged', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$form->submit();
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('TODO: FLICK MIGRATION');
});

test('a converted button is never flagged', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', '', 'Send');
echo $form->submit_button('Save');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("->submit('Send')")
        ->and($output)->toContain("->submit('Save')")
        ->and($output)->not->toContain('TODO: FLICK MIGRATION');
});

test('submit() on an unrelated object is untouched', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr();
$job->submit();
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('$job->submit();')
        ->and($output)->not->toContain('TODO: FLICK MIGRATION');
});

test('the flag is not duplicated on a second run', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$posted = $form->submit();
PHP;
    $once = $this->migrator->migrate($input);

    expect($this->migrator->migrate($once))->toBe($once);
});
