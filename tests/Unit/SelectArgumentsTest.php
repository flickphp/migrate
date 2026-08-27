<?php

/**
 * The three literal-argument select patterns require arguments 1-7 to be
 * string literals. Real code passes expressions there, so such a call matched
 * none of them and fell through to the plain rename, handing eight positional
 * arguments to a four-parameter method. Flick then read the empty argument 4
 * as a dropdown loader name and threw:
 *
 *   InvalidArgumentException: Flick loader names may only contain letters,
 *   numbers, underscores and hyphens; got ""
 *
 * Formr:  input_select($name,$label,$value,$id,$string,$inline,$selected,$options)
 * Flick:  select($name,$label,$selected,['options' => $options])
 *
 * Both $value (argument 3) and $selected (argument 7) pre-select an option in
 * Formr -- verified against Formr 1.3.1, whose _create_select() runs
 * array_key_exists($selected, $options) and also honours $value. Argument 3
 * wins when it carries something, otherwise argument 7 does.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('expression arguments are folded into Flick shape', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_select('vaccine_type', 'Vaccine Type', '', '', '', '', $prefs->vaccine_type, VaccineTypes::getSelectorArray());
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('$prefs->vaccine_type')
        ->and($output)->toContain("'options' => VaccineTypes::getSelectorArray()");
});

test('the selected key survives when the value argument is empty', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_select('vt', 'Vaccine', '', '', '', '', $chosen, $opts);
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("select('vt', 'Vaccine', \$chosen");
});

test('a non-empty value argument wins over selected', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_select('vt', 'Vaccine', $current, '', '', '', $chosen, $opts);
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("select('vt', 'Vaccine', \$current");
});

test('an id that differs from the field name is carried into attributes', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_select('vt', 'Vaccine', '', 'custom-id', '', '', $chosen, $opts);
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("'id' => 'custom-id'");
});

test('select() on an unrelated object is untouched', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr();
$rows = $query->select('id', 'name', 'x', '', '', '', $a, $b);
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("\$query->select('id', 'name', 'x', '', '', '', \$a, \$b)");
});

test('an already-migrated four-argument select is left alone', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->select('vt', 'Vaccine', $chosen, ['options' => $opts]);
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("select('vt', 'Vaccine', \$chosen, ['options' => \$opts])");
});

test('the rewrite is idempotent', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_select('vt', 'Vaccine', '', '', '', '', $chosen, $opts);
PHP;
    $once = $this->migrator->migrate($input);

    expect($this->migrator->migrate($once))->toBe($once);
});

test('the migrated select renders its options and selection through Flick', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_select('vt', 'Vaccine', '', '', '', '', $chosen, $opts);
PHP;
    $output = $this->migrator->migrate($input);

    $body = "\$opts = ['pfizer' => 'Pfizer', 'moderna' => 'Moderna'];\n"
        ."\$chosen = 'moderna';\n"
        .preg_replace('/^<\?php\s*/', '', $output);
    $body = preg_replace('/\$form = new [^;]+;/', '', $body);

    $rendered = renderThroughFlick($body);
    if ($rendered === null) {
        expect(true)->toBeTrue();  // sibling flick package absent

        return;
    }

    expect($rendered)->toContain('<option')
        ->and($rendered)->toContain('selected')
        ->and($rendered)->not->toContain('loader names may only contain');
});
