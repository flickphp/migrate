<?php

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

describe('namespace migration', function () {
    test('converts use statement', function () {
        $input = "<?php\nuse Formr\Formr;\n";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('use Flick\Flick;');
    });

    test('converts fully qualified class name', function () {
        $input = '<?php $form = new Formr\Formr();';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('new Flick()');
    });
});

describe('constructor migration', function () {
    test('converts simple constructor', function () {
        $input = '<?php $form = new Formr();';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('new Flick()');
    });

    test('converts constructor with wrapper', function () {
        $input = "<?php \$form = new Formr('bootstrap');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("new Flick(['views' => 'bootstrap'])");
    });

    test('converts constructor with hush switch', function () {
        $input = "<?php \$form = new Formr('bulma', 'hush');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("new Flick(['views' => 'bulma', 'echo' => false])");
    });
});

describe('method migration', function () {
    test('converts create_form to create', function () {
        $input = '<?php $form->create_form("Name");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->create(');
    });

    test('converts input_text to text', function () {
        $input = '<?php $form->input_text("name");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->text(');
    });

    test('converts post to request', function () {
        $input = '<?php $form->post("email");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->request(');
    });

    test('converts post with label and rules to request dropping label', function () {
        $input = '<?php $name = $form->post(\'name\', \'Name\', \'required, min:2\');';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->request(\'name\', \'required, min:2\')');
        expect($output)->not->toContain('\'Name\'');
    });

    test('converts post with only label to request without label', function () {
        $input = '<?php $name = $form->post(\'name\', \'Name\');';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->request(\'name\')');
        expect($output)->not->toContain('\'Name\'');
    });

    test('converts get to request with parameter transformation', function () {
        $input = '<?php $search = $form->get(\'query\', \'Search Term\', \'required\');';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->request(\'query\', \'required\')');
        expect($output)->not->toContain('get(');
        expect($output)->not->toContain('\'Search Term\'');
    });

    test('converts fastpost with variable argument to request', function () {
        $input = '<?php $form->fastpost($formArray);';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->request($formArray)');
        expect($output)->not->toContain('fastpost');
    });

    test('converts submit check to submitted', function () {
        $input = '<?php if ($form->submit()) { }';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('if ($form->submitted())');
    });

    test('converts in_errors to hasError', function () {
        $input = '<?php $form->in_errors("email");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->hasError(');
    });

    test('converts error_message to errorMessage', function () {
        $input = '<?php $form->error_message("Error");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->errorMessage(');
    });

    test('converts datetime_local to datetime', function () {
        $input = '<?php $form->datetime_local("date");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->datetime(');
    });

    test('converts error to getError', function () {
        $input = '<?php $form->error("email");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->getError(');
    });

    test('converts errors to getErrors', function () {
        $input = '<?php $form->errors();';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->getErrors(');
    });
});

describe('validation rule migration', function () {
    test('converts pipe delimiter to comma', function () {
        $input = "<?php \$form->request('email', 'Email', 'required|email');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('required, email');
    });

    test('converts bracket notation to colon', function () {
        $input = "<?php \$form->request('name', 'Name', 'min[5]|max[50]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('min:5, max:50');
    });

    test('converts valid_email to email', function () {
        $input = "<?php \$form->request('email', 'Email', 'valid_email');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'email'");
    });

    test('converts min_length to min', function () {
        $input = "<?php \$form->request('name', 'Name', 'min_length[5]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('min:5');
    });

    test('converts greater_than to greaterThan', function () {
        $input = "<?php \$form->request('age', 'Age', 'greater_than[18]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('greaterThan:18');
    });

    test('converts matches rule syntax', function () {
        $input = "<?php \$form->request('password_confirm', 'Confirm', 'matches[password]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('matches:password');
    });

    test('converts before rule', function () {
        $input = "<?php \$form->request('date', 'Date', 'before[2025-01-01]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('before:2025-01-01');
    });

    test('converts after rule', function () {
        $input = "<?php \$form->request('date', 'Date', 'after[2020-01-01]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('after:2020-01-01');
    });

    test('converts alpha_numeric to alphaNumeric', function () {
        $input = "<?php \$form->request('code', 'Code', 'alpha_numeric');";
        $output = $this->migrator->migrate($input);

        // Formr's letters+digits alpha_numeric maps exactly to Flick's
        // alphaNumeric rule — no caveat TODO needed.
        expect($output)->toContain('alphaNumeric')
            ->and($output)->not->toContain("'alphaDash'");
    });

    it('does not flag alpha_numeric as unmapped after renaming it', function () {
        $migrator = new FormrMigrator;

        $output = $migrator->migrate(<<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->post('user', 'User', 'required|alpha_numeric');
PHP);

        expect($output)->toContain('alphaNumeric')
            ->and($output)->not->toContain('has no known Flick rule equivalent')
            ->and(implode("\n", $migrator->getTodos()))->not->toContain('alpha_numeric');
    });

    it('recognises every rule name its own map can produce', function () {
        // A rename that lands on a name the "known" list omits produces a correct
        // conversion AND a false TODO in the user's file. Derive the set instead of
        // hand-copying it.
        $migrator = new FormrMigrator;
        $reflection = new ReflectionClass($migrator);

        $ruleMap = $reflection->getProperty('ruleMap')->getValue($migrator);

        $unknown = array_values(array_filter(
            array_unique(array_values($ruleMap)),
            fn (string $name): bool => ! $reflection
                ->getMethod('ruleIsKnownAfterMapping')
                ->invoke($migrator, $name)
        ));

        expect($unknown)->toBe([]);
    });
});

describe('inline validation migration', function () {
    test('converts inline validation syntax', function () {
        $input = "<?php \$form->request('Name(required|min[2])');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('Name[required, min:2]');
    });
});

describe('methods with no equivalent', function () {
    test('flags button as no equivalent', function () {
        $input = '<?php $form->button("Click");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION');
    });

    test('converts honeypot with name to constructor config', function () {
        $input = '<?php
$form = new Flick([\'id\' => \'test\']);
$form->honeypot(\'my_honeypot\');';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'honeypot' => 'my_honeypot'");
        expect($output)->toContain('TODO: FLICK MIGRATION - honeypot moved to constructor');
    });

    test('flags honeypot without name as needing manual fix', function () {
        $input = '<?php $form->honeypot();';
        $output = $this->migrator->migrate($input);

        // When no honeypot name provided, it should be flagged
        expect($output)->toContain('TODO: FLICK MIGRATION');
        expect($output)->toContain('honeypot');
    });

    test('flags input_reset as no equivalent', function () {
        $input = '<?php $form->input_reset("Reset");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION');
    });

    test('converts fastform to create', function () {
        $input = '<?php $form->fastform("Name, Email");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->create(');
        expect($output)->not->toContain('fastform');
    });

    test('converts fastform_multipart to createMultipart', function () {
        $input = '<?php $form->fastform_multipart("Name, File");';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->createMultipart(');
        expect($output)->not->toContain('fastform_multipart');
    });

    test('comments out messages method call', function () {
        $input = "<?php\n\$form->messages();\n";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION');
        expect($output)->toContain('//$form->messages();');
    });
});

describe('formr array conversion', function () {
    test('converts formr array format to flick format', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$formArray = [
    'text' => 'fname,First Name:',
    'text2' => 'lname,Last Name:',
    'email' => 'email,Email Address:',
    'submit' => 'submit,,Submit Form'
];
echo $form->create($formArray);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'fields' =>");
        expect($output)->toContain("'fname' => ['type' => 'text', 'label' => 'First Name:']");
        expect($output)->toContain("'lname' => ['type' => 'text', 'label' => 'Last Name:']");
        expect($output)->toContain("'email' => ['type' => 'email', 'label' => 'Email Address:']");
        expect($output)->toContain("'button' => 'Submit Form'");
    });

    test('handles single field type without numeric suffix', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$arr = ['text' => 'name,Name:'];
echo $form->create($arr);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'fields' =>");
        expect($output)->toContain("'name' => ['type' => 'text', 'label' => 'Name:']");
    });
});

describe('property migration', function () {
    test('comments out upload_dir when no constructor exists', function () {
        $input = "<?php \$form->upload_dir = '/uploads';";
        $output = $this->migrator->migrate($input);

        // When no Flick constructor exists, upload_dir should be commented out with TODO
        expect($output)->toContain('TODO: FLICK MIGRATION - Add services.upload config to constructor');
        expect($output)->toContain("// \$form->upload_dir = '/uploads';");
    });

    test('flags required as no equivalent', function () {
        $input = "<?php \$form->required = '*';";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("TODO: FLICK MIGRATION - 'required' has no direct Flick equivalent");
    });

    test('flags version as no equivalent', function () {
        $input = "<?php \$form->version = '1.0';";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION');
        expect($output)->toContain('version');
    });

    test('flags method as no equivalent', function () {
        $input = "<?php \$form->method = 'get';";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION');
    });

    test('merges id property into empty constructor', function () {
        $input = "<?php\n\$form = new Flick();\n\$form->id = 'myForm';";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("new Flick(['id' => 'myForm'])");
        expect($output)->not->toContain("\$form->id = 'myForm'");
    });

    test('merges id property into existing constructor config', function () {
        $input = "<?php\n\$form = new Flick(['views' => 'bootstrap']);\n\$form->id = 'myForm';";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'views' => 'bootstrap'");
        expect($output)->toContain("'id' => 'myForm'");
        expect($output)->not->toContain("\$form->id = 'myForm'");
    });

    test('merges multiple properties into constructor', function () {
        $input = "<?php\n\$form = new Flick();\n\$form->id = 'myForm';\n\$form->action = '/submit';";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'id' => 'myForm'");
        expect($output)->toContain("'action' => '/submit'");
    });
});

describe('dropdown name migration', function () {
    test('converts state alias to states in options', function () {
        // After select migration: 8-arg becomes 4-arg with 'states' as simple string
        // (dropdown name is converted from 'state' to 'states')
        $input = "<?php \$form->select('state', 'State', '', '', '', '', '', 'state');";
        $output = $this->migrator->migrate($input);

        // Field name 'state' should remain unchanged, options become 'states'
        expect($output)->toContain("->select('state', 'State', '', 'states')");
    });

    test('converts country alias to countries in options', function () {
        $input = "<?php \$form->select('country', 'Country', '', '', '', '', '', 'country');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'countries'");
    });

    test('converts states_provinces to statesProvinces', function () {
        $input = "<?php \$form->select('loc', 'Location', '', '', '', '', '', 'states_provinces');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'statesProvinces'");
    });

    test('converts height to heights', function () {
        $input = "<?php \$form->select('h', 'Height', '', '', '', '', '', 'height');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'heights'");
    });

    test('converts age to ages', function () {
        $input = "<?php \$form->select('a', 'Age', '', '', '', '', '', 'age');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'ages'");
    });

    test('converts cc_months to months2', function () {
        $input = "<?php \$form->select('m', 'Month', '', '', '', '', '', 'cc_months');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'months2'");
    });

    test('converts cc_years to yearsPlus', function () {
        $input = "<?php \$form->select('y', 'Year', '', '', '', '', '', 'cc_years');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'yearsPlus'");
    });

    test('flags years_old as no equivalent', function () {
        $input = "<?php \$form->select('yr', 'Year', '', '', '', '', '', 'years_old');";
        $output = $this->migrator->migrate($input);

        // Dropdown migration adds TODO for years_old (no Flick equivalent)
        expect($output)->toContain('TODO: FLICK MIGRATION');
        expect($output)->toContain('years_old');
    });
});

describe('select method migration', function () {
    test('converts 8-arg select to 4-arg select with simple string', function () {
        // When no custom id, just use simple string for options
        $input = "<?php \$form->select('state', 'State', '', '', '', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->select('state', 'State', '', 'states')");
    });

    test('preserves id in attributes array', function () {
        // When custom id differs from field name, use array format
        $input = "<?php \$form->select('state', 'State', 'CA', 'myId', '', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'id' => 'myId'");
        expect($output)->toContain("'options' => 'states'");
    });

    test('drops help and selected params', function () {
        $input = "<?php \$form->select('s', 'S', '', '', '', 'Help text', 'selected', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->not->toContain('Help text');
        expect($output)->not->toContain("'selected'");
        expect($output)->toContain("'states'");
    });

    test('handles empty options gracefully', function () {
        $input = "<?php \$form->select('field', 'Label', '', '', '', '', '', '');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->select('field', 'Label', '', '')");
    });

    test('preserves value parameter', function () {
        // Simple string format when no custom id
        $input = "<?php \$form->select('country', 'Country', 'US', '', '', '', '', 'countries');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->select('country', 'Country', 'US', 'countries')");
    });

    test('drops id when same as field name', function () {
        // id='state' same as field name, so drop it and use simple format
        $input = "<?php \$form->select('state', 'State:', '', 'state', '', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->select('state', 'State:', '', 'states')");
    });

    test('converts input_select through full pipeline', function () {
        // input_select gets renamed to select first, then 8-arg to 4-arg
        $input = "<?php \$form->input_select('state', 'State:', '', 'state', '', '', '', 'state');";
        $output = $this->migrator->migrate($input);

        // Should be select (not input_select), 4 args, with dropdown name converted
        expect($output)->toContain("->select('state', 'State:', '', 'states')");
    });

    test('handles variable options in select', function () {
        $input = "<?php \$form->select('color', 'Color:', '', 'color', '', '', '', \$colorOptions);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->select('color', 'Color:', '', \$colorOptions)");
    });

    test('converts selectMultiple with 8 args', function () {
        $input = "<?php \$form->selectMultiple('sizes[]', 'Sizes:', '', 'sizes', '', '', '', \$sizeOptions);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->selectMultiple('sizes[]', 'Sizes:', '', \$sizeOptions)");
    });

    test('preserves class attribute from Formr select', function () {
        $input = "<?php \$form->select('state', '', '', 'state', 'class=\"form-select\"', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("['options' => 'states', 'class' => 'form-select']");
    });

    test('preserves multiple attributes from Formr select', function () {
        $input = "<?php \$form->select('state', 'State:', '', 'state', 'class=\"form-control\" data-live-search=\"true\"', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'options' => 'states'");
        expect($output)->toContain("'class' => 'form-control'");
        expect($output)->toContain("'data-live-search' => 'true'");
    });

    test('preserves standalone boolean attributes', function () {
        $input = "<?php \$form->select('state', 'State:', '', 'state', 'disabled required', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'disabled' => true");
        expect($output)->toContain("'required' => true");
    });

    test('preserves mixed class and boolean attributes', function () {
        $input = "<?php \$form->select('state', 'State:', '', 'state', 'class=\"my-class\" disabled', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'class' => 'my-class'");
        expect($output)->toContain("'disabled' => true");
    });

    test('uses simple string format when no attributes present', function () {
        $input = "<?php \$form->select('state', 'State', '', 'state', '', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->select('state', 'State', '', 'states')");
        expect($output)->not->toContain('[');
    });

    test('preserves attributes with variable options', function () {
        $input = "<?php \$form->select('color', 'Color:', '', 'color', 'class=\"color-picker\"', '', '', \$colorOptions);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'options' => \$colorOptions");
        expect($output)->toContain("'class' => 'color-picker'");
    });

    test('preserves attributes with inline array options', function () {
        $input = "<?php \$form->select('priority', 'Priority:', '', 'priority', 'class=\"priority-select\"', '', '', ['low' => 'Low', 'high' => 'High']);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'class' => 'priority-select'");
        expect($output)->toContain("'options' => ['low' => 'Low', 'high' => 'High']");
    });

    test('preserves custom id along with attributes', function () {
        $input = "<?php \$form->select('state', 'State:', '', 'myCustomId', 'class=\"form-select\"', '', '', 'states');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'id' => 'myCustomId'");
        expect($output)->toContain("'options' => 'states'");
        expect($output)->toContain("'class' => 'form-select'");
    });

    test('handles selectMultiple with attributes', function () {
        $input = "<?php \$form->selectMultiple('sizes[]', 'Sizes:', '', 'sizes', 'class=\"multi-select\"', '', '', \$sizeOptions);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->selectMultiple('sizes[]', 'Sizes:', '',");
        expect($output)->toContain("'options' => \$sizeOptions");
        expect($output)->toContain("'class' => 'multi-select'");
    });
});

describe('stats tracking', function () {
    test('tracks namespace changes', function () {
        $input = "<?php\nuse Formr\Formr;\n";
        $this->migrator->migrate($input);
        $stats = $this->migrator->getStats();

        expect($stats['namespaces'])->toBeGreaterThan(0);
    });

    test('tracks method renames', function () {
        $input = '<?php $form->create_form("Name");';
        $this->migrator->migrate($input);
        $stats = $this->migrator->getStats();

        expect($stats['methods'])->toBeGreaterThan(0);
    });

    test('resets stats between migrations', function () {
        $input = '<?php $form->create_form("Name");';
        $this->migrator->migrate($input);
        $this->migrator->resetStats();
        $stats = $this->migrator->getStats();

        expect($stats['methods'])->toBe(0);
    });
});

describe('upload migration', function () {
    test('adds upload config to existing constructor', function () {
        $input = <<<'PHP'
<?php
$form = new Flick(['views' => 'bootstrap']);
$form->upload_dir = __DIR__ . '/uploads';
$form->upload_max_filesize = 5242880;
PHP;

        $output = $this->migrator->migrate($input);

        // Core stopped reading a 'vendor' key, so a migrated config must not
        // carry one.
        expect($output)->not->toContain("'vendor'");

        // Should add services.upload config to constructor
        expect($output)->toContain("'services' => ['upload' =>");
        expect($output)->toContain("'directory' => __DIR__ . '/uploads'");
        expect($output)->toContain("'maxFileSize' => '5M'");

        // Original property assignments should be removed
        expect($output)->not->toContain('$form->upload_dir =');
        expect($output)->not->toContain('$form->upload_max_filesize =');
    });

    test('replaces request() with upload->image() for file fields', function () {
        $input = <<<'PHP'
<?php
echo $form->file('avatar', 'Avatar');
$avatar = $form->request('avatar');
PHP;

        $output = $this->migrator->migrate($input);

        // Should replace request() with upload->image() for avatar field
        expect($output)->toContain('$form->upload->image(\'avatar\')');
        expect($output)->toContain('TODO: FLICK MIGRATION - Adjust upload options');
    });

    test('replaces request() with upload->file() for documents', function () {
        $input = <<<'PHP'
<?php
echo $form->files('documents', 'Documents');
$docs = $form->request('documents');
PHP;

        $output = $this->migrator->migrate($input);

        // Should replace request() with upload->file() for document fields
        expect($output)->toContain('$form->upload->file(\'documents\')');
    });

    test('converts config-mappable property reads to config() calls', function () {
        $input = <<<'PHP'
<?php
$form = new Flick(['views' => 'bootstrap']);
echo $form->upload_dir;
PHP;

        $output = $this->migrator->migrate($input);

        // Config-mappable property should be converted to config() call
        expect($output)->toContain("echo \$form->config('services.upload.directory')");
    });

    test('converts inline PHP property reads to config() calls', function () {
        $input = 'Directory: <code><?php echo $form->upload_dir; ?></code>';

        $output = $this->migrator->migrate($input);

        // Inline PHP should be converted to config() call
        expect($output)->toContain("<?php echo \$form->config('services.upload.directory'); ?>");
    });

    test('comments out per-upload property reads', function () {
        $input = 'Accepted: <code><?php echo $form->upload_accepted_types; ?></code>';

        $output = $this->migrator->migrate($input);

        // Per-upload property should be commented out with TODO
        expect($output)->toContain('TODO: upload_accepted_types is now set per-upload via mime option');
    });

    test('adds TODO for per-upload properties', function () {
        $input = <<<'PHP'
<?php
$form = new Flick(['views' => 'bootstrap']);
$form->upload_accepted_types = 'jpg,png,gif';
PHP;

        $output = $this->migrator->migrate($input);

        // Should add TODO indicating this is now per-upload
        expect($output)->toContain("TODO: FLICK MIGRATION - 'upload_accepted_types' is now set per-upload");
    });
});

describe('mail method migration', function () {
    test('converts send_email to mail->send', function () {
        $input = "<?php \$form->send_email('admin@example.com', 'Subject', \$message);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->mail->send('admin@example.com', 'Subject', \$message)");
        expect($output)->not->toContain('send_email');
    });

    test('converts send_html_email to mail->send with html option', function () {
        $input = "<?php \$form->send_html_email('admin@example.com', 'Subject', '<h1>Hello</h1>');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->mail->send('admin@example.com', 'Subject', '', ['html' => '<h1>Hello</h1>'])");
        expect($output)->not->toContain('send_html_email');
    });

    test('handles variable arguments in send_email', function () {
        $input = '<?php $form->send_email($to, $subject, $body);';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->mail->send($to, $subject, $body)');
    });

    test('handles variable arguments in send_html_email', function () {
        $input = '<?php $form->send_html_email($recipient, $title, $htmlContent);';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->mail->send(\$recipient, \$title, '', ['html' => \$htmlContent])");
    });

    test('adds TODO for mail service configuration', function () {
        $input = "<?php \$form->send_email('admin@example.com', 'Subject', \$message);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION - Mail service requires configuration');
    });

    test('adds TODO only once for multiple mail calls', function () {
        $input = <<<'PHP'
<?php
$form->send_email('admin@example.com', 'Subject 1', $msg1);
$form->send_email('admin@example.com', 'Subject 2', $msg2);
PHP;
        $output = $this->migrator->migrate($input);

        // Should only have one TODO comment
        $count = substr_count($output, 'Mail service requires configuration');
        expect($count)->toBe(1);
    });

    test('handles mixed plain and html email calls', function () {
        $input = <<<'PHP'
<?php
$form->send_email($to, 'Plain', $text);
$form->send_html_email($to, 'HTML', $html);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->mail->send($to, \'Plain\', $text)');
        expect($output)->toContain("\$form->mail->send(\$to, 'HTML', '', ['html' => \$html])");
    });

    test('converts email in complete form example', function () {
        $input = <<<'PHP'
<?php
if ($form->ok()) {
    $form->send_email('admin@example.com', 'Contact Form', $message);
    echo 'Thank you!';
}
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->mail->send(');
        expect($output)->not->toContain('send_email');
    });

    test('preserves string concatenation in arguments', function () {
        $input = "<?php \$form->send_email(\$to, 'New ' . \$type . ' Submission', \$body);";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->mail->send(\$to, 'New ' . \$type . ' Submission', \$body)");
    });

    test('tracks mail method conversions in stats', function () {
        $input = <<<'PHP'
<?php
$form->send_email($to, 'Subject 1', $msg1);
$form->send_html_email($to, 'Subject 2', $msg2);
PHP;
        $this->migrator->migrate($input);
        $stats = $this->migrator->getStats();

        // Should count both mail method conversions
        expect($stats['methods'])->toBeGreaterThanOrEqual(2);
    });
});

describe('mail config injection', function () {
    test('injects mail config into empty constructor', function () {
        $input = <<<'PHP'
<?php
$form = new Flick();
$form->send_email('admin@example.com', 'Subject', $message);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'services' =>");
        expect($output)->toContain("'mail' =>");
        expect($output)->toContain("'fromAddress' => 'noreply@example.com'");
        expect($output)->toContain("'mailer' =>");
        expect($output)->toContain("'transport' => 'smtp'");
    });

    test('injects mail config into constructor with existing config', function () {
        $input = <<<'PHP'
<?php
$form = new Flick(['action' => '']);
$form->send_email('admin@example.com', 'Subject', $message);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'action' => ''");
        expect($output)->toContain("'services' =>");
        expect($output)->toContain("'mail' =>");
    });

    test('adds TODO comment in injected mail config', function () {
        $input = <<<'PHP'
<?php
$form = new Flick();
$form->send_email('admin@example.com', 'Subject', $message);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION - Update these mail settings');
    });

    test('skips injection if no mail methods used', function () {
        $input = <<<'PHP'
<?php
$form = new Flick(['views' => 'bootstrap']);
echo $form->text('name', 'Name:');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->not->toContain("'mail' =>");
    });

    test('handles send_html_email for config injection', function () {
        $input = <<<'PHP'
<?php
$form = new Flick();
$form->send_html_email('admin@example.com', 'Subject', '<h1>Hello</h1>');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'mail' =>");
        expect($output)->toContain("'fromAddress'");
    });

    test('increments properties stat when injecting config', function () {
        $input = <<<'PHP'
<?php
$form = new Flick();
$form->send_email('admin@example.com', 'Subject', $message);
PHP;
        $this->migrator->migrate($input);
        $stats = $this->migrator->getStats();

        expect($stats['properties'])->toBeGreaterThanOrEqual(1);
    });
});

describe('receiver scoping (regression)', function () {
    test('leaves unrelated ->get calls untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$cart = $session->get('cart', 'default');
$name = $form->post('name', 'Name', 'required|email');
PHP;
        $output = $this->migrator->migrate($input);

        // $session is not a Formr instance: its get() must not be rewritten
        // to request(), and its second argument must not be dropped.
        expect($output)->toContain("\$session->get('cart', 'default')");
        // The real Formr call is still migrated.
        expect($output)->toContain("\$form->request('name', 'required, email')");
    });

    test('leaves unrelated error/dropdown/upload method calls untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$logger->error('boom');
$menu = $config->dropdown('items');
$svc->upload('file');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$logger->error('boom')");
        expect($output)->toContain("\$config->dropdown('items')");
        expect($output)->toContain("\$svc->upload('file')");
    });

    test('leaves unrelated property assignments untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$post->comments = 'hello';
$config->version = '2.0';
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$post->comments = 'hello';");
        expect($output)->toContain("\$config->version = '2.0';");
        expect($output)->not->toContain('TODO');
    });

    test('leaves unrelated upload property assignments untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$settings->upload_dir = '/var/data';
$settings->upload_max_filesize = 1048576;
PHP;
        $output = $this->migrator->migrate($input);

        // $settings is not a Formr instance: its upload_* assignments must
        // not be extracted into constructor config or commented out.
        expect($output)->toContain("\$settings->upload_dir = '/var/data';");
        expect($output)->toContain('$settings->upload_max_filesize = 1048576;');
        expect($output)->not->toContain('TODO');
    });

    test('does not mangle equality comparisons', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
if ($request->method == 'POST') {
    echo 'ok';
}
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("if (\$request->method == 'POST')");
        expect($output)->not->toContain('TODO');
    });
});

describe('data array guard (regression)', function () {
    test('leaves a plain data array untouched', function () {
        $input = "<?php \$appointment = ['date' => '2024-01-01', 'time' => '10:30'];";
        $output = $this->migrator->migrate($input);

        expect($output)->toBe($input);
    });

    test('leaves a data array with a comma in a value untouched', function () {
        $input = "<?php \$msg = ['text' => 'Hello, World'];";
        $output = $this->migrator->migrate($input);

        expect($output)->toBe($input);
    });

    test('still converts an array passed to a form builder', function () {
        $input = <<<'PHP'
<?php
$fields = ['text' => 'name', 'email' => 'email'];
echo $form->create($fields);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'fields' =>");
    });
});

describe('prebuilt form output (regression)', function () {
    test('migrated create_form output is lint-clean and idempotent', function () {
        $input = <<<'PHP'
<?php
echo $form->create_form('custom_form');
PHP;
        $once = $this->migrator->migrate($input);

        // The rewrite must not truncate the statement mid-expression
        // (previously emitted a bare `//` comment inside the echo).
        $tmp = tempnam(sys_get_temp_dir(), 'flickmig');
        file_put_contents($tmp, $once);
        exec('php -l '.escapeshellarg($tmp).' 2>&1', $lintOutput, $lintCode);
        unlink($tmp);

        expect($lintCode)->toBe(0)
            ->and($once)->toContain('TODO')
            ->and($this->migrator->migrate($once))->toBe($once);
    });
});

describe('inline validation scoping (regression)', function () {
    test('does not touch constants outside of strings', function () {
        $input = '<?php error_reporting(E_ALL|E_STRICT);';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('error_reporting(E_ALL|E_STRICT)');
    });
});

describe('mail methods with nested calls (regression)', function () {
    test('handles a nested call in the html body without breaking parens', function () {
        $input = "<?php \$form->send_html_email(\$to, 'Hi', render(\$tpl));";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("['html' => render(\$tpl)]");
        expect($output)->not->toContain('render($tpl])');
    });
});

describe('constructor and class-name fallbacks (regression)', function () {
    test('renames new Formr($var) with a TODO', function () {
        $input = '<?php $form = new Formr($framework);';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('new Flick($framework)');
        expect($output)->toContain('TODO: FLICK MIGRATION');
        expect($output)->not->toContain('new Formr(');
    });

    test('a non-literal constructor argument still migrates the rest of the file', function () {
        $input = <<<'PHP'
<?php
$form = new Formr($framework);
echo $form->input_text('name');
$email = $form->post('email', 'Email', 'required');
PHP;
        $output = $this->migrator->migrate($input);

        // The constructor TODO used to sit between "=" and "new", which hid
        // the receiver from every later pass and left the file unmigrated
        // under a TODO claiming the form was built somewhere else.
        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('new Flick($framework)')
            ->and($output)->not->toContain('input_text')
            ->and($output)->toContain("->request('email', 'required')")
            ->and($output)->not->toContain('created elsewhere');
    });

    test('re-running over a non-literal constructor is a no-op', function () {
        $input = <<<'PHP'
<?php
$form = new Formr($framework);
echo $form->input_text('name');
$email = $form->post('email', 'Email', 'required');
echo $form->heading('Title');
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        // The TODO the first run wrote sits between "=" and "new", so the
        // receiver has to stay findable across it or the second run would
        // treat the form as external and re-flag the whole file.
        expect($twice)->toBe($once)
            ->and($twice)->not->toContain('created elsewhere');
    });

    test('renames bare Formr class references', function () {
        $input = '<?php if ($x instanceof Formr) {}';
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('instanceof Flick');
        expect($output)->not->toContain('Formr');
    });

    test('does not rename Formr inside string literals or comments', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
// Formr is the old library
/* Formr block
   comment */
echo "Powered by Formr";
echo 'Formr rocks';
if ($x instanceof Formr) {}
PHP;
        // The nowdoc above takes its line endings from this file, which a
        // Windows checkout gives CRLF (`* text=auto`). The migrator preserves
        // them faithfully, so the "\n" written into the multi-line expectation
        // below would not be found. Normalise the input instead of the
        // expectation: what is under test is which identifiers get renamed,
        // not how lines end.
        $input = str_replace("\r\n", "\n", $input);

        $output = $this->migrator->migrate($input);

        expect($output)->toContain('"Powered by Formr"')
            ->and($output)->toContain("'Formr rocks'")
            ->and($output)->toContain('// Formr is the old library')
            ->and($output)->toContain("/* Formr block\n   comment */")
            ->and($output)->toContain('instanceof Flick')
            ->and($output)->toContain('new Flick')
            ->and($output)->not->toContain('new Formr');
    });

    test('does not rename Formr inside heredoc or nowdoc bodies', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$html = <<<HTML
<p>Powered by Formr</p>
HTML;
$text = <<<'TXT'
Formr rocks
TXT;
if ($x instanceof Formr) {}
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect($once)->toContain('<p>Powered by Formr</p>')
            ->and($once)->toContain('Formr rocks')
            ->and($once)->toContain('instanceof Flick')
            ->and($twice)->toBe($once);
    });

    test('string and comment scoping is idempotent', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo "Powered by Formr"; // Formr footer
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect($twice)->toBe($once)
            ->and($once)->toContain('"Powered by Formr"');
    });
});

describe('dropdown options tracing (regression)', function () {
    test('leaves options entries in unrelated arrays untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$addressConfig = ['options' => 'state', 'fallback' => 'none'];
$ageConfig = ['options' => 'years_old'];
echo $form->text('name');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("['options' => 'state', 'fallback' => 'none']")
            ->and($output)->not->toContain("'states'")
            ->and($output)->toContain("['options' => 'years_old']")
            ->and($output)->not->toContain('TODO');
    });

    test('renames options entries in arrays passed to a form builder', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$fields = ['fields' => ['loc' => ['type' => 'select', 'options' => 'state']]];
echo $form->create($fields);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'options' => 'states'");
    });

    test('renames options entries in inline arrays on a form receiver call', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $form->select('loc', 'Location:', '', ['options' => 'state']);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("['options' => 'states']");
    });

    test('flags no-equivalent dropdowns only in form arrays, exactly once, idempotently', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$fields = ['fields' => ['yr' => ['type' => 'select', 'options' => 'years_old']]];
echo $form->create($fields);
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect(substr_count($once, "TODO: FLICK MIGRATION - 'years_old'"))->toBe(1)
            ->and($twice)->toBe($once);
    });

    test('the 4-arg select no-equivalent flag is idempotent', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $form->select('yr', 'Year', '', 'years_old');
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect(substr_count($once, "TODO: FLICK MIGRATION - 'years_old'"))->toBe(1)
            ->and($twice)->toBe($once);
    });
});

describe('receiver scoping sweep (regression)', function () {
    test('leaves unrelated select, checkbox, radio, and dropdown-name calls untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$db->select('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h');
$geo->select('a', 'b', 'c', 'state');
$ui->checkbox('a', 'b', 'c', 'd', 'e', 'f', 'checked');
$ui->radio('a', 'b', 'c', 'd', 'e', 'f', 'checked');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$db->select('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h')")
            ->and($output)->toContain("\$geo->select('a', 'b', 'c', 'state')")
            ->and($output)->toContain("\$ui->checkbox('a', 'b', 'c', 'd', 'e', 'f', 'checked')")
            ->and($output)->toContain("\$ui->radio('a', 'b', 'c', 'd', 'e', 'f', 'checked')");
    });

    test('leaves unrelated submit, fieldsetOpen, and honeypot calls untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
if ($gateway->submit()) {
    echo 'paid';
}
$ui->fieldsetOpen('Legend', $attrs);
$spam->honeypot('trap');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('if ($gateway->submit())')
            ->and($output)->toContain("\$ui->fieldsetOpen('Legend', \$attrs)")
            ->and($output)->toContain("\$spam->honeypot('trap');")
            ->and($output)->not->toContain('honeypot\' =>');
    });

    test('leaves unrelated create, send_email, and file-field request calls untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$auth->create('login');
$factory->create('user');
$mailer->send_email($to, $subject, $body);
$disk->file('avatar');
$data = $api->request('avatar');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$auth->create('login')")
            ->and($output)->not->toContain('/login')
            ->and($output)->toContain("\$factory->create('user')")
            ->and($output)->not->toContain('TODO')
            ->and($output)->toContain('$mailer->send_email($to, $subject, $body)')
            ->and($output)->toContain("\$data = \$api->request('avatar')")
            ->and($output)->not->toContain('upload->');
    });

    test('leaves unrelated property reads untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $settings->upload_dir;
?>
<p><?php echo $post->comments; ?></p>
<p><?php echo $config->version; ?></p>
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('echo $settings->upload_dir;')
            ->and($output)->toContain('<?php echo $post->comments; ?>')
            ->and($output)->toContain('<?php echo $config->version; ?>')
            ->and($output)->not->toContain('TODO');
    });

    test('keeps a custom form id that matches a prebuilt id when no prebuilt form is used', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->id = 'loginForm';
echo $form->open();
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'loginForm'")
            ->and($output)->not->toContain('form-login');
    });

    test('still migrates the known receiver through every scoped pass', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->id = 'loginForm';
if ($form->submit()) {
    $form->send_email($to, 'Hi', $body);
}
echo $form->fastform('login');
echo $form->select('color', 'Color:', '', 'state');
echo $form->honeypot('trap');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('if ($form->submitted())')
            ->and($output)->toContain("\$form->mail->send(\$to, 'Hi', \$body)")
            ->and($output)->toContain("create('/login')")
            // the prebuilt login form is used, so the id remap applies
            ->and($output)->toContain("'form-login'")
            ->and($output)->toContain("'states'")
            ->and($output)->toContain("'honeypot' => 'trap'");
    });

    test('a file mixing form and unrelated receivers migrates idempotently', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
if ($gateway->submit()) {
    $mailer->send_email($a, $b, $c);
}
$db->select('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h');
echo $settings->upload_dir;
$name = $form->post('name', 'Name', 'required');
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect($twice)->toBe($once)
            ->and($once)->toContain("\$form->request('name', 'required')");
    });
});

describe('external instance guard (regression)', function () {
    test('does not rewrite any receivers when the class is referenced but constructed elsewhere', function () {
        $input = <<<'PHP'
<?php
use Formr\Formr;

$cart = $session->get('cart', 'default');
$name = $form->post('name', 'Name', 'required');
PHP;
        $output = $this->migrator->migrate($input);

        // No local constructor: we cannot tell which variable holds the form,
        // so nothing may be rewritten — especially not $session->get(), whose
        // second argument used to be silently dropped by the any-variable
        // fallback.
        expect($output)->toContain("\$session->get('cart', 'default')")
            ->and($output)->toContain("\$form->post('name', 'Name', 'required')")
            ->and($output)->toContain('use Flick\Flick;')
            ->and($output)->toContain('TODO: FLICK MIGRATION');
    });

    test('type-hinted receivers are migrated and unrelated receivers stay untouched', function () {
        $input = <<<'PHP'
<?php
use Formr\Formr;

function render(Formr $form, $session)
{
    $cart = $session->get('cart', 'default');

    return $form->input_text('name');
}
PHP;
        $output = $this->migrator->migrate($input);

        // The type hint identifies $form as the form instance, so its calls
        // migrate while $session is left alone.
        expect($output)->toContain('Flick $form')
            ->and($output)->toContain("\$form->text('name')")
            ->and($output)->toContain("\$session->get('cart', 'default')")
            ->and($output)->not->toContain('created elsewhere');
    });

    test('referencing the class without any form usage adds no TODO', function () {
        $input = <<<'PHP'
<?php
use Formr\Formr;

$logger->debug('x');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('use Flick\Flick;')
            ->and($output)->toContain("\$logger->debug('x')")
            ->and($output)->not->toContain('TODO');
    });

    test('external-instance output is lint-clean and idempotent', function () {
        $input = <<<'PHP'
<?php
use Formr\Formr;

$cart = $session->get('cart', 'default');
$name = $form->post('name', 'Name', 'required');
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        $tmp = tempnam(sys_get_temp_dir(), 'flickmig');
        file_put_contents($tmp, $once);
        exec('php -l '.escapeshellarg($tmp).' 2>&1', $lintOutput, $lintCode);
        unlink($tmp);

        expect($lintCode)->toBe(0)
            ->and($twice)->toBe($once);
    });

    test('standalone snippets without class references keep the any-variable fallback', function () {
        $input = "<?php \$name = \$form->post('name', 'Name', 'required');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->request('name', 'required')");
    });
});

describe('v1 punchlist regressions', function () {
    // mig-H1: password/sanitize modifiers must stay INLINE, not be dropped.
    test('mig-H1: hash modifier is appended inline in the request() path', function () {
        $input = "<?php \$form->post('password', 'Password', 'required|min_length[8]|hash');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('bcrypt')
            ->and($output)->toContain("request('password', 'required, min:8, bcrypt')")
            ->and($output)->not->toContain("['modifiers'");
    });

    test('mig-H1: hash modifier is appended inline in the create() inline-string path', function () {
        $input = "<?php \$form->create('password(required|min[8]|hash)');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('password[required, min:8, bcrypt]');
    });

    test('mig-H1: sanitize_string modifier maps to stripTags inline', function () {
        $input = "<?php \$form->post('bio', 'Bio', 'required|sanitize_string');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('stripTags')
            ->and($output)->not->toContain("['modifiers'");
    });

    // mig-H2: alpha_numeric -> alphaNumeric (letters-only alpha would reject digits).
    test('mig-H2: alpha_numeric maps to alphaNumeric not alpha', function () {
        $input = "<?php \$form->post('code', 'Code', 'required|alpha_numeric');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('alphaNumeric')
            ->and($output)->not->toMatch('/\balpha\b(?!Numeric)/');
    });

    // mig-M1: is_numeric/in_array mapped; unknown tokens TODO-flagged.
    test('mig-M1: is_numeric maps to numeric', function () {
        $input = "<?php \$form->post('qty', 'Qty', 'required|is_numeric');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('numeric')
            ->and($output)->not->toContain('is_numeric');
    });

    test('mig-M1: in_array maps to in with colon params', function () {
        $input = "<?php \$form->post('color', 'Color', 'required|in_array[red,green,blue]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('in:red,green,blue')
            ->and($output)->not->toContain('in_array');
    });

    test('mig-M1: unknown rule token gets a TODO instead of silently passing through', function () {
        $input = "<?php \$form->post('x', 'X', 'required|totally_unknown_rule');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->toContain('totally_unknown_rule');
    });

    // mig-M6: regex parameter containing ']' converts to colon syntax.
    test('mig-M6: regex rule with a bracket in its pattern converts to colon syntax', function () {
        $input = "<?php \$form->post('slug', 'Slug', 'required|regex[/^[a-z]+\$/]');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('regex:/^[a-z]+$/')
            ->and($output)->not->toContain('regex[');
    });

    // mig-H3: non-literal label in 3-arg call must not land in the rules slot.
    test('mig-H3: non-literal label is dropped, never left in the rules slot', function () {
        $input = "<?php \$form->post('name', \$labelVar, 'required');";
        $output = $this->migrator->migrate($input);

        expect($output)->not->toContain('$labelVar')
            ->and($output)->toContain("request('name', 'required')");
    });

    // mig-H5: config merge must cross a nested array in the constructor.
    test('mig-H5: id property merges into a constructor with a nested array', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->id = 'my-form';
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'id' => 'my-form'")
            ->and($output)->toContain("'views' => 'bootstrap'");
    });

    test('mig-H5: honeypot merges into a nested-array constructor and is only then commented out', function () {
        // Already-Flick input with a nested array — the [^\]]*? matcher used to
        // stop at the inner ']' and skip the merge (dropping spam protection).
        $input = <<<'PHP'
<?php
$form = new Flick(['views' => 'bootstrap', 'attributes' => ['class' => 'x']]);
$form->honeypot('website');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'honeypot' => 'website'")
            ->and($output)->toContain("['class' => 'x']")
            ->and($output)->toContain('//$form->honeypot');
    });

    test('mig-H5: nested-array merge is idempotent on a second pass', function () {
        $input = <<<'PHP'
<?php
$form = new Flick(['views' => 'bootstrap', 'attributes' => ['class' => 'x']]);
$form->id = 'my-form';
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect($once)->toContain("'id' => 'my-form'")
            ->and($twice)->toBe($once);
    });

    // full-mig-lows (a): $N backrefs in a property value must not expand.
    test('full-mig-lows: a $1 in a property value round-trips verbatim', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bulma', 'hush');
$form->action = 'page.php?ref=$1';
PHP;
        $output = $this->migrator->migrate($input);

        // The value must merge into the constructor with $1 intact (not
        // expanded as a preg_replace backreference).
        expect($output)->toContain("'action' => 'page.php?ref=\$1'");
    });

    // mig-M3: 5-/6-arg checkbox/radio fold id + attrs into the 4th arg array.
    test('mig-M3: 5-arg input_checkbox folds id and attrs into the 4th argument', function () {
        $input = "<?php \$form->input_checkbox('t', 'Terms', 'yes', 'tid', 'class=\"x\"');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'id' => 'tid'")
            ->and($output)->toContain("'class' => 'x'")
            ->and($output)->not->toContain("'yes', 'tid'");
    });

    test('mig-M3: 5-arg input_radio folds id and attrs into the 4th argument', function () {
        $input = "<?php \$form->input_radio('g', 'Male', 'male', 'gid', 'class=\"y\"');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'id' => 'gid'")
            ->and($output)->toContain("'class' => 'y'");
    });

    // full-mig-checkbox7: quoted attrs and dropped args 5/6.
    test('full-mig-checkbox7: 7-arg checkbox with quoted attrs preserves class, id, checked', function () {
        $input = "<?php \$form->input_checkbox('t', 'Terms', 'yes', 'tid', 'class=\"custom\"', 'Help text', 'checked');";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'id' => 'tid'")
            ->and($output)->toContain("'class' => 'custom'")
            ->and($output)->toContain("'checked' => true");
    });

    // mig-M4: chained method calls all get renamed.
    test('mig-M4: chained Formr methods are all renamed', function () {
        $input = "<?php echo \$form->form_open()->input_text('a', 'A')->form_close();";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('->open()')
            ->and($output)->toContain('->text(')
            ->and($output)->toContain('->close()')
            ->and($output)->not->toContain('form_open')
            ->and($output)->not->toContain('input_text')
            ->and($output)->not->toContain('form_close');
    });

    // mig-M5: Formr syntax inside comments/heredocs is left untouched.
    test('mig-M5: Formr syntax inside a line comment is not rewritten', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
// Example: $form->post('name', 'Name', 'required') was the old way
echo $form->input_text('name');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("// Example: \$form->post('name', 'Name', 'required') was the old way")
            ->and($output)->toContain('->text(');
    });

    test('mig-M5: Formr syntax inside a heredoc body is not rewritten', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$snippet = <<<'HTML'
$form->input_text('a','A')
HTML;
echo $form->input_text('name');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->input_text('a','A')")
            ->and($output)->toContain('->text(');
    });

    // full-mig-C2: array only rewritten when actually passed to a form method.
    test('full-mig-C2: a lowercase comma value not passed to a form method survives', function () {
        $input = "<?php \$greeting = ['text' => 'hello, world'];";
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("['text' => 'hello, world']")
            ->and($output)->not->toContain("'fields'");
    });

    test('full-mig-C2: the same array passed to create() still converts', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$def = ['text' => 'name,Name', 'submit' => 'submit,,Go'];
echo $form->create($def);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'fields'");
    });

    // full-mig-session: session must be commented out, never merged.
    test('full-mig-session: session assignment is TODO-commented, never merged into constructor', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->session = 'my_session';
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->not->toContain("'session' => 'my_session'")
            ->and($output)->toContain('TODO: FLICK MIGRATION');
    });

    // full-mig-submit: compound submit() conditions convert or are flagged.
    test('full-mig-submit: negated submit() in a condition converts to submitted()', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
if (!$form->submit()) {
    echo 'no';
}
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('submitted()')
            ->and($output)->not->toContain('->submit()');
    });

    test('full-mig-submit: submit() with && in a condition converts to submitted()', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
if ($form->submit() && $valid) {
    echo 'ok';
}
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('submitted()')
            ->and($output)->not->toContain('->submit()');
    });

    // full-mig-lows (c): bytesToHuman must not round a limit UP.
    test('full-mig-lows: 1.5MB byte value is not rounded up to 2M', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->upload_max_filesize = 1572864;
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->not->toContain("'2M'");
    });

    // A limit that is not a whole number of MB used to be truncated DOWN to the
    // next whole unit - 2000000 bytes became '1M', cutting the allowed size
    // nearly in half, with only a console note to catch it. Flick's parser takes
    // a plain byte count, so the value can just be carried across intact.
    test('an inexact byte limit is carried across exactly', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->upload_dir = '/uploads';
$form->upload_max_filesize = 2000000;
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'maxFileSize' => '2000000B'")
            ->and($output)->not->toContain("'1M'");
    });

    test('an exactly-divisible byte limit keeps its readable form', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->upload_dir = '/uploads';
$form->upload_max_filesize = 5242880;
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'maxFileSize' => '5M'");
    });

    test('an inexact byte limit needs no confirmation todo', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$form->upload_dir = '/uploads';
$form->upload_max_filesize = 2000000;
PHP;
        $this->migrator->migrate($input);

        // Nothing was lost, so there is nothing to confirm.
        expect(implode("\n", $this->migrator->getTodos()))->not->toContain('maxFileSize');
    });

    // full-mig-lows (b): upload_dir inside a double-quoted string is not rewritten.
    test('full-mig-lows: upload_dir inside a double-quoted string is left alone', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo "path is $form->upload_dir here";
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$form->upload_dir here');
    });

    // mig-L3: silently-unconverted items are recorded in getTodos() so the CLI
    // "Manual review needed" block surfaces them.
    test('mig-L3: an unmapped rule is recorded in getTodos()', function () {
        $input = "<?php \$form->post('x', 'X', 'required|totally_unknown_rule');";
        $this->migrator->migrate($input);

        $todos = implode("\n", $this->migrator->getTodos());
        expect($todos)->toContain('totally_unknown_rule');
    });

    // Migrated output must still parse.
    test('a comprehensive migration produces lint-clean PHP', function () {
        $input = <<<'PHP'
<?php
use Formr\Formr;

$form = new Formr('bootstrap');
$form->id = 'signup';
$form->action = 'submit.php?ref=$1';
$name = $form->post('name', 'Name', 'required|min_length[2]|alpha_numeric');
$pass = $form->post('password', 'Password', 'required|min_length[8]|hash');
$slug = $form->post('slug', 'Slug', 'required|regex[/^[a-z]+$/]');
echo $form->form_open()->input_text('name', 'Name')->form_close();
echo $form->input_checkbox('terms', 'Terms', 'yes', 'tid', 'class="c"', 'Help', 'checked');
if (!$form->submit()) {
    echo 'not submitted';
}
PHP;
        $output = $this->migrator->migrate($input);

        $tmp = tempnam(sys_get_temp_dir(), 'flickmig');
        file_put_contents($tmp, $output);
        exec('php -l '.escapeshellarg($tmp).' 2>&1', $lintOutput, $lintCode);
        unlink($tmp);

        expect($lintCode)->toBe(0, implode("\n", $lintOutput))
            ->and($output)->toContain('bcrypt')
            ->and($output)->toContain('alphaNumeric')
            ->and($output)->toContain('regex:/^[a-z]+$/')
            ->and($output)->toContain('->submitted()');
    });
});

describe('idempotency', function () {
    test('migrating an already-migrated file is a no-op', function () {
        $input = <<<'PHP'
<?php
use Formr\Formr;

$form = new Formr('bootstrap');
$form->id = 'myForm';
$form->honeypot('hp');
$name = $form->post('name', 'Name', 'required|min[2]');
$form->send_email('a@b.com', 'Subject', $msg);
echo $form->input_text('name');
$form->messages();
$form->button('Click');
PHP;

        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect($twice)->toBe($once);
    });
});
