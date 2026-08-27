# Formr to Flick Migration Tool

A CLI tool that automatically converts [Formr](https://formr.github.io) PHP code to [Flick](https://flickphp.com).

## When to Use

Use this tool when you have an existing codebase using the Formr library and want to upgrade to Flick. The tool handles:

- Namespace and class name conversions
- 60+ method name mappings
- Validation rule syntax transformation
- Property-to-config migrations

## Installation

Install it as a dev dependency in the project you're migrating — the tool only
rewrites source files, so it is not needed at runtime:

```bash
composer require --dev flickphp/migrate
```

## Usage

```bash
# Migrate a single file
./vendor/bin/migrate-formr /path/to/file.php

# Migrate an entire directory (recursive)
./vendor/bin/migrate-formr /path/to/directory

# Preview changes without modifying files
./vendor/bin/migrate-formr /path/to/file.php --dry-run
```

## Configuration

There is nothing to configure — the tool is a one-shot CLI. Its behavior is controlled entirely by flags:

| Flag | Description |
|------|-------------|
| `--dry-run`, `-n` | Preview the changes without writing any files |
| `--help`, `-h` | Show help |
| `--version`, `-v` | Show the version |

By default the tool rewrites files **in place**, writing a sibling `*.bak` next to each file before overwriting it so a bad run is recoverable. Only files that contain a real Formr class reference are processed; everything else is skipped. The command exits non-zero if any file could not be read or written.

---

## What Gets Converted

### Namespaces
- `Formr\Formr` → `Flick\Flick`
- `new Formr()` → `new Flick()`
- `new Formr('bootstrap')` → `new Flick(['views' => 'bootstrap'])`

### Methods (60+ mappings)

| Formr | Flick |
|-------|-------|
| `create_form()` | `create()` |
| `form_open()` | `open()` (arguments reordered, see below) |
| `form_close()` | `close()` |
| `input_text()` | `text()` |
| `input_email()` | `email()` |
| `input_password()` | `password()` |
| `input_hidden()` | `hidden()` |
| `input_checkbox()` | `checkbox()` |
| `input_radio()` | `radio()` |
| `checkbox_inline()` | `checkboxInline()` |
| `radio_inline()` | `radioInline()` |
| `input_select()` | `select()` |
| `error_message()` | `errorMessage()` |
| `in_errors()` | `hasError()` |
| `submit()` | `submitted()` (only inside an `if`/`while`/`elseif` condition) |
| `submit_button()` | `submit()` |
| `input_submit()` | `submit()` (arguments reordered, see below) |
| `input_button_submit()` | `submit()` (arguments reordered, see below) |
| `post()` | `request()` |
| `get()` | `request()` |
| `validate()` | `request()` |
| `send_email()` | `$form->mail->send()` |
| `send_html_email()` | `$form->mail->send(..., ['html' => ...])` |

> `textarea()` and `ok()` keep the same name in Flick, so they are left unchanged.

**Submit buttons move their arguments.** Formr's `input_submit($name, $label,
$value, $id, $string)` carries the button text in `$value`; Flick's
`submit($text, $attributes)` carries it first. The tool moves it, and passes
`$string` through as the attributes:

```php
$form->input_submit('submit', '', 'Send', '', 'class="btn"');
// becomes
$form->submit('Send', 'class="btn"');
```

`$name`, `$label` and `$id` have nowhere to go — Flick's `submit()` renders no
label and hard-codes `name="submit"` / `id="submit"` — so anything other than
Formr's own defaults is flagged with a TODO rather than dropped. That includes
the one-argument form: `input_submit('Send')` sets the *name*, not the text
(Formr renders a button that still says "Submit"), so it becomes `submit()`
plus a TODO about the lost name.

`submit_button($value)` already leads with the text, so it converts straight
across.

**Opening tags move their arguments too.** Both libraries have an `open()`, and
their signatures do not line up — Formr's `form_open($name, $id, $action,
$method, $string, $hidden)` against Flick's `open($action, $method,
$attributes)`. The tool moves the action into first place:

```php
$form->form_open('myform', 'myform', '/save', 'POST', 'class="row"');
// becomes
$form->open('/save', 'POST', 'class="row"');
```

`$name` and `$id` have nowhere to go — Flick takes the form id from constructor
config — so a non-default one is flagged with a TODO rather than dropped.

`form_open()` is Formr-only, so it is reshaped whatever its argument count. A
bare `open()` is only reshaped at four arguments or more, because Flick's own
three-argument shape is already correct and guessing either way would break a
working form.

**Selects and fields keep their extra arguments.** Formr's field methods carry
up to nine positional arguments where Flick's take four, so the surplus is
folded into Flick's attributes array rather than passed along, where PHP would
discard it without a word:

```php
$form->input_select('vt', 'Vaccine', '', '', '', '', $chosen, $options);
// becomes
$form->select('vt', 'Vaccine', $chosen, ['options' => $options]);

$form->input_email('email', 'Email', $user->email, '', 'disabled');
// becomes
$form->email('email', 'Email', $user->email, ['disabled' => true]);
```

### Validation Rules

| Formr | Flick |
|-------|-------|
| `required\|min[5]\|max[50]` | `required, min:5, max:50` |
| `valid_email` | `email` |
| `valid_url` | `url` |
| `alpha_numeric` | `alphaNumeric` |
| `min_length[5]` | `min:5` |
| `max_length[50]` | `max:50` |
| `greater_than[0]` | `greaterThan:0` |
| `less_than[100]` | `lessThan:100` |
| `is_numeric` | `numeric` |
| `valid_ip` | `ip` |
| `matches[field]` | `matches:field` |

### Inline Validation Syntax

```php
// Formr uses pipes and parentheses
$form->validate('Name(required|min[2]), Email(valid_email)');

// Flick uses commas and brackets
$form->request('Name[required, min:2], Email[email]');
```

> A single rule converts too — `Email(valid_email)` becomes `Email[email]`. What
> the tool will not touch is a parenthesized value it doesn't recognise as
> rules: `Category(electronics)` is left alone, because in Flick that syntax
> means a prebuilt dropdown, and guessing wrong either way breaks the form.
> Rules are converted, never invented: nothing gains a `required` it didn't have.

---

## What Gets Flagged with TODOs

The tool inserts `// TODO: FLICK MIGRATION` comments for items requiring manual attention:

### Pro-Required Features

Some methods map to [Flick Pro](https://flickphp.com/pro) services. Mail methods
are rewritten; reCAPTCHA calls are only flagged (the method name is kept):

| Formr Method | Result | Notes |
|--------------|--------|-------|
| `send_email($to, $subj, $body)` | `$form->mail->send($to, $subj, $body)` | Rewritten; configure the mail service in the constructor |
| `send_html_email($to, $subj, $html)` | `$form->mail->send($to, $subj, '', ['html' => $html])` | Rewritten; HTML body moved to the options array |
| `recaptcha_*()` | *(name kept)* | **Not** rewritten — flagged with a Pro TODO; convert to `$form->recaptcha->scripts()`/`->passed()` by hand |
| `upload($field)` | `$form->file($field)` | Rewritten to the core `file()` method (no Pro TODO); switch to `$form->upload->image()` if you want Pro image processing |

**Mail service configuration required:**
```php
$form = new Flick([
    'services' => [
        'mail' => [
            'fromAddress' => 'noreply@example.com',
            'mailer' => [
                'transport' => 'smtp',
                'host' => 'smtp.example.com',
                // ... other options
            ]
        ]
    ]
]);
```

### Validation Rules That Become Modifiers

| Formr Rule | Flick Modifier |
|------------|----------------|
| `hash` | `bcrypt` modifier |
| `sanitize_string` | `stripTags` modifier |
| `sanitize_email` | `sanitizeEmail` modifier |

### Properties With No Direct Equivalent

| Property | Migration Notes |
|----------|-----------------|
| `required = '*'` | Use CSS to style required fields |
| `inline_errors` | Configure in view templates |
| `custom_validation_messages` | Use per-field messages |

---

## Complete Before/After Examples

### Example 1: Contact Form

**Before (Formr):**
```php
<?php
use Formr\Formr;

$form = new Formr('bootstrap');
$form->action = '/contact';
$form->method = 'post';
$form->required = '*';

if ($form->submit()) {
    $name = $form->post('name', 'Name', 'required|min[2]');
    $email = $form->post('email', 'Email', 'required|valid_email');
    $message = $form->post('message', 'Message', 'required|min[10]');

    if ($form->ok()) {
        $form->send_email('admin@example.com', 'Contact Form', $message);
        echo 'Thank you for your message!';
    }
}

echo $form->form_open();
echo $form->input_text('name', 'Name');
echo $form->input_email('email', 'Email');
echo $form->textarea('message', 'Message');
echo $form->submit_button('Send');
echo $form->form_close();
```

**After (Flick):**
```php
<?php
use Flick\Flick;

$form = new Flick(['views' => 'bootstrap', 'action' => '/contact', 'services' => [
        'mail' => [
            // TODO: FLICK MIGRATION - Update these mail settings for your environment
            'fromAddress' => 'noreply@example.com',
            'fromName' => 'Your App Name',
            'mailer' => [
                'transport' => 'smtp',  // smtp, ses, mailgun, sendgrid, postmark, mailjet, mailtrap
                'host' => 'localhost',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => ''
            ]
        ]
    ]]);
// TODO: FLICK MIGRATION - 'method' has no direct Flick equivalent
// $form->method = 'post';
// TODO: FLICK MIGRATION - 'required' has no direct Flick equivalent
// $form->required = '*';

if ($form->submitted()) {
    $name = $form->request('name', 'required, min:2');
    $email = $form->request('email', 'required, email');
    $message = $form->request('message', 'required, min:10');

    if ($form->ok()) {
        /* TODO: FLICK MIGRATION - Mail service requires configuration in constructor (services.mail) */ $form->mail->send('admin@example.com', 'Contact Form', $message);
        echo 'Thank you for your message!';
    }
}

echo $form->open();
echo $form->text('name', 'Name');
echo $form->email('email', 'Email');
echo $form->textarea('message', 'Message');
echo $form->submit('Send');
echo $form->close();
```

> **Note:** The mail config block and the commented-out `method`/`required` properties are injected automatically with TODOs for you to review.

### Example 2: Registration Form

**Before (Formr):**
```php
<?php
use Formr\Formr;

$form = new Formr();

if ($form->submit()) {
    $data = $form->validate('
        username(required|alpha_numeric|min[3]|max[20]),
        email(required|valid_email),
        password(required|min[8]|hash),
        password_confirm(required|matches[password])
    ');

    if ($form->ok()) {
        // Save user to database
    }
}

echo $form->create_form('Username, Email, Password|password, Confirm Password|password');
```

**After (Flick):**
```php
<?php
use Flick\Flick;

$form = new Flick();

if ($form->submitted()) {
    $data = $form->request('
        username[required, alphaNumeric, min:3, max:20],
        email[required, email],
        password[required, min:8, bcrypt],
        password_confirm[required, matches:password]
    ');

    if ($form->ok()) {
        // Save user to database
    }
}

echo $form->create('Username, Email, Password|password, Confirm Password|password');
```

### Example 3: File Upload

**Before (Formr):**
```php
<?php
use Formr\Formr;

$form = new Formr();
$form->upload_dir = '/uploads/';
$form->upload_max_filesize = 5000000;
$form->upload_accepted_types = 'jpg,png,gif';

if ($form->submit()) {
    $file = $form->upload('photo');
    if ($form->ok()) {
        echo "File uploaded: " . $file;
    }
}
```

**After (Flick):**
```php
<?php
use Flick\Flick;

$form = new Flick();
// TODO: FLICK MIGRATION - 'upload_accepted_types' is now set per-upload via 'mime' option

// TODO: FLICK MIGRATION - Add services.upload config to constructor
// $form->upload_dir = '/uploads/';
// TODO: FLICK MIGRATION - Add services.upload config to constructor
// $form->upload_max_filesize = 5000000;
// TODO: FLICK MIGRATION - 'upload_accepted_types' is now set per-upload
// $form->upload_accepted_types = 'jpg,png,gif';

if ($form->submitted()) {
    $file = $form->file('photo');
    if ($form->ok()) {
        echo "File uploaded: " . $file;
    }
}
```

> **Note:** File upload needs the most hand-finishing. The migrator maps `upload()` to the core `file()` method and comments out every upload property with a TODO, so nothing is silently carried over — but it does not build a `services.upload` block into an empty `new Flick()`. Flick's key for the size limit is `maxFileSize`. Add the upload config yourself:
>
> ```php
> $form = new Flick([
>     'services' => [
>         'upload' => [
>             'directory' => '/uploads/',
>             'maxFileSize' => '5MB',
>         ]
>     ]
> ]);
> ```
>
> Then use the Pro image handler with a `mimeTypes` rule: `$form->upload->image('photo', ['mimeTypes:image/jpeg,image/png,image/gif'])`.

---

## After Migration

1. **Search for TODOs**: Find all migration comments in your codebase
   ```bash
   grep -r "TODO: FLICK MIGRATION" /path/to/project
   ```

2. **Address each TODO**: Review and fix each flagged item manually

3. **Install Flick Pro** (if needed): If you use email, uploads, or reCAPTCHA. Requires a license and the Flick Pro repository entry in composer.json — see [flickphp.com/pro](https://flickphp.com/pro) for setup.
   ```bash
   composer require flickphp/pro
   ```

4. **Test thoroughly**: Run your application and test all forms

5. **Remove migration package**:
   ```bash
   composer remove flickphp/migrate
   ```

---

## Troubleshooting

### "Method not found" errors after migration

The migration tool maps common methods, but some custom or less common Formr methods may not have direct equivalents.

**Solution:** Check the [Flick documentation](https://flickphp.com) for the equivalent method, or use the generic `input()` method with type attributes.

### Validation rules not working

Flick uses different syntax for validation rules:
- Commas instead of pipes: `required, email` not `required|valid_email`
- Colons for parameters: `min:5` not `min[5]`

**Solution:** Review your validation rules and ensure they use Flick syntax.

### Form values not persisting

Formr automatically persisted values; Flick asks you to opt in.

**Solution:** Add `'persistToSession' => true` to your Flick config:
```php
$form = new Flick(['persistToSession' => true]);
```

Not `'session'` — that key takes a session *adapter* (a `SessionInterface`
instance), and a `true` there is ignored.

### CSRF token errors

Flick has CSRF protection enabled by default, which Formr did not.

**Solution:** Ensure your forms include the hidden token fields (automatic with `create()` or `open()`), or disable CSRF if needed:
```php
$form = new Flick(['csrf' => false]);
```

### Upload methods not working

File uploads require Flick Pro's upload service.

**Solution:** Install Flick Pro and configure the upload service, or handle uploads manually with PHP's native `$_FILES`.

### reCAPTCHA not working

reCAPTCHA requires Flick Pro's recaptcha service.

**Solution:** Install Flick Pro and configure reCAPTCHA with your site/secret keys.

---

## Limitations

The migration tool cannot automatically handle:

- Custom Formr extensions or subclasses
- Complex conditional logic around Formr methods
- JavaScript integrations that reference Formr-specific attributes
- Database queries that store Formr-specific data formats

These require manual review and migration.

**Field labels are dropped from validation calls.** Formr's
`post($name, $label, $rules)` used `$label` in its error messages; Flick's
`request()` has no label concept, so messages fall back to the raw field name:

```
Formr:  "Email Confirmation does not match email"
Flick:  "confirm_email must be the same as email"
```

The values still validate correctly — only the wording changes. To restore it,
pass your own text through `request()`'s third argument:

```php
$form->request('confirm_email', 'matches:email', [
    'confirm_email' => ['matches' => 'Email Confirmation does not match Email'],
]);
```

**A form built in one file and used in another is flagged, not converted.**
There is no reliable way to tell which variable holds the form in a file that
never names the class, and guessing would rewrite unrelated objects. Such files
get a `TODO: FLICK MIGRATION` comment at the top and are otherwise left exactly
as they were. Search for that marker after a run.

**Formr vendored outside `vendor/` is skipped entirely.** A hand-copied `formr/`
directory beside your application code is Formr's own source, not yours, so
nothing in it is rewritten or annotated.

---

## Requirements

- PHP 8.3+

## See Also

- [Flick Documentation](https://flickphp.com) - Full Flick documentation
- [Flick Core](https://github.com/flickphp/flick) - Main Flick package
- [Flick Pro](https://flickphp.com/pro) - Premium services

## License

MIT License. See [LICENSE](LICENSE) for details.
