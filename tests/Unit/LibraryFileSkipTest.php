<?php

/**
 * Formr vendored by hand outside vendor/ was itself migrated: `namespace
 * Formr;` became `namespace Flick;` and `class Formr` became `class Flick`,
 * producing a second Flick\Flick that collides with the real package and
 * destroying the library in the process.
 *
 * vendor/ is already excluded by the CLI's file walk, so this only bites the
 * non-standard vendoring that freudenb/blog_system does -- a formr/ directory
 * sitting beside the application code.
 *
 * A file that DEFINES the class is the library, not a consumer of it.
 */

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

test('a file that defines the Formr class is recognised as the library', function () {
    $library = <<<'PHP'
<?php

namespace Formr;

class Formr
{
    public function input_text($data, $label = '') { return ''; }
}
PHP;

    expect($this->migrator->definesFormrClass($library))->toBeTrue();
});

test('a file that merely consumes Formr is not the library', function () {
    $consumer = <<<'PHP'
<?php
use Formr\Formr;
$form = new Formr('bootstrap');
echo $form->input_text('name', 'Name');
PHP;

    expect($this->migrator->definesFormrClass($consumer))->toBeFalse();
});

test('a class whose name merely starts with Formr is not the library', function () {
    $other = <<<'PHP'
<?php
namespace App;
class FormrHelper
{
    public function build() { return ''; }
}
PHP;

    expect($this->migrator->definesFormrClass($other))->toBeFalse();
});

test('a final or abstract declaration still counts', function () {
    expect($this->migrator->definesFormrClass("<?php\nfinal class Formr {}"))->toBeTrue()
        ->and($this->migrator->definesFormrClass("<?php\nabstract class Formr {}"))->toBeTrue();
});

test('the real library shape is recognised', function () {
    // The opening of Formr 1.3.1 as vendored by freudenb/blog_system: a
    // docblock between the namespace and the class, and a helper class
    // declared before Formr itself.
    $library = <<<'PHP'
<?php

namespace Formr;

/**
 * Formr (1.3.1)
 *
 * a php micro-framework which helps you build and validate web forms
 */

class FormrException extends \Exception {}


class Formr
{
    public $wrapper = '';
}
PHP;

    expect($this->migrator->definesFormrClass($library))->toBeTrue();
});
