<?php

/**
 * One case per shape that failed when v1.0.0 was run against real
 * third-party Formr code. Synthetic reconstructions of the structure, not
 * copied source.
 *
 * Four of six representative migrated forms fatalled on render before these
 * fixes; the fifth silently posted to the wrong URL with the wrong method,
 * and the sixth turned a submit button into a submission check.
 *
 * See claude/plans/2026-08-26-migrate-fidelity-findings.md
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('blog_system shape: qualified constructor with no import, Formr-style open', function () {
    $input = <<<'PHP'
<?php
require_once 'formr/class.formr.php';
$aform = new Formr\Formr();
$aform->open('', '', 'index.php?q=admin', '', '');
$aform->create_form('Title, Autor, Description, Text|textarea');
$aform->success_message = "Saved!";
echo "<p>" . $aform->success_message . "</p>";
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        // was: new Flick() -> Class "Flick" not found in the global namespace
        ->and($output)->toContain('new \Flick\Flick()')
        // was: <form action="/" method="" id="myForm" index.php?q=admin>
        ->and($output)->toContain("->open('index.php?q=admin')")
        // was: the read survived its commented-out assignment and threw
        ->and($output)->toContain('this read will throw');
});

test('VaccineNotifier shape: select and field with expression arguments', function () {
    $input = <<<'PHP'
<?php
use Formr\Formr;
$locationForm = new Formr('bootstrap');
echo $locationForm->input_select('vaccine_type', 'Vaccine Type', '', '', '', '', $prefs->vaccine_type, VaccineTypes::getSelectorArray());
echo $locationForm->input_email('email', 'Email', $user->email, '', 'disabled');
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        // was: eight positional args -> "loader names may only contain..."
        ->and($output)->toContain("'options' => VaccineTypes::getSelectorArray()")
        // was: the saved selection in argument 7 was dropped
        ->and($output)->toContain('$prefs->vaccine_type')
        // was: 'disabled' silently discarded by PHP
        ->and($output)->toContain("'disabled' => true");
});

test('isp-net-adminator shape: leading-backslash constructor with hush', function () {
    $input = <<<'PHP'
<?php
class Adminator
{
    public function formInit()
    {
        return new \Formr\Formr('bootstrap5', 'hush');
    }
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        // was: new \Flick\Flick('bootstrap5', 'hush') -- 'hush' swallowed,
        // echo mode silently back on
        ->and($output)->toContain("'echo' => false")
        ->and($output)->not->toContain("'hush'");
});

test('isp-net-adminator shape: a file whose form is built elsewhere is flagged', function () {
    $input = <<<'PHP'
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

    expect($this->migrator->hasFormrClassReference($input))->toBeFalse()
        ->and($this->migrator->usesDistinctiveFormrApi($input))->toBeTrue();

    $output = $this->migrator->flagExternalUsageOnly($input);

    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain('created elsewhere');
});

test('smolblog shape: form held in a promoted property', function () {
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
        if ($this->form->submitted()) {
            $this->get($key);
        }
        echo $this->form->input_text('a', 'A');
    }
}
PHP;
    $output = $this->migrator->migrate($input);

    expect(lintPhp($output))->toBe(0)
        // was: untouched, so it called a Formr method on a Flick object
        ->and($output)->toContain("\$this->form->text('a', 'A')")
        // the class's own get() must survive - it is not Formr's
        ->and($output)->toContain('public function get(string $page)')
        ->and($output)->toContain('$this->get($key);');
});

test('a returned submit button is not turned into a submission check', function () {
    $input = <<<'PHP'
<?php
$form = new Formr();
return $form->input_submit('submit', '', 'Send');
PHP;
    $output = $this->migrator->migrate($input);

    // was: return $form->submitted('Send'); -- the button gone, replaced by a
    // check. The only defect found that produced wrong working code rather
    // than a fatal.
    expect(lintPhp($output))->toBe(0)
        ->and($output)->toContain("return \$form->submit('Send')")
        ->and($output)->not->toContain('submitted');
});

test('bsik-core shape: generic names on unrelated objects stay untouched', function () {
    $input = <<<'PHP'
<?php
class Settings
{
    public function load()
    {
        $v = $this->session->get('cart', 'default');
        $this->validator->validate($v);

        return $this->response->ok();
    }
}
PHP;

    expect($this->migrator->hasFormrClassReference($input))->toBeFalse()
        ->and($this->migrator->usesDistinctiveFormrApi($input))->toBeFalse()
        ->and($this->migrator->definesFormrClass($input))->toBeFalse();
});

test('a hand-vendored Formr library is left alone', function () {
    $input = <<<'PHP'
<?php

namespace Formr;

class Formr
{
    public function input_text($data, $label = '') { return ''; }
}
PHP;

    expect($this->migrator->definesFormrClass($input))->toBeTrue();
});

test('a sibling of the vendored library is recognised as library source', function () {
    // blog_system vendors Formr under formr/, and its wrapper traits call
    // $this->formr->in_errors(...). They are Formr's own source, not the
    // developer's, so the external-usage gate must not annotate them -- but
    // they declare `trait Bootstrap`, not `class Formr`, so the CLI decides
    // by directory. This pins the two halves the CLI combines.
    $wrapper = <<<'PHP_SRC'
<?php

trait Bootstrap
{
    public static function render($data)
    {
        if ($this->formr->in_errors($data['name'])) {
            return 'is-invalid';
        }
    }
}
PHP_SRC;

    // Not the class declaration itself...
    expect($this->migrator->definesFormrClass($wrapper))->toBeFalse()
        // ...but it does use the API, which is why the directory check exists.
        ->and($this->migrator->usesDistinctiveFormrApi($wrapper))->toBeTrue();
});
