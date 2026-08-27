<?php

/**
 * A property assignment with no Flick equivalent is commented out, but a read
 * of the same property elsewhere survived untouched. Flick's __get throws for
 * an unknown name --
 *
 *   FlickException: The `success_message` service is not available.
 *
 * -- so the file fatals at the very line nobody was warned about.
 * freudenb/blog_system has three of these, each an
 * `echo "<p>".$form->success_message."</p>";` sitting right after the
 * assignment that was commented out.
 *
 * migrateNoEquivalentPropertyReads() already covered two shapes, both
 * whole-statement inline echoes. A read embedded in a larger expression
 * matched neither.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('a read inside a concatenation is flagged', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$form->success_message = "Saved!";
echo "<p>" . $form->success_message . "</p>";
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('// $form->success_message = "Saved!";')
        ->and($output)->toContain('this read will throw');
});

test('the assignment itself is not double-flagged', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$form->success_message = "Saved!";
echo "<p>" . $form->success_message . "</p>";
PHP;
    $output = $this->migrator->migrate($input);

    expect(substr_count($output, 'this read will throw'))->toBe(1);
});

test('a property that was never assigned is not flagged', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_text('a', 'A');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->not->toContain('this read will throw');
});

test('a read on an unrelated object is not flagged', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr();
$form->success_message = "Saved!";
echo $mailer->success_message;
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('echo $mailer->success_message;');
});

test('a comparison against the property is flagged too', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$form->success_message = "Saved!";
if ($form->success_message == "Saved!") { echo 'yes'; }
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('this read will throw');
});

test('the flag is not duplicated on a second run', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$form->success_message = "Saved!";
echo "<p>" . $form->success_message . "</p>";
PHP;
    $once = $this->migrator->migrate($input);

    expect($this->migrator->migrate($once))->toBe($once);
});

test('the flagged output still parses', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
$form->success_message = "Neuer Beitrag eingetragen!";
echo "<p style='color:lightgreen'>" . $form->success_message . "</p>";
$form->required = '*';
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0);
});

test('a multi-line property value is commented out in full', function () {
    // propertyValuePattern() spans newlines up to the closing semicolon, so a
    // match expression or multi-line array is captured whole. Prefixing a
    // single // left every continuation line live and the file stopped
    // parsing -- hit on oddevan/smolblog-wordpress once $this->form became a
    // recognised receiver.
    $input = <<<'PHP_SRC'
<?php
use Formr\Formr;

class Page
{
    private Formr $form;

    public function run(Exception $e)
    {
        $this->form->error_message = match (get_class($e)) {
            RuntimeException::class => 'boom',
            default => $e->getMessage(),
        };
    }
}
PHP_SRC;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('// $this->form->error_message = match')
        ->and($output)->toContain('// default => $e->getMessage(),');
});
