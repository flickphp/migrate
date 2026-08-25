<?php

use Flick\Migrate\FormrMigrator;

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

describe('6a: non-literal label argument (regression)', function () {
    test('multi-arg function call as the label is dropped cleanly', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$email = $form->post('email', sprintf('%s address', $type), 'required|valid_email');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->request('email', 'required, email')")
            ->and($output)->not->toContain('sprintf');
    });
});

describe('6b: semicolon inside a property-value string (regression)', function () {
    test('action URL containing a semicolon is captured whole', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->action = 'process.php?a=1;b=2';
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("'action' => 'process.php?a=1;b=2'")
            ->and($output)->not->toContain("b=2';");
    });

    test('no-equivalent property with a semicolon in its value is commented out whole', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->error_message = 'Fix these; then resubmit';
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("// \$form->error_message = 'Fix these; then resubmit';");
    });
});

describe('6f: submit() check conversion contexts (regression)', function () {
    test('converts submit() used as a ternary condition', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$x = $form->submit() ? 'sent' : 'idle';
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->submitted() ? 'sent' : 'idle'");
    });

    test('converts submit() in a return statement', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
function wasSent($form) {
    return $form->submit();
}
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('return $form->submitted()');
    });

    test('converts submit() with a form id argument in an if condition', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
if ($form->submit('myForm')) {
    echo 'ok';
}
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("if (\$form->submitted('myForm'))");
    });

    test('does not convert a renamed submit button', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_submit('Send');
PHP;
        $output = $this->migrator->migrate($input);

        // The single argument is Formr's $name, not the button text: with $value
        // empty, input_submit() renders "Submit" on a button NAMED 'Send'. This
        // used to become submit('Send'), which changed what the button said.
        // The name has no Flick equivalent, so it is flagged instead.
        expect($output)->toContain('$form->submit()')
            ->and($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->not->toContain('submitted');
    });
});

describe('6g: messages() with nested parens (regression)', function () {
    test('nested-paren call is commented out instead of surviving as an undefined method', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->messages(getOpenTag(), getCloseTag());
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->toContain('//$form->messages(getOpenTag(), getCloseTag());');
    });

    test('call inside an echo expression stays lint-clean', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->messages();
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->not->toMatch('/^\s*echo \$form->messages\(\);/m');
    });
});

describe('6h: 1-arg input_hidden (regression)', function () {
    test('input_hidden with one argument gains an empty value', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_hidden('token');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->hidden('token', '')");
    });

    test('hidden alias with one argument gains an empty value', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->hidden('ref');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->hidden('ref', '')");
    });

    test('hidden with two arguments is left alone', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_hidden('token', $csrf);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->hidden('token', \$csrf)");
    });
});

describe('6i: inline-rule conversion scoping (regression)', function () {
    test('leaves a regex alternation outside form calls untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->create_form('Name(required|min[2])');
if (preg_match('/^cat(dog|bird)$/', $x)) {}
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'/^cat(dog|bird)$/'")
            ->and($output)->toContain('Name[required, min:2]');
    });

    test('leaves a piped data string in an unrelated array untouched', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->create_form('Name(required)');
$config = ['sort' => 'items(name|asc)'];
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'items(name|asc)'");
    });
});

describe('6e: single inline rule (regression)', function () {
    test('converts a single bare rule to bracket syntax', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->create_form('Name(required), Email(valid_email)');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('Name[required]')
            ->and($output)->toContain('Email[email]');
    });

    test('migrated single-rule form renders the field through Flick', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->create_form('Name(required)');
PHP;
        $output = $this->migrator->migrate($input);

        preg_match("/->create\\('([^']+)'\\)/", $output, $m);
        expect($m)->not->toBeEmpty();

        $html = renderThroughFlick("echo \$form->create('{$m[1]}');");
        if ($html === null) {
            $this->markTestSkipped('flick package not available');
        }

        expect($html)->toContain('name="name"')
            ->and($html)->toContain('required')
            ->and($html)->not->toContain('Flick exception');
    });
});

describe('6p: apostrophe in template HTML (regression)', function () {
    test('HTML prose apostrophe cannot desync the string protector', function () {
        $input = <<<'PHP'
<?php $form = new Formr('bootstrap'); ?>
<p>Don't miss this!</p>
<?php echo 'Formr is great'; ?>
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'Formr is great'")
            ->and($output)->toContain("Don't miss this!");
    });

    test('Formr in HTML display text is not renamed', function () {
        $input = <<<'PHP'
<?php $form = new Formr('bootstrap'); ?>
<footer>Powered by Formr</footer>
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('Powered by Formr');
    });
});

describe('6d: fastform array conversion (regression)', function () {
    test('converts an inline array literal passed to fastform', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $form->fastform(['text' => 'name,Name:', 'email' => 'email,Email:', 'submit' => 'submit,,Submit']);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'fields' =>")
            ->and($output)->toContain("'name' => ['type' => 'text', 'label' => 'Name:']")
            ->and($output)->toContain("'email' => ['type' => 'email', 'label' => 'Email:']")
            ->and($output)->toContain("'button' => 'Submit'");
    });

    test('converts an assigned array whose label contains an apostrophe', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$fields = ['text' => "name,Bob's Name:"];
echo $form->fastform($fields);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'fields' =>")
            ->and($output)->toContain("'name' => ['type' => 'text', 'label' => 'Bob\\'s Name:']");
    });

    test('migrated inline fastform renders every field through Flick', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
echo $form->fastform(['text' => 'name,Name:', 'email' => 'email,Email:', 'submit' => 'submit,,Submit']);
PHP;
        $output = $this->migrator->migrate($input);

        preg_match('/->create\((\[.*?\])\);/s', $output, $m);
        expect($m)->not->toBeEmpty();

        $html = renderThroughFlick("echo \$form->create({$m[1]});");
        if ($html === null) {
            $this->markTestSkipped('flick package not available');
        }

        expect($html)->toContain('name="name"')
            ->and($html)->toContain('name="email"')
            ->and($html)->not->toContain('Flick exception');
    });
});

describe('6j: text-family argument folding (regression)', function () {
    test('text() folds id and attributes into the 4th array argument', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->text('username','Username','','user-id','class="form-control" maxlength="20"');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->text('username', 'Username', '', ['id' => 'user-id', 'class' => 'form-control', 'maxlength' => '20'])");
    });

    test('email() with only an id folds it and drops a redundant one', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->input_email('email','Email','','email');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->email('email', 'Email', '')");
    });
});

describe('6k: checkbox with a non-literal argument (regression)', function () {
    test('non-literal id is embedded as an expression, literals still fold', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->checkbox('color', 'Red', 'red', $dynamicId, 'class="form-check"', '', 'checked');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("\$form->checkbox('color', 'Red', 'red', ['id' => \$dynamicId, 'class' => 'form-check', 'checked' => true])");
    });

    test('non-literal attribute string gets a TODO instead of silent loss', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->checkbox('color', 'Red', 'red', 'color-id', $attrString, '', 'checked');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('TODO: FLICK MIGRATION')
            ->and($output)->toContain('$attrString');
    });
});

describe('6l: checked-by-value checkboxes (regression)', function () {
    test('selected argument equal to the value marks the box checked', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->checkbox('news','Newsletter','yes','news-id','','','yes');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'checked' => true");
    });

    test('migrated checked-by-value checkbox renders checked through Flick', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
echo $form->checkbox('news','Newsletter','yes','news-id','','','yes');
PHP;
        $output = $this->migrator->migrate($input);

        preg_match('/->checkbox\((.*?)\);/s', $output, $m);
        expect($m)->not->toBeEmpty();

        $html = renderThroughFlick("echo \$form->checkbox({$m[1]});");
        if ($html === null) {
            $this->markTestSkipped('flick package not available');
        }

        expect($html)->toContain('checked')
            ->and($html)->toContain('id="news-id"');
    });
});

describe('6m: send_email extra arguments (regression)', function () {
    test('the from argument maps to the fromAddress option', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->send_email('a@b.com', 'Hi', $msg, 'noreply@site.com');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->mail->send('a@b.com', 'Hi', \$msg, ['fromAddress' => 'noreply@site.com'])");
    });

    test('a true html flag sends the body as html', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->send_email('a@b.com', 'Hi', $msg, '', true);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->mail->send('a@b.com', 'Hi', '', ['html' => \$msg])");
    });

    test('a headers argument gets a TODO instead of vanishing', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->send_email('a@b.com', 'Hi', $msg, '', false, $headers);
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain('$headers')
            ->and($output)->toMatch('/TODO: FLICK MIGRATION.*headers/');
    });

    test('send_html_email keeps its from argument', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->send_html_email('a@b.com', 'Hi', $html, 'noreply@site.com');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("->mail->send('a@b.com', 'Hi', '', ['fromAddress' => 'noreply@site.com', 'html' => \$html])");
    });
});

describe('6n: honeypot with multiple forms (regression)', function () {
    test('each form keeps its own honeypot in its own constructor', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$search = new Formr();
$form->honeypot('hp_one');
$search->honeypot('hp_two');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toMatch('/\$form = new Flick\(\[[^\]]*\'honeypot\' => \'hp_one\'/')
            ->and($output)->toMatch('/\$search = new Flick\(\[[^\]]*\'honeypot\' => \'hp_two\'/')
            ->and($output)->toContain("//\$form->honeypot('hp_one');")
            ->and($output)->toContain("//\$search->honeypot('hp_two');");
    });

    test('a second honeypot on the same form is flagged, not silently dropped', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->honeypot('hp_one');
$form->honeypot('hp_two');
PHP;
        $output = $this->migrator->migrate($input);

        expect($output)->toContain("'honeypot' => 'hp_one'")
            ->and($output)->toContain("\$form->honeypot('hp_two');")
            ->and($output)->toMatch('/TODO: FLICK MIGRATION.*hp_two.*manually/');
    });
});

describe('multi-receiver service config (regression)', function () {
    /**
     * The constructor for a given receiver, as a single line.
     */
    $constructorFor = function (string $output, string $var): string {
        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, $var.' = new Flick(')) {
                return $line;
            }
        }

        return '';
    };

    test('each form keeps its own upload directory and neither line is dropped', function () use ($constructorFor) {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$search = new Formr('bootstrap');
$form->upload_dir = 'uploads/form/';
$search->upload_dir = 'uploads/search/';
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($constructorFor($output, '$form'))->toContain("'directory' => 'uploads/form/'")
            ->and($constructorFor($output, '$search'))->toContain("'directory' => 'uploads/search/'")
            // both values landed, so both source lines are gone
            ->and($output)->not->toContain('upload_dir');
    });

    test('two forms with different upload keys do not contaminate each other', function () use ($constructorFor) {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$search = new Formr('bootstrap');
$form->upload_dir = 'uploads/form/';
$search->upload_max_filesize = 5000000;
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($constructorFor($output, '$form'))->toContain("'directory' => 'uploads/form/'")
            ->and($constructorFor($output, '$form'))->not->toContain('maxFileSize')
            ->and($constructorFor($output, '$search'))->toContain("'maxFileSize' =>")
            ->and($constructorFor($output, '$search'))->not->toContain('directory');
    });

    test('a receiver whose merge cannot land keeps its line, commented out', function () use ($constructorFor) {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$search = new Formr();
$form->upload_dir = 'uploads/form/';
$search->upload_dir = 'uploads/search/';
PHP;
        $output = $this->migrator->migrate($input);

        // $search has no config array to merge into, so its value must
        // survive as a commented-out line rather than being deleted along
        // with the receiver whose merge did land.
        expect(lintPhp($output))->toBe(0)
            ->and($constructorFor($output, '$form'))->toContain("'directory' => 'uploads/form/'")
            ->and($output)->not->toContain("\$form->upload_dir = 'uploads/form/';")
            ->and($output)->toContain("// \$search->upload_dir = 'uploads/search/';")
            ->and($output)->toContain('TODO: FLICK MIGRATION - Add services.upload config to constructor');
    });

    test('two forms both sending mail each get their own mail config', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$search = new Formr('bootstrap');
$form->send_email('a@b.com', 'Hi', $msg);
$search->send_email('c@d.com', 'Yo', $other);
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and(substr_count($output, "'mail' =>"))->toBe(2);
    });

    test('mail and upload on two forms produce one services array each', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$search = new Formr('bootstrap');
$form->upload_dir = 'uploads/form/';
$search->upload_dir = 'uploads/search/';
$form->send_email('a@b.com', 'Hi', $msg);
$search->send_email('c@d.com', 'Yo', $other);
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and(substr_count($output, "'services' =>"))->toBe(2)
            ->and(substr_count($output, "'mail' =>"))->toBe(2)
            ->and(substr_count($output, "'upload' =>"))->toBe(2);
    });
});

describe('6o: duplicate services key (regression)', function () {
    test('mail and upload configs merge into a single services array', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$form->upload_dir = 'uploads/';
$form->send_email('a@b.com', 'Hi', $msg);
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and(substr_count($output, "'services' =>"))->toBe(1)
            ->and($output)->toContain("'mail' =>")
            ->and($output)->toContain("'upload' =>");
    });
});

describe('conversion interaction sweep (regression)', function () {
    test('kitchen-sink migration is lint-clean and idempotent', function () {
        $input = <<<'PHP'
<?php
$form = new Formr('bootstrap');
$search = new Formr();
$form->honeypot('hp_main');
$search->honeypot('hp_search');
$form->upload_dir = 'uploads/';
$form->action = 'process.php?a=1;b=2';
$name = $form->post('name', sprintf('%s label', $x), 'required|min[2]');
$email = $form->post('email',"Bob's email",'valid_email');
echo $form->create_form('Name(required), Email(valid_email)');
echo $form->fastform(['text' => 'fname,First:', 'submit' => 'submit,,Go']);
echo $form->text('username','Username','','user-id','class="form-control"');
echo $form->checkbox('news','News','yes','news-id','','','yes');
echo $form->checkbox('dyn','Dyn','x',$dynId,'class="c"','','checked');
echo $form->input_hidden('token');
if ($form->submit('mainForm')) {
    $sent = $form->submit() ? 1 : 0;
    $form->send_email('a@b.com', 'Hi', $msg, 'noreply@x.com', false, $headers);
}
if (preg_match('/^cat(dog|bird)$/', $y)) {}
$form->messages(getOpen(), getClose());
?>
<p>Don't miss out! Powered by Formr.</p>
<?php echo 'Formr rocks'; ?>
PHP;
        $once = $this->migrator->migrate($input);
        $twice = $this->migrator->migrate($once);

        expect(lintPhp($once))->toBe(0)
            ->and($twice)->toBe($once)
            ->and($once)->toContain("'Formr rocks'")
            ->and($once)->toContain('Powered by Formr');
    });
});

describe('6c: label with mixed quotes (regression)', function () {
    test('double-quoted label containing an apostrophe is dropped, not shifted into rules', function () {
        $input = <<<'PHP'
<?php
$form = new Formr();
$name = $form->post('name',"Bob's field",'required');
PHP;
        $output = $this->migrator->migrate($input);

        expect(lintPhp($output))->toBe(0)
            ->and($output)->toContain("->request('name', 'required')")
            ->and($output)->not->toContain("Bob's field");
    });
});
