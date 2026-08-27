<?php

/**
 * A file whose form instance is built elsewhere never names the Formr class,
 * so the CLI's structural gate skipped it before migrate() could flag it.
 * H-Software/isp-net-adminator finished with 31 un-migrated Formr calls
 * across four files, zero TODOs, and a report reading
 *
 *   Files processed: 1 / Files modified: 1 / Migration complete!
 *
 * while the one file it did rewrite turned the shared instance into a Flick,
 * guaranteeing "Call to undefined method" at every one of those call sites.
 *
 * The gate keys on DISTINCTIVE Formr names only. Generic names -- get, post,
 * validate, ok, submitted, text, select, open, close, messages -- are
 * excluded deliberately: siktec-lab/bsik-core declares formr/formr, never
 * uses it, and has 22 ->get( calls on unrelated objects. It must stay at zero
 * flags, which is the whole reason the tool's precision is worth keeping.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('a distinctive Formr call with no class reference is recognised', function () {
    $content = <<<'PHP'
<?php
class PartnerPage
{
    public function render()
    {
        $this->action_form = $this->formInit();

        return $this->action_form->input_submit('odeslat', '', 'OK');
    }
}
PHP;

    expect($this->migrator->hasFormrClassReference($content))->toBeFalse()
        ->and($this->migrator->usesDistinctiveFormrApi($content))->toBeTrue();
});

test('generic method names on unrelated objects are not recognised', function () {
    $content = <<<'PHP'
<?php
class Cart
{
    public function run()
    {
        $data = $this->session->get('cart', 'default');
        $ok = $this->validator->validate($data);
        $this->form->messages();
        $this->response->open('x');

        return $this->response->ok();
    }
}
PHP;

    expect($this->migrator->usesDistinctiveFormrApi($content))->toBeFalse();
});

test('a property assignment of a distinctive name counts', function () {
    $content = <<<'PHP'
<?php
$page->form->error_message = 'nope';
PHP;

    expect($this->migrator->usesDistinctiveFormrApi($content))->toBeTrue();
});

test('a flagged file gains the TODO and keeps its code untouched', function () {
    $content = <<<'PHP'
<?php
class PartnerPage
{
    public function render()
    {
        return $this->action_form->input_submit('odeslat', '', 'OK');
    }
}
PHP;

    $output = $this->migrator->flagExternalUsageOnly($content);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('created elsewhere, so its method calls were not migrated automatically')
        ->and($output)->toContain("input_submit('odeslat', '', 'OK')");
});

test('flagging reports a todo for the summary', function () {
    $content = <<<'PHP'
<?php
$page->action_form->input_submit('a', '', 'OK');
PHP;

    $this->migrator->flagExternalUsageOnly($content);

    expect($this->migrator->getTodos())->not->toBeEmpty();
});

test('flagging is idempotent', function () {
    $content = <<<'PHP'
<?php
$page->action_form->input_submit('a', '', 'OK');
PHP;
    $once = $this->migrator->flagExternalUsageOnly($content);

    expect($this->migrator->flagExternalUsageOnly($once))->toBe($once);
});

test('a property-held receiver is seen by the usage check', function () {
    // hasLikelyFormrUsage() was anchored on \$\w+-> and so missed $this->form->
    $content = <<<'PHP'
<?php
$this->form->create_form('Name, Email');
PHP;

    expect($this->migrator->usesDistinctiveFormrApi($content))->toBeTrue();
});
