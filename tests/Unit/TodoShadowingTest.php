<?php

declare(strict_types=1);

use Flick\Migrate\FormrMigrator;

/*
|--------------------------------------------------------------------------
| A TODO on the line above must not shadow the next statement
|--------------------------------------------------------------------------
|
| isAlreadyMigrated() skipped any statement whose PREVIOUS line carried a
| migration TODO. That check exists for the formats that write a comment-only
| TODO line directly above a statement they leave LIVE - a Pro property, a
| flagged method call, a dropdown's options key - where nothing on the
| statement's own line marks it as handled.
|
| It also fired for a TODO sitting inline on a line of real code, which has
| nothing to do with the statement below it. The line under any such TODO was
| silently skipped by every pass that consults the check. It bit hardest with a
| non-literal constructor argument, because that always writes an inline TODO
| onto the constructor line, so line 3 of those files was always skipped.
|
| The check now requires the previous line to be comment-only. Every format
| that needs protection writes exactly that; an inline TODO never does.
|
*/

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

describe('a TODO above a line of real code does not shadow it', function () {
    test('an inline TODO on the previous line leaves the next property migrating', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $form->heading('Hi');
$form->upload_dir = '/uploads';
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'directory' => '/uploads'")
            ->and($output)->not->toContain("\$form->upload_dir = '/uploads';");
    });

    test('a non-literal constructor argument does not shadow the line below it', function () {
        // migrateConstructor() always writes an inline TODO onto this line, so
        // this is the case where the shadow was guaranteed rather than incidental
        $input = <<<'PHP'
<?php
$tpl = 'bootstrap';
$form = new Formr($tpl);
$form->upload_dir = '/uploads';
PHP;
        $output = $this->migrator->migrate($input);

        // The merge cannot land in a constructor whose argument is a variable,
        // so the property is commented out and flagged rather than folded into
        // the config - deliberate, so a failed merge cannot take the value with
        // it. What must NOT happen is what used to: the line left live and
        // unflagged on a Flick object, where it does nothing at all.
        expect($output)->toContain('review constructor argument(s)')
            ->and($output)->toContain('Add services.upload config to constructor')
            ->and($output)->toContain("// \$form->upload_dir = '/uploads';")
            ->and($output)->not->toMatch("/^\\s*\\\$form->upload_dir = '\\/uploads';/m");
    });

    test('a shadowed method call is still flagged', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $form->heading('Hi');
echo $form->heading('There');
PHP;
        $output = $this->migrator->migrate($input);

        // both calls carry a TODO, not just the first
        expect(substr_count($output, 'TODO: FLICK MIGRATION'))->toBe(2);
    });
});

describe('the formats that rely on the previous-line check still hold', function () {
    // Each of these writes a comment-only TODO line directly above a statement
    // it leaves LIVE. Running the migrator over its own output must not stack a
    // second TODO on top of the first.
    test('a pro property is flagged exactly once on a partially-migrated file', function () {
        // `new Formr` is still present, so the receivers resolve and the
        // property pass really does run over the already-flagged line
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
// TODO: FLICK MIGRATION - 'recaptcha_site_key' requires Flick Pro
$form->recaptcha_site_key = 'abc123';
PHP;
        $output = $this->migrator->migrate($input);

        expect(substr_count($output, 'requires Flick Pro'))->toBe(1);
    });

    test('a no-equivalent dropdown is flagged exactly once on a partially-migrated file', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->create([
    'fields' => [
        'state' => [
            /* TODO: FLICK MIGRATION - 'provinces': no Flick equivalent */
            'options' => 'provinces',
        ],
    ],
]);
PHP;
        $output = $this->migrator->migrate($input);

        expect(substr_count($output, "'provinces'"))->toBe(2);   // the TODO text and the key itself
    });

    test('migrating twice changes nothing', function (string $input) {
        $once = (new FormrMigrator)->migrate($input);
        $twice = (new FormrMigrator)->migrate($once);

        expect($twice)->toBe($once);
    })->with([
        'pro property' => [<<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->recaptcha_site_key = 'abc123';
PHP],
        'no-equivalent property' => [<<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->heading_tag = 'h2';
PHP],
        'flagged method call' => [<<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $form->heading('Hi');
$form->upload_dir = '/uploads';
PHP],
        'inline todo above a property' => [<<<'PHP'
<?php
$tpl = 'bootstrap';
$form = new Formr($tpl);
$form->upload_dir = '/uploads';
$form->recaptcha_site_key = 'abc';
PHP],
    ]);
});
