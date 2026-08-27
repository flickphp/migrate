<?php

/**
 * A form held in a property is the normal shape in class-based code, and it
 * was the one shape nothing converted.
 *
 * Two causes. findFormrVariables() read the type hint on a promoted property
 * -- `private Formr $form` -- as the receiver `$form`, which appears nowhere
 * in the file, so `external` stayed false and every scoped pass matched
 * nothing. And replaceBalancedCall() scanned back over the identifier and
 * required a `$` immediately before it, so `$this->form->method()` was
 * rejected outright.
 *
 * Meanwhile the type hint itself migrated, leaving Formr calls on an object
 * that is now a Flick:
 *
 *   Error: Call to undefined method Flick\Flick::input_text()
 *
 * oddevan/smolblog-wordpress kept four live Formr usages after a run the
 * migrator reported as successful.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('calls on a promoted Formr property are migrated', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;

class Page
{
    public function __construct(private Formr $form) {}

    public function render()
    {
        return $this->form->input_text('a', 'A');
    }
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('private Flick $form')
        ->and($output)->toContain("\$this->form->text('a', 'A')")
        ->and($output)->not->toContain('input_text');
});

test('calls on a declared Formr property are migrated', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;

class Page
{
    private Formr $form;

    public function render()
    {
        echo $this->form->form_open();
        echo $this->form->input_email('e', 'E');
        echo $this->form->form_close();
    }
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('$this->form->open()')
        ->and($output)->toContain("\$this->form->email('e', 'E')")
        ->and($output)->toContain('$this->form->close()');
});

test('a plain parameter receiver still works', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
function render(Formr $form)
{
    return $form->input_text('a', 'A');
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("\$form->text('a', 'A')");
});

test('an unrelated property with a colliding method name is untouched', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;

class Page
{
    public function __construct(private Formr $form) {}

    public function run()
    {
        $this->logger->info('called');
        $this->registry->get('key');

        return $this->form->input_text('a', 'A');
    }
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("\$this->logger->info('called')")
        ->and($output)->toContain("\$this->registry->get('key')")
        ->and($output)->toContain("\$this->form->text('a', 'A')");
});

test('the class own method that shares a Formr name is untouched', function () {
    // smolblog's AdminPageRegistry declares get() and calls $this->get($key).
    $input = <<<'PHP'
<?php
use Formr\Formr;

class AdminPageRegistry
{
    public function __construct(private Formr $form) {}

    public function get(string $page): string
    {
        return $page;
    }

    public function showPage(string $key): void
    {
        $this->get($key);
        echo $this->form->input_text('a', 'A');
    }
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('public function get(string $page)')
        ->and($output)->toContain('$this->get($key);')
        ->and($output)->toContain("\$this->form->text('a', 'A')");
});

test('a deeper chain is not guessed at', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr();
$a->b->c->input_text('x', 'X');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("\$a->b->c->input_text('x', 'X')");
});

test('validation rules convert on a property receiver', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;

class Page
{
    private Formr $form;

    public function run()
    {
        return $this->form->post('email', 'Email', 'required|valid_email');
    }
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("\$this->form->request('email', 'required, email')");
});

test('the rewrite is idempotent', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;

class Page
{
    public function __construct(private Formr $form) {}

    public function render()
    {
        return $this->form->input_text('a', 'A');
    }
}
PHP;
    $once = $this->migrator->migrate($input);

    expect($this->migrator->migrate($once))->toBe($once);
});
