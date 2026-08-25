<?php

/**
 * Formr puts a submit button's text in a different argument slot than Flick does.
 *
 *   Formr: input_submit($name, $label, $value, $id, $string)  -- text is $value
 *   Flick: submit($text, $attributes)                         -- text is $text
 *
 * Renaming the method without moving the arguments produced
 * submit('send', '', 'Send'): a button labelled "send", plus a third argument
 * submit() does not accept. $name, $label and $id have no Flick equivalent
 * (submit() hard-codes name="submit" / id="submit"), so they are flagged rather
 * than dropped silently.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

describe('input_submit', function () {
    test('the button text moves from the third argument to the first', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', '', 'Send');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->submit('Send')")
            ->and($output)->not->toContain("->submit('submit'");
    });

    test('an omitted value falls back to submit() default', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit();
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('->submit()');
    });

    test('an empty value argument falls back to submit() default', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', '', '');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('->submit()');
    });

    test('the attribute string carries over as the second argument', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', '', 'Send', '', 'class="btn"');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('->submit(\'Send\', \'class="btn"\')');
    });

    test('a non-default name is flagged rather than dropped', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('go', '', 'Send');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->submit('Send')")
            ->and($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->toContain('go');
    });

    test('the default name is not flagged', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', '', 'Send');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->not->toContain('TODO: FLICK MIGRATION');
    });

    test('a label is flagged rather than dropped', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', 'Go now', 'Send');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->submit('Send')")
            ->and($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->toContain('Go now');
    });

    test('a non-default id is flagged rather than dropped', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', '', 'Send', 'sendBtn');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->submit('Send')")
            ->and($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->toContain('sendBtn');
    });

    test('a variable value argument is carried across, not stringified', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('submit', '', $buttonText);
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('->submit($buttonText)');
    });

    test('the array-call form is left alone', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit(['type' => 'submit', 'value' => 'Send']);
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('TODO: FLICK MIGRATION');
    });
});

describe('input_button_submit', function () {
    test('shares input_submit signature, so the text moves the same way', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_button_submit('submit', '', 'Send');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->submit('Send')")
            ->and($output)->not->toContain("->submit('submit'");
    });
});

describe('submit_button', function () {
    test('already takes the text first, so it converts unchanged', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->submit_button('Send');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->submit('Send')");
    });
});

describe('receiver scoping', function () {
    test('a non-Formr object with its own input_submit is left alone', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$widget = new SomethingElse();
echo $widget->input_submit('go', '', 'Send');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("\$widget->input_submit('go', '', 'Send')");
    });
});
