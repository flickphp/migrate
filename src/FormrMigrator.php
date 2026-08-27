<?php

declare(strict_types=1);

namespace Flick\Migrate;

/**
 * Formr to Flick Migration Tool
 *
 * Converts Formr PHP code to Flick, with inline TODO comments
 * for items requiring manual attention.
 */
class FormrMigrator
{
    private array $stats = [
        'namespaces' => 0,
        'methods' => 0,
        'validation_rules' => 0,
        'properties' => 0,
    ];

    /**
     * The single record of "something needs manual review". Every flag site
     * pushes its note here; the CLI's todo COUNT is derived from this list
     * (getStats()), so the summary number and the "Manual review needed"
     * bullets can never disagree — they used to be two unrelated accumulators.
     *
     * @var list<string>
     */
    private array $todos = [];

    // Receiver variables whose mail methods were converted, as a set keyed by
    // variable name (for per-receiver config injection). A single bool meant
    // only the first form in a file ever got its mail config.
    private array $mailReceivers = [];

    // Method mappings: Formr => Flick
    private array $methodMap = [
        // Form builders
        'create_form' => 'create',
        'create_form_multipart' => 'createMultipart',
        'form_open' => 'open',
        'form_open_multipart' => 'openMultipart',
        'form_close' => 'close',
        'open_multipart' => 'openMultipart',

        // Input methods (input_* prefix)
        'input_text' => 'text',
        'input_email' => 'email',
        'input_password' => 'password',
        'input_textarea' => 'textarea',
        'input_select' => 'select',
        'input_select_multiple' => 'selectMultiple',
        'input_checkbox' => 'checkbox',
        'input_checkbox_inline' => 'checkboxInline',
        'input_radio' => 'radio',
        'input_radio_inline' => 'radioInline',
        'input_hidden' => 'hidden',
        'input_file' => 'file',
        'input_upload' => 'file',
        'input_upload_multiple' => 'files',
        'input_submit' => 'submit',
        'input_button_submit' => 'submit',
        'input_color' => 'color',
        'input_date' => 'date',
        'input_datetime' => 'datetime',
        'input_datetime_local' => 'datetime',
        'input_month' => 'month',
        'input_number' => 'number',
        'input_range' => 'range',
        'input_search' => 'search',
        'input_tel' => 'tel',
        'input_time' => 'time',
        'input_url' => 'url',
        'input_week' => 'week',

        // Short aliases (some already match)
        'checkbox_inline' => 'checkboxInline',
        'radio_inline' => 'radioInline',
        'select_multiple' => 'selectMultiple',
        'file_multiple' => 'files',
        'upload_multiple' => 'files',
        'dropdown' => 'select',
        'dropdown_multiple' => 'selectMultiple',
        'upload' => 'file',

        // Buttons
        'submit_button' => 'submit',

        // Labels & Fieldsets
        'label_open' => 'labelOpen',
        'label_close' => 'labelClose',
        'fieldset_open' => 'fieldsetOpen',
        'fieldset_close' => 'fieldsetClose',

        // Messages
        'error_message' => 'errorMessage',
        'success_message' => 'successMessage',
        'warning_message' => 'warningMessage',
        'info_message' => 'infoMessage',

        // Errors
        'in_errors' => 'hasError',
        'add_to_errors' => 'addError',
        // Note: in_errors_if and in_errors_else have different signatures - see $noEquivalentMethods

        // Session/Utility
        'unset_session' => 'destroySession',
        'get_ip_address' => 'getIp',

        // Note: post, get, fastpost, validate have special handling in migrateRequestMethods()
        // because they need parameter transformation (label param dropped)

        // Debug
        'printr' => 'dump',

        // Short aliases for methods with same name but different case
        'datetime_local' => 'datetime',

        // Error methods
        'error' => 'getError',
        'errors' => 'getErrors',

        // Fast form methods
        'fastform' => 'create',
        'fastform_multipart' => 'createMultipart',
    ];

    // Methods that require Pro package
    private array $proMethods = [
        'recaptcha_passed',
        'recaptcha_head',
        'recaptcha_body',
    ];

    // Methods with no equivalent
    private array $noEquivalentMethods = [
        'button' => 'Use HTML <button> directly',
        // csrf is handled by $commentOutMethods
        'form_info' => 'No direct Flick equivalent',
        'heading' => 'Use HTML heading tags directly',
        // honeypot is handled by migrateHoneypotMethod()
        'in_errors_else' => 'Use hasError() with conditional logic',
        'in_errors_if' => 'Use hasError() with conditional logic',
        'info' => 'No direct Flick equivalent',
        'input_button' => 'Use HTML <button> directly',
        'input_image' => 'Image submit button. Use HTML <input type="image" src="..."> directly',
        'input_reset' => 'Use HTML <input type="reset"> directly',
        'insert_required_indicator' => 'Internal Formr utility - not needed',
        'is_array' => 'Use PHP is_array() instead',
        'is_in_brackets' => 'Internal Formr utility - not needed',
        'label' => 'Flick uses labelOpen() and labelClose() instead',
        'make_id' => 'Internal Formr utility - not needed',
        'reset_button' => 'Use HTML <input type="reset"> directly',
        'type_is_checkbox' => 'Internal Formr utility - not needed',
    ];

    // Validation rule mappings
    private array $ruleMap = [
        'valid_email' => 'email',
        'valid_url' => 'url',
        'valid_ip' => 'ip',
        'min_length' => 'min',
        'max_length' => 'max',
        'exact_length' => 'exact',
        'greater_than' => 'greaterThan',
        'greater_than_or_equal' => 'greaterThanOrEqual',
        'less_than' => 'lessThan',
        'less_than_or_equal' => 'lessThanOrEqual',
        'alpha_dash' => 'alphaDash',
        // Formr's alpha_numeric (letters+digits) maps exactly to Flick's
        // alphaNumeric rule.
        'alpha_numeric' => 'alphaNumeric',
        'alphanumeric' => 'alphaNumeric',
        'an' => 'alphaNumeric',
        'int' => 'integer',
        'is_numeric' => 'numeric',
        'in_array' => 'in',
        'not_regex' => 'notRegex',
        // Rules with same name but different syntax
        'matches' => 'matches',
        'before' => 'before',
        'after' => 'after',
        // Short forms
        'ml' => 'max',
        'el' => 'exact',
        'gt' => 'greaterThan',
        'gte' => 'greaterThanOrEqual',
        'lt' => 'lessThan',
        'lte' => 'lessThanOrEqual',
        'ad' => 'alphaDash',
    ];

    // Flick's native validation rule names (post-mapping). A converted token
    // not in this set is unknown to Flick and gets an inline TODO instead of
    // passing through silently.
    private array $flickValidRules = [
        'afterOrEqual', 'after', 'beforeOrEqual', 'before', 'between', 'confirmed',
        'greaterThanOrEqual', 'greaterThan', 'lessThanOrEqual', 'lessThan',
        'notMatches', 'matches', 'notIn', 'notRegex', 'regex', 'requiredWith',
        'strongPassword', 'accepted', 'boolean', 'creditCard', 'date', 'phone',
        'required', 'equals', 'exact', 'in', 'max', 'min', 'alphaDash', 'alpha',
        'email', 'integer', 'ipv4', 'ipv6', 'ip', 'json', 'numeric', 'url', 'uuid',
        // string modifiers are valid inline tokens too
        'bcrypt', 'hash', 'sanitizeChars', 'sanitizeEmail', 'sanitizeInt',
        'sanitizeUrl', 'slug', 'stripAlpha', 'stripNumeric', 'stripTags',
    ];

    // Rules that become modifiers
    private array $modifierRules = [
        'hash' => 'bcrypt',
        'sanitize_string' => 'stripTags',
        'sanitize_email' => 'sanitizeEmail',
        'sanitize_int' => 'sanitizeInt',
        'sanitize_url' => 'sanitizeUrl',
        'slug' => 'slug',
        'strip_numeric' => 'stripNumeric',
    ];

    // Rules with no equivalent
    private array $noEquivalentRules = [
        'allow_html' => 'Flick does not strip HTML by default, remove this rule',
        'md5' => 'MD5 hashing not supported, use bcrypt modifier',
        'sha1' => 'SHA1 hashing not supported, use bcrypt modifier',
    ];

    // Property to config mappings
    private array $propertyMap = [
        'action' => 'action',
        'id' => 'id',
        'honeypot' => 'honeypot',
        // 'session' is intentionally NOT here: Flick's session is a
        // SessionInterface object, not a config string, so it must fall
        // through to noEquivalentProperties and be commented out with a TODO.
    ];

    // Properties requiring Pro (non-upload)
    private array $proProperties = [
        'recaptcha_site_key',
        'recaptcha_secret_key',
        'recaptcha_score',
        'recaptcha_action_name',
        'recaptcha_use_curl',
    ];

    // Upload properties that map to services.upload config
    // Each entry has: 'config' (key in services.upload), 'path' (full dot-notation path)
    private array $uploadPropertyMap = [
        'upload_dir' => ['config' => 'directory', 'path' => 'services.upload.directory'],
        'upload_max_filesize' => ['config' => 'maxFileSize', 'path' => 'services.upload.maxFileSize'],
    ];

    // Upload properties that become per-upload options (not config)
    private array $uploadPerUploadProperties = [
        'upload_accepted_types' => 'mime',
        'upload_accepted_mimes' => 'mime',
        'upload_rename' => 'name',
        'upload_resize' => 'width/height',
    ];

    // Collected file field names for upload migration
    private array $fileFields = [];

    // Properties with no equivalent
    private array $noEquivalentProperties = [
        'charset',
        'comments',
        'controls',
        'custom_validation_messages',
        'delimiter',
        'doctype',
        'error_heading_plural',
        'error_heading_singular',
        'error_message',
        'format_rule_dates',
        'html_purifier',
        'info_message',
        'inline_errors',
        'inline_errors_class',
        'link_errors',
        'method',
        'minify',
        'name',
        'nl',
        'required',
        'required_indicator',
        'salt',
        'sanitize_html',
        'session', // Flick's session is a SessionInterface object, not a string
        'session_values',
        'show_valid',
        'strip_slashes',
        'submit',
        'success_message',
        'uploads',
        'version',
        'warning_message',
    ];

    // Dropdown name mappings: Formr method name => Flick file name
    private array $dropdownMap = [
        // Aliases
        'state' => 'states',
        'country' => 'countries',
        // Singular to plural
        'height' => 'heights',
        'age' => 'ages',
        // Underscore to camelCase
        'states_provinces' => 'statesProvinces',
        // Different names
        'months_alpha' => 'months',
        'cc_months' => 'months2',
        'cc_years' => 'yearsPlus',
    ];

    // Dropdowns with no equivalent
    private array $noEquivalentDropdowns = [
        'years_old' => 'Age verification dropdown - no direct Flick equivalent',
    ];

    // Prebuilt forms: Formr name => the Flick form template, its form id, and
    // the legacy HTML ids Formr's own examples used. One row per form - the
    // name map, the id map and the inline legacy-id list used to be three
    // separate structures that had to be edited together.
    private array $prebuiltForms = [
        'login' => ['path' => '/login', 'id' => 'form-login', 'legacyIds' => ['loginForm']],
        'short_contact' => ['path' => '/shortContact', 'id' => 'form-contact', 'legacyIds' => ['contactForm']],
        'signup' => ['path' => '/registration', 'id' => 'form-registration', 'legacyIds' => ['signupForm']],
    ];

    public function migrate(string $content): string
    {
        // Formr source from a Windows machine arrives CRLF, and every pass
        // below builds the lines it inserts with a literal "\n" -- so a TODO
        // added on its own line used to drop a lone LF into an otherwise CRLF
        // file and leave it with mixed endings. Normalising here and restoring
        // on the way out keeps all of them working in one line ending, rather
        // than threading the file's through every insertion site and hoping
        // none is missed. It fixes the read side too: strpos($content, "\n")
        // otherwise leaves a stray \r on the end of the line it finds.
        //
        // Only a file whose endings are already uniform is restored. One that
        // arrives mixed is passed through untouched, so this never rewrites the
        // endings of lines the migration itself did not change.
        $restoreCrlf = str_contains($content, "\r\n")
            && substr_count($content, "\n") === substr_count($content, "\r\n");

        if ($restoreCrlf) {
            $content = str_replace("\r\n", "\n", $content);
        }

        $this->todos = [];
        $this->fileFields = [];
        $this->mailReceivers = [];

        // Resolve the receivers ONCE, from the untouched input, and hand the
        // same value to every receiver-scoped pass. Re-deriving them per pass
        // meant an earlier pass could hide them: migrateConstructor() inserts
        // a TODO comment between "=" and "new" for a non-literal argument,
        // after which nothing downstream could find the receiver again.
        $receivers = $this->resolveReceivers($content);

        // Apply migrations in order
        // Constructor must run BEFORE namespace migration
        $content = $this->migrateConstructor($content);
        $content = $this->migrateNamespaces($content);
        $content = $this->migrateFormrArrays($content);
        $content = $this->migrateRequestMethods($content, $receivers);
        // Submit buttons must run BEFORE migrateMethods: the map renames
        // input_submit -> submit without touching the arguments, and the two
        // signatures put the button text in different slots.
        $content = $this->migrateSubmitMethods($content, $receivers);
        // form_open()/open() need reshaping, not renaming: Formr and Flick
        // both have open() and their argument orders are incompatible. Runs
        // before migrateMethods so the map never renames form_open past it.
        $content = $this->migrateFormrOpen($content, $receivers);
        $content = $this->migrateMethods($content, $receivers);
        // Checkbox/Radio migration must run AFTER migrateMethods (input_checkbox -> checkbox)
        $content = $this->migrateCheckboxRadioMethods($content, $receivers);
        // Text-family folding must run AFTER migrateMethods (input_text -> text)
        $content = $this->migrateTextInputMethods($content, $receivers);
        // Select migration must run AFTER migrateMethods (input_select -> select)
        $content = $this->migrateSelectMethod($content, $receivers);
        $content = $this->migrateDropdowns($content, $receivers);
        $content = $this->migrateValidationRules($content, $receivers);
        // Honeypot must run BEFORE migrateProperties to add config to constructor
        $content = $this->migrateHoneypotMethod($content, $receivers);
        $content = $this->migrateProperties($content, $receivers);
        // Prebuilt forms must run AFTER migrateProperties (id is now in constructor)
        $content = $this->migratePrebuiltForms($content, $receivers);
        // Mail migrations convert send_email/send_html_email to mail->send()
        $content = $this->migrateMailMethods($content, $receivers);
        // Mail config injection must run AFTER migrateMailMethods
        $content = $this->migrateMailConfig($content);
        // Upload migrations must run AFTER migrateProperties and migrateMethods
        $content = $this->migrateUploadConfig($content, $receivers);
        $content = $this->migrateFileUploadMethods($content, $receivers);
        $content = $this->migrateUploadPropertyReads($content, $receivers);
        // Handle reads of properties with no equivalent
        $content = $this->migrateNoEquivalentPropertyReads($content, $receivers);
        // Runs last: flags files whose form instance is constructed elsewhere
        // (receiver rewrites were skipped for them, see Receivers)
        $content = $this->flagExternalInstanceUsage($content, $receivers);

        return $restoreCrlf ? str_replace("\n", "\r\n", $content) : $content;
    }

    /**
     * Move a submit button's text into the slot Flick reads it from.
     *
     * Formr: input_submit($name, $label, $value, $id, $string)  -- text is $value
     * Flick: submit($text, $attributes)                         -- text is $text
     *
     * The generic method map renames these but leaves the arguments alone, so
     * input_submit('send', '', 'Send') became submit('send', '', 'Send'): a
     * button labelled "send", with a third argument submit() does not take.
     * Runs before migrateMethods() so it sees the Formr names.
     *
     * $string is a raw attribute string, which Flick's $attributes accepts.
     * $name, $label and $id have nowhere to go — submit() hard-codes
     * name="submit" and id="submit" and renders no label — so anything other
     * than Formr's own defaults is flagged rather than dropped silently.
     * submit_button($value) already leads with the text, so the map handles it.
     */
    private function migrateSubmitMethods(string $content, Receivers $receivers): string
    {
        foreach (['input_submit', 'input_button_submit'] as $method) {
            $content = $this->replaceBalancedCall($content, $method, function ($var, $args) use ($method, $receivers) {
                if (! $receivers->matches($var)) {
                    return null; // Not a known Formr receiver; leave as-is.
                }

                $args = array_map('trim', $args);

                // The array-call form carries the text as $data['value'], a
                // different shape entirely. Renaming it would produce
                // submit(['type' => ...]), which is not a valid call, so it is
                // flagged for hand-migration instead of guessed at.
                if (isset($args[0]) && (str_starts_with($args[0], '[') || str_starts_with($args[0], 'array('))) {
                    $this->todos[] = "{$method}() was called with an array; convert it to submit(\$text) by hand";

                    return "/* TODO: FLICK MIGRATION - {$method}() was called with an array; convert it to submit(\$text) by hand */ "
                        .$var.'->'.$method.'('.implode(',', $args).')';
                }

                $emptyLiterals = ['', "''", '""'];
                $name = $args[0] ?? '';
                $label = $args[1] ?? '';
                $value = $args[2] ?? '';
                $id = $args[3] ?? '';
                $string = $args[4] ?? '';

                $this->stats['methods']++;

                $todoPrefix = '';
                // Formr defaults the name to 'submit', which is also what Flick
                // renders, so only a name the developer actually chose is news.
                foreach ([
                    ['name', $name, ["'submit'", '"submit"']],
                    ['label', $label, []],
                    ['id', $id, ["'submit'", '"submit"']],
                ] as [$argName, $argValue, $defaults]) {
                    if (in_array($argValue, $emptyLiterals, true) || in_array($argValue, $defaults, true)) {
                        continue;
                    }

                    $note = "{$method}() \${$argName} ({$argValue}) has no Flick equivalent: submit() renders no name, label or custom id";
                    $todoPrefix .= "/* TODO: FLICK MIGRATION - {$note} */ ";
                    $this->todos[] = $note;
                }

                // An empty $value means Formr fell back to its own default
                // label, which is the same word submit() defaults to.
                $callArgs = in_array($value, $emptyLiterals, true) ? [] : [$value];

                if (! in_array($string, $emptyLiterals, true)) {
                    if ($callArgs === []) {
                        $callArgs[] = "'Submit'";
                    }
                    $callArgs[] = $string;
                }

                return $todoPrefix.$var.'->submit('.implode(', ', $callArgs).')';
            });
        }

        return $content;
    }

    /**
     * Convert Formr's 7-argument checkbox/radio to Flick's 4-argument format.
     *
     * Formr: checkbox($name, $label, $value, $id, $attributes, $help, $checked)
     * Flick: checkbox($name, $label, $value, $attributes)
     *
     * The 4th argument in Flick is an array that can contain 'id' and 'checked'.
     */
    /**
     * Reshape Formr's opening form tag into Flick's.
     *
     *   Formr: form_open($name, $id, $action, $method, $string, $hidden)
     *   Formr: open(...)        -- same signature, an alias
     *   Flick: open($action, $method, $attributes)
     *
     * The method map renames form_open to open but leaves the arguments
     * alone, and a source that already said open() needs no rename at all --
     * so either way Formr's action landed in Flick's attributes slot and the
     * form rendered as
     *
     *   <form action="/" method="" id="myForm" index.php?q=admin>
     *
     * with the wrong target, an empty method (which browsers treat as GET)
     * and the URL emitted as a bare attribute.
     *
     * form_open is Formr-only, so any argument count is unambiguously Formr's
     * signature. A bare open() is ambiguous below four arguments, because
     * Flick's own three-argument shape is already correct, so those are left
     * untouched rather than guessed at.
     *
     * $name and $id have nowhere to go -- Flick takes the form id from
     * constructor config -- so a non-default one is flagged rather than
     * dropped in silence.
     *
     * Runs before migrateMethods() and renames form_open itself, so the
     * method map never sees it.
     */
    private function migrateFormrOpen(string $content, Receivers $receivers): string
    {
        foreach (['form_open' => 1, 'open' => 4] as $method => $minArgs) {
            $content = $this->replaceOutsideCommentsAndHeredocs(
                $content,
                fn (string $code) => $this->replaceBalancedCall($code, $method, function ($var, $args) use ($receivers, $minArgs) {
                    if (! $receivers->matches($var) || count($args) < $minArgs) {
                        return null;
                    }

                    $args = array_map('trim', $args);
                    $isBlank = static fn (string $a): bool => $a === "''" || $a === '""' || $a === '';

                    $name = $args[0] ?? "''";
                    $id = $args[1] ?? "''";
                    $action = $args[2] ?? "''";
                    $formMethod = $args[3] ?? "''";
                    $string = $args[4] ?? "''";

                    // Build only as many arguments as carry meaning, padding
                    // with Flick's own defaults when a later one is needed.
                    $out = [];
                    if (! $isBlank($action)) {
                        $out[] = $action;
                    }
                    if (! $isBlank($formMethod)) {
                        if ($out === []) {
                            $out[] = "'/'";
                        }
                        $out[] = $formMethod;
                    }
                    if (! $isBlank($string)) {
                        if ($out === []) {
                            $out[] = "'/'";
                        }
                        if (count($out) < 2) {
                            $out[] = "'POST'";
                        }
                        $out[] = $string;
                    }

                    $this->stats['methods']++;

                    $call = $var.'->open('.implode(', ', $out).')';

                    $lost = [];
                    if (! $isBlank($name)) {
                        $lost[] = 'name '.$name;
                    }
                    if (! $isBlank($id)) {
                        $lost[] = 'id '.$id;
                    }

                    if ($lost === []) {
                        return $call;
                    }

                    $note = 'open() '.implode(' and ', $lost)
                        .' has no Flick equivalent: the form id comes from constructor config';
                    $this->todos[] = $note;

                    return '/* TODO: FLICK MIGRATION - '.$note.' */ '.$call;
                })
            );
        }

        return $content;
    }

    private function migrateCheckboxRadioMethods(string $content, Receivers $receivers): string
    {
        $methods = ['checkbox', 'checkboxInline', 'radio', 'radioInline'];

        // Scope to known Formr receivers so e.g. a UI builder's checkbox()
        // is never rewritten (see Receivers).
        $recv = $receivers->pattern();

        // A single quoted-string sub-pattern that accepts single- OR
        // double-quoted literals, so an attribute string like class="custom"
        // (double quotes inside a single-quoted PHP string) is captured whole
        // instead of being truncated at the inner quote (full-mig-checkbox7).
        $q = "('[^']*'|\"[^\"]*\")";

        foreach ($methods as $method) {
            // Formr signature: (name, label, value, id, attributes, help, checked)
            // Flick signature: (name, label, value, [id + attrs + checked])
            // Handle 7-, 6-, 5-, and 4-argument forms. Longest first so a
            // longer call is never partially matched by a shorter pattern.
            $arg = "\\s*,\\s*{$q}";

            // 7 args: name, label, value, id, attrs, help, checked
            $content = preg_replace_callback(
                '/('.$recv.')->'.$method.'\s*\(\s*'.$q.$arg.$arg.$arg.$arg.$arg.$arg.'\s*\)/',
                fn ($m) => $this->buildFoldedFieldCall($m[1], $method, $m[2], $m[3], $m[4], substr($m[5], 1, -1), substr($m[6], 1, -1), substr($m[8], 1, -1)),
                $content
            );

            // 6 args: name, label, value, id, attrs, help (no checked)
            $content = preg_replace_callback(
                '/('.$recv.')->'.$method.'\s*\(\s*'.$q.$arg.$arg.$arg.$arg.$arg.'\s*\)/',
                fn ($m) => $this->buildFoldedFieldCall($m[1], $method, $m[2], $m[3], $m[4], substr($m[5], 1, -1), substr($m[6], 1, -1), ''),
                $content
            );

            // 5 args: name, label, value, id, attrs
            $content = preg_replace_callback(
                '/('.$recv.')->'.$method.'\s*\(\s*'.$q.$arg.$arg.$arg.$arg.'\s*\)/',
                fn ($m) => $this->buildFoldedFieldCall($m[1], $method, $m[2], $m[3], $m[4], substr($m[5], 1, -1), substr($m[6], 1, -1), ''),
                $content
            );

            // 4 args: name, label, value, id
            $content = preg_replace_callback(
                '/('.$recv.')->'.$method.'\s*\(\s*'.$q.$arg.$arg.$arg.'\s*\)/',
                fn ($m) => $this->buildFoldedFieldCall($m[1], $method, $m[2], $m[3], $m[4], substr($m[5], 1, -1), '', ''),
                $content
            );
        }

        // Mixed literal/non-literal calls with 4+ args fall through the
        // all-literal patterns above and used to keep the Formr argument
        // list, silently dropping id/class/checked at runtime (6k). Fold what
        // can be folded — a non-literal id becomes an embedded expression;
        // when the attribute string or checked flag is not a literal, flag
        // the call with a TODO instead of silently mangling it.
        foreach ($methods as $method) {
            $content = $this->foldCheckboxRadioNonLiteral($content, $recv, $method);
        }

        return $content;
    }

    /**
     * Balanced-parse fallback for checkbox/radio calls the all-literal
     * patterns could not fold (at least one non-literal argument).
     */
    private function foldCheckboxRadioNonLiteral(string $content, string $recv, string $method): string
    {
        if (! preg_match_all('/('.$recv.')->'.$method.'\s*\(/', $content, $m, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $isLiteral = fn (string $s): bool => preg_match('/^([\'"]).*\1$/s', $s) === 1;

        for ($i = count($m[0]) - 1; $i >= 0; $i--) {
            [$match, $at] = $m[0][$i];
            $var = $m[1][$i][0];

            if ($this->isAlreadyMigrated($content, $at)) {
                continue;
            }

            $open = $at + strlen($match) - 1;
            $close = $this->findMatchingParen($content, $open);
            if ($close === -1) {
                continue;
            }

            $args = array_map('trim', $this->splitTopLevelArgs(substr($content, $open + 1, $close - $open - 1)));
            if (count($args) < 4 || count($args) > 7) {
                continue; // Flick-shaped already (<= 3) or not a Formr signature.
            }
            if (str_starts_with($args[3], '[')) {
                continue; // 4th argument is already a Flick attributes array.
            }

            $name = $args[0];
            $value = $args[2];
            $idArg = $args[3];
            $attrsArg = $args[4] ?? "''";
            $checkedArg = $args[6] ?? "''"; // args[5] is Formr's help arg - dropped

            $parts = [];
            $foldable = true;

            if ($isLiteral($idArg)) {
                $id = substr($idArg, 1, -1);
                $fieldName = $isLiteral($name) ? substr($name, 1, -1) : null;
                $fieldNameBase = $fieldName === null ? null : rtrim($fieldName, '[]');
                if ($id !== '' && $id !== $fieldName && $id !== $fieldNameBase) {
                    $parts[] = "'id' => '".str_replace("'", "\\'", $id)."'";
                }
            } else {
                $parts[] = "'id' => {$idArg}";
            }

            if ($isLiteral($attrsArg)) {
                foreach ($this->parseFormrAttributes(substr($attrsArg, 1, -1)) as $key => $attrValue) {
                    $parts[] = $attrValue === true
                        ? "'{$key}' => true"
                        : "'{$key}' => '".str_replace("'", "\\'", $attrValue)."'";
                }
            } else {
                $foldable = false;
            }

            if ($isLiteral($checkedArg)) {
                $checked = substr($checkedArg, 1, -1);
                $checkedLower = strtolower($checked);
                if ($checkedLower === 'checked' || $checkedLower === 'selected') {
                    $parts[] = "'checked' => true";
                } elseif ($checked !== '') {
                    if ($isLiteral($value) && substr($value, 1, -1) === $checked) {
                        $parts[] = "'checked' => true";
                    } elseif (! $isLiteral($value)) {
                        $foldable = false; // Checked-by-value against a runtime value.
                    }
                    // Literal value != literal checked: unchecked, add nothing.
                }
            } else {
                $foldable = false;
            }

            if (! $foldable) {
                $this->todos[] = "fold Formr's id/attributes/checked arguments into {$method}()'s 4th array argument manually";
                $todo = "/* TODO: FLICK MIGRATION - fold Formr's id/attributes/checked arguments into {$method}()'s 4th array argument manually */ ";
                $content = substr($content, 0, $at).$todo.substr($content, $at);

                continue;
            }

            $this->stats['methods']++;
            $call = "{$var}->{$method}({$args[0]}, {$args[1]}, {$args[2]}"
                .($parts === [] ? '' : ', ['.implode(', ', $parts).']').')';
            $content = substr($content, 0, $at).$call.substr($content, $close + 1);
        }

        return $content;
    }

    /**
     * Build a Flick field call (checkbox/radio/select and the text family)
     * from Formr's positional arguments, folding id + HTML attributes +
     * checked into the 4th-argument array. $name/$label/$value keep their
     * original quoted form; $id/$attrs/$checked are already unquoted. Formr's
     * help/inline argument is intentionally dropped; text-family callers pass
     * '' for $checked.
     */
    private function buildFoldedFieldCall(
        string $var,
        string $method,
        string $name,
        string $label,
        string $value,
        string $id,
        string $formrAttrs,
        string $checked
    ): string {
        $fieldName = substr($name, 1, -1);
        $fieldNameBase = rtrim($fieldName, '[]');

        $parts = [];

        // id, unless redundant with the field name
        if ($id !== '' && $id !== $fieldName && $id !== $fieldNameBase) {
            $parts[] = "'id' => '".str_replace("'", "\\'", $id)."'";
        }

        // Parsed HTML attributes (class="x" disabled ...)
        foreach ($this->parseFormrAttributes($formrAttrs) as $key => $attrValue) {
            if ($attrValue === true) {
                $parts[] = "'{$key}' => true";
            } else {
                $parts[] = "'{$key}' => '".str_replace("'", "\\'", $attrValue)."'";
            }
        }

        // Formr marks the box checked when the 7th argument is the literal
        // 'checked'/'selected' OR equals the checkbox value (checked-by-value,
        // see Formr's _create_input). Both must fold to 'checked' => true.
        $checkedLower = strtolower($checked);
        if ($checkedLower === 'checked' || $checkedLower === 'selected'
            || ($checked !== '' && $checked === substr($value, 1, -1))) {
            $parts[] = "'checked' => true";
        }

        $this->stats['methods']++;

        if (empty($parts)) {
            return "{$var}->{$method}({$name}, {$label}, {$value})";
        }

        return "{$var}->{$method}({$name}, {$label}, {$value}, [".implode(', ', $parts).'])';
    }

    // Text-like input methods sharing Formr's (name, label, value, id,
    // attributes, inline) signature, post-rename. select/checkbox/radio have
    // their own passes; hidden and submit have different signatures.
    private array $textInputMethods = ['text', 'email', 'password', 'textarea', 'number', 'tel', 'url', 'date', 'datetime', 'month', 'search', 'time', 'week', 'color', 'range', 'file'];

    /**
     * Fold Formr's id + HTML-attribute arguments of text-like inputs into
     * Flick's 4th-argument array, mirroring the checkbox/radio pass. Without
     * this, text('u','U','','user-id','class="x"') reached Flick with the id
     * in the attributes slot and the real attribute string silently ignored.
     */
    private function migrateTextInputMethods(string $content, Receivers $receivers): string
    {
        $recv = $receivers->pattern();
        $q = "('[^']*'|\"[^\"]*\")";
        $arg = "\\s*,\\s*{$q}";

        foreach ($this->textInputMethods as $method) {
            // 6 args: name, label, value, id, attrs, inline (inline dropped)
            $content = preg_replace_callback(
                '/('.$recv.')->'.$method.'\s*\(\s*'.$q.$arg.$arg.$arg.$arg.$arg.'\s*\)/',
                fn ($m) => $this->buildFoldedFieldCall($m[1], $method, $m[2], $m[3], $m[4], substr($m[5], 1, -1), substr($m[6], 1, -1), ''),
                $content
            );

            // 5 args: name, label, value, id, attrs
            $content = preg_replace_callback(
                '/('.$recv.')->'.$method.'\s*\(\s*'.$q.$arg.$arg.$arg.$arg.'\s*\)/',
                fn ($m) => $this->buildFoldedFieldCall($m[1], $method, $m[2], $m[3], $m[4], substr($m[5], 1, -1), substr($m[6], 1, -1), ''),
                $content
            );

            // 4 args: name, label, value, id
            $content = preg_replace_callback(
                '/('.$recv.')->'.$method.'\s*\(\s*'.$q.$arg.$arg.$arg.'\s*\)/',
                fn ($m) => $this->buildFoldedFieldCall($m[1], $method, $m[2], $m[3], $m[4], substr($m[5], 1, -1), '', ''),
                $content
            );
        }

        return $content;
    }

    /**
     * Convert Formr's 8-argument select() to Flick's 4-argument select().
     *
     * Formr: select($name, $label, $default, $id, $attributes, $help, $selected, $options)
     * Flick: select($name, $label, $value, $attributes)
     */
    private function migrateSelectMethod(string $content, Receivers $receivers): string
    {
        // Handle both select and selectMultiple
        $methods = ['select', 'selectMultiple'];

        // select() is a common method name (query builders, collections), so
        // only rewrite calls on known Formr receivers (see Receivers).
        $recv = $receivers->pattern();

        foreach ($methods as $method) {
            // Pattern for 8 string literal arguments
            // Use alternation to match single-quoted or double-quoted strings
            // This allows 'class="form-select"' (double quotes inside single quotes)
            $pattern = '/('.$recv.')->'.$method.'\s*\(\s*'.
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 1: name
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 2: label
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 3: default/value
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 4: id
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 5: attributes (string)
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 6: help (DROP)
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 7: selected (DROP)
                "('[^']*'|\"[^\"]*\")".         // 8: options (string)
                '\s*\)/';

            $content = preg_replace_callback($pattern, function ($matches) use ($method) {
                $var = $matches[1];
                $name = $matches[2];
                $label = $matches[3];
                $value = $matches[4];
                // Strip only outer quotes (not all quotes) using substr
                $id = substr($matches[5], 1, -1);
                $formrAttrs = substr($matches[6], 1, -1);
                $fieldName = substr($matches[2], 1, -1);
                // Strip [] suffix for comparison (e.g., 'sizes[]' -> 'sizes')
                $fieldNameBase = rtrim($fieldName, '[]');
                $options = substr($matches[9], 1, -1);

                // Skip id if it's the same as the field name (redundant)
                if ($id === $fieldName || $id === $fieldNameBase) {
                    $id = '';
                }

                // Parse Formr attributes and build Flick's 4th argument
                $parsedAttrs = $this->parseFormrAttributes($formrAttrs);
                $attrsStr = $this->buildFlickAttributesString($parsedAttrs, $options, $id);

                $this->stats['methods']++;

                return "{$var}->{$method}({$name}, {$label}, {$value}, {$attrsStr})";
            }, $content);

            // Pattern for 8 args where last arg is a variable (e.g., $colorOptions)
            $patternVar = '/('.$recv.')->'.$method.'\s*\(\s*'.
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 1: name
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 2: label
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 3: default/value
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 4: id
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 5: attributes (string)
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 6: help (DROP)
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 7: selected (DROP)
                '(\$\w+)'.                     // 8: options (variable)
                '\s*\)/';

            $content = preg_replace_callback($patternVar, function ($matches) use ($method) {
                $var = $matches[1];
                $name = $matches[2];
                $label = $matches[3];
                $value = $matches[4];
                // Strip only outer quotes (not all quotes) using substr
                $id = substr($matches[5], 1, -1);
                $formrAttrs = substr($matches[6], 1, -1);
                $fieldName = substr($matches[2], 1, -1);
                // Strip [] suffix for comparison (e.g., 'sizes[]' -> 'sizes')
                $fieldNameBase = rtrim($fieldName, '[]');
                $optionsVar = $matches[9]; // Variable like $colorOptions

                // Skip id if it's the same as the field name (redundant)
                if ($id === $fieldName || $id === $fieldNameBase) {
                    $id = '';
                }

                // Parse Formr attributes and build Flick's 4th argument
                $parsedAttrs = $this->parseFormrAttributes($formrAttrs);
                $attrsStr = $this->buildFlickAttributesString($parsedAttrs, $optionsVar, $id, true, false);

                $this->stats['methods']++;

                return "{$var}->{$method}({$name}, {$label}, {$value}, {$attrsStr})";
            }, $content);

            // Pattern for 8 args where last arg is an inline array (multiline supported)
            // Example: $form->select('priority', 'Label:', '', 'priority', '', '', '', [...])
            $patternArray = '/('.$recv.')->'.$method.'\s*\(\s*'.
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 1: name
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 2: label
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 3: default/value
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 4: id
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 5: attributes
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 6: help (DROP)
                "('[^']*'|\"[^\"]*\")\s*,\s*".  // 7: selected (DROP)
                '(\[[\s\S]*?\])'.              // 8: options (inline array, multiline)
                '\s*\)/';

            $content = preg_replace_callback($patternArray, function ($matches) use ($method) {
                $var = $matches[1];
                $name = $matches[2];
                $label = $matches[3];
                $value = $matches[4];
                // Strip only outer quotes (not all quotes) using substr
                $id = substr($matches[5], 1, -1);
                $formrAttrs = substr($matches[6], 1, -1);
                $fieldName = substr($matches[2], 1, -1);
                $fieldNameBase = rtrim($fieldName, '[]');
                $optionsArray = $matches[9]; // The inline array

                // Skip id if it's the same as the field name (redundant)
                if ($id === $fieldName || $id === $fieldNameBase || empty($id)) {
                    $id = '';
                }

                // Parse Formr attributes and build Flick's 4th argument
                $parsedAttrs = $this->parseFormrAttributes($formrAttrs);
                $attrsStr = $this->buildFlickAttributesString($parsedAttrs, $optionsArray, $id, false, true);

                $this->stats['methods']++;

                return "{$var}->{$method}({$name}, {$label}, {$value}, {$attrsStr})";
            }, $content);

            // Fallback for everything the three literal patterns cannot match:
            // any of arguments 1-7 being an expression rather than a string
            // literal. Those calls fell through to the plain rename, so Flick
            // received eight positional arguments and read the empty argument
            // 4 as a dropdown loader name -- a hard throw, with the options
            // array in argument 8 never reachable.
            //
            // Formr's $value (3) and $selected (7) both pre-select an option,
            // so the Flick value is argument 3 when it carries something and
            // argument 7 otherwise. Dropping argument 7 outright would lose
            // the user's saved selection on exactly the shape that is most
            // common: a literal '' for $value and an expression for $selected.
            $content = $this->replaceBalancedCall($content, $method, function ($var, $args) use ($method, $receivers) {
                if (! $receivers->matches($var) || count($args) < 5) {
                    return null;
                }

                $args = array_map('trim', $args);
                $isBlank = static fn (string $a): bool => $a === "''" || $a === '""' || $a === '';

                $name = $args[0];
                $label = $args[1] ?? "''";
                $value = $args[2] ?? "''";
                $id = $args[3] ?? "''";
                $formrAttrs = $args[4] ?? "''";
                $selected = $args[6] ?? "''";
                $options = $args[7] ?? "''";

                $effectiveValue = $isBlank($value) ? $selected : $value;
                if ($isBlank($effectiveValue)) {
                    $effectiveValue = "''";
                }

                // An id equal to the field name is what Flick emits anyway.
                $idLiteral = $isBlank($id) ? '' : trim($id, '\'"');
                $fieldName = trim($name, '\'"');
                if ($idLiteral === $fieldName || $idLiteral === rtrim($fieldName, '[]')) {
                    $idLiteral = '';
                }

                $attrPairs = [];
                if (! $isBlank($options)) {
                    $attrPairs[] = "'options' => ".$options;
                }
                if ($idLiteral !== '') {
                    $attrPairs[] = "'id' => '".$idLiteral."'";
                }
                if (! $isBlank($formrAttrs)) {
                    $attrPairs[] = "'attributes' => ".$formrAttrs;
                }

                $this->stats['methods']++;

                $attrsStr = $attrPairs === [] ? '[]' : '['.implode(', ', $attrPairs).']';

                return "{$var}->{$method}({$name}, {$label}, {$effectiveValue}, {$attrsStr})";
            });
        }

        return $content;
    }

    /**
     * Convert Formr dropdown names to Flick dropdown names.
     *
     * Only converts dropdown names in specific contexts:
     * - In ['options' => 'dropdown_name'] (Flick format)
     * - As the 8th argument of Formr's select() (before migration)
     */
    private function migrateDropdowns(string $content, Receivers $receivers): string
    {
        // select() receivers are scoped so unrelated objects (query builders
        // etc.) never get their arguments renamed. The 'options' => array
        // rewrites below are value-based, so they are traced instead: they
        // only apply inside arrays that belong to the form (arguments of a
        // call on a Formr receiver, or variables passed to a form builder).
        $recv = $receivers->pattern();

        // Convert dropdown names in 'options' => 'name' context (Flick array
        // format). Single combined pass so every match offset is valid
        // against the regions computed on the same content.
        $regions = $this->formArrayRegions($content, $recv);
        $dropdownAlts = implode('|', array_map(fn ($k) => preg_quote($k, '/'), array_keys($this->dropdownMap)));
        $renamed = 0;

        $content = preg_replace_callback(
            "/(['\"]options['\"]\\s*=>\\s*['\"])(".$dropdownAlts.")(['\"])/",
            function ($m) use ($regions, &$renamed) {
                if (! $this->offsetWithinRegions($m[0][1], $regions)) {
                    return $m[0][0];
                }

                $renamed++;

                return $m[1][0].$this->dropdownMap[$m[2][0]].$m[3][0];
            },
            $content,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );
        $this->stats['methods'] += $renamed;

        // Convert dropdown names in Flick's 4-arg select (4th argument simple string)
        // Pattern: ->select('name', 'label', 'value', 'dropdown_name')
        foreach ($this->dropdownMap as $formr => $flick) {
            // Match 4th argument pattern in select() - exactly 3 commas before the dropdown name
            $pattern = '/('.$recv."->select\\s*\\([^,]+,[^,]+,[^,]+,\\s*['\"])".
                preg_quote($formr, '/')."(['\"]\\s*\\))/";
            $replacement = "$1{$flick}$2";

            $newContent = preg_replace($pattern, $replacement, $content);
            if ($newContent !== $content) {
                $this->stats['methods']++;
                $content = $newContent;
            }
        }

        // Convert dropdown names in Formr's 8-arg select (8th argument position)
        // This handles cases where migrateSelectMethod didn't run yet or didn't match
        foreach ($this->dropdownMap as $formr => $flick) {
            // Match the 8th argument pattern in select() - 7 commas before the dropdown name
            $pattern = '/('.$recv."->select\\s*\\([^)]*,[^)]*,[^)]*,[^)]*,[^)]*,[^)]*,[^)]*,\\s*['\"])".
                preg_quote($formr, '/')."(['\"]\\s*\\))/";
            $replacement = "$1{$flick}$2";

            $newContent = preg_replace($pattern, $replacement, $content);
            if ($newContent !== $content) {
                $this->stats['methods']++;
                $content = $newContent;
            }
        }

        // Flag dropdowns with no equivalent in 'options' context (array
        // format) — traced to form arrays and guarded so re-runs never stack
        // duplicate TODO lines.
        foreach ($this->noEquivalentDropdowns as $dropdown => $note) {
            $regions = $this->formArrayRegions($content, $recv);
            $subject = $content;

            $content = preg_replace_callback(
                "/^(\\s*)(.*)(['\"]options['\"]\\s*=>\\s*['\"])".preg_quote($dropdown, '/')."(['\"].*)/m",
                function ($m) use ($dropdown, $note, $regions, $subject) {
                    if (! $this->offsetWithinRegions($m[3][1], $regions)
                        || $this->isAlreadyMigrated($subject, $m[3][1])) {
                        return $m[0][0];
                    }

                    $this->todos[] = "'{$dropdown}': {$note}";

                    return $m[1][0]."/* TODO: FLICK MIGRATION - '{$dropdown}': {$note} */\n".$m[1][0].$m[2][0].$m[3][0].$dropdown.$m[4][0];
                },
                $subject,
                -1,
                $count,
                PREG_OFFSET_CAPTURE
            );
        }

        // Flag dropdowns with no equivalent in simple string format (4th arg
        // of select) — guarded so re-runs never stack duplicate TODO lines.
        foreach ($this->noEquivalentDropdowns as $dropdown => $note) {
            $subject = $content;

            $content = preg_replace_callback(
                '/^(\\s*)(.*)('.$recv."->select\\s*\\([^,]+,[^,]+,[^,]+,\\s*['\"])".preg_quote($dropdown, '/')."(['\"]\\s*\\).*)/m",
                function ($m) use ($dropdown, $note, $subject) {
                    if ($this->isAlreadyMigrated($subject, $m[3][1])) {
                        return $m[0][0];
                    }

                    $this->todos[] = "'{$dropdown}': {$note}";

                    return $m[1][0]."/* TODO: FLICK MIGRATION - '{$dropdown}': {$note} */\n".$m[1][0].$m[2][0].$m[3][0].$dropdown.$m[4][0];
                },
                $subject,
                -1,
                $count,
                PREG_OFFSET_CAPTURE
            );
        }

        return $content;
    }

    /**
     * Convert Formr's post/get/fastpost/validate to Flick's request().
     *
     * Formr: post('field', 'Label', 'rules') - 3 params
     * Flick: request('field', 'rules') - 2 params (label is dropped)
     */
    private function migrateRequestMethods(string $content, Receivers $receivers): string
    {
        // Methods that need parameter transformation
        $methods = ['post', 'get', 'fastpost', 'validate'];

        // Only rewrite these (very common) method names on receivers we know
        // hold a Formr instance. Otherwise unrelated calls like
        // $session->get('cart', 'default') get corrupted. When no Formr
        // instance is found (standalone snippet) we fall back to any variable.
        //
        // Run only on real code so Formr calls quoted inside a // comment or a
        // heredoc body are not rewritten (mig-M5). Uses the comments+heredoc
        // protector (not the string one) because the transform must see the
        // call's string-literal arguments.
        //
        // Argument handling goes through replaceBalancedCall (not regexes) so
        // a label like sprintf('%s address', $type) or "Bob's field" is
        // matched as one argument. Formr's 2nd positional argument is always
        // the label, so with 2 or 3 args it is dropped; anything else is a
        // plain rename that keeps every argument.
        return $this->replaceOutsideCommentsAndHeredocs($content, function (string $code) use ($methods, $receivers) {
            foreach ($methods as $method) {
                $code = $this->replaceBalancedCall($code, $method, function ($var, $args) use ($receivers) {
                    if (! $receivers->matches($var)) {
                        return null; // Not a known Formr receiver; leave as-is.
                    }

                    $args = array_map('trim', $args);
                    $this->stats['methods']++;

                    if (count($args) === 3) {
                        return "{$var}->request({$args[0]}, {$args[2]})";
                    }

                    if (count($args) === 2) {
                        return "{$var}->request({$args[0]})";
                    }

                    return "{$var}->request(".implode(', ', $args).')';
                });
            }

            return $code;
        });
    }

    private function migrateNamespaces(string $content): string
    {
        $patterns = [
            // use statement
            '/use\s+Formr\\\\Formr\s*;/' => 'use Flick\\Flick;',
            // Fully qualified class name
            '/Formr\\\\Formr/' => 'Flick\\Flick',
            // use with alias
            '/use\s+Formr\\\\Formr\s+as\s+/' => 'use Flick\\Flick as ',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $newContent = preg_replace($pattern, $replacement, $content);
            if ($newContent !== $content) {
                $this->stats['namespaces']++;
                $content = $newContent;
            }
        }

        // Rename any remaining bare `Formr` class references (e.g.
        // `instanceof Formr`, `Formr::something()`, `Formr $form` type hints).
        // Runs after the qualified-name replacements above so we only catch
        // unqualified usages; otherwise these leave a fatal "Class Formr not
        // found". Word boundaries prevent touching identifiers like
        // FormrMigrator. Scoped to code only: prose like `echo "Powered by
        // Formr"` or comments must not be rewritten.
        $newContent = $this->replaceOutsideStringsAndComments(
            $content,
            fn (string $code) => preg_replace('/\bFormr\b/', 'Flick', $code)
        );
        if ($newContent !== $content) {
            $this->stats['namespaces']++;
            $content = $newContent;
        }

        return $content;
    }

    /**
     * Apply a replacement callback only to the code portions of the content,
     * leaving string literals, heredoc/nowdoc bodies, comments, and template
     * HTML untouched. The inverse of the string-literal scan used for inline
     * validation rules in migrateValidationRules().
     */
    private function replaceOutsideStringsAndComments(string $content, callable $callback): string
    {
        return $this->applyToCodeTokens($content, $callback, true);
    }

    /**
     * Like replaceOutsideStringsAndComments(), but protects ONLY comments,
     * heredoc/nowdoc bodies, and template HTML — NOT inline quoted strings.
     * Used by passes whose patterns legitimately match string-literal
     * arguments (e.g. the request() transform matches ->post('field',
     * 'label', 'rules')): protecting inline strings would split the call and
     * break the match, but a call quoted in a // comment or a heredoc body
     * must still be left untouched (mig-M5).
     */
    private function replaceOutsideCommentsAndHeredocs(string $content, callable $callback): string
    {
        return $this->applyToCodeTokens($content, $callback, false);
    }

    /**
     * Tokenize the content with PHP's own tokenizer and apply $callback only
     * to runs of code tokens. Protected tokens (comments, heredoc/nowdoc
     * bodies, inline HTML, open/close tags — plus quoted strings when
     * $protectStrings is true) are re-emitted verbatim. Using token_get_all
     * instead of hand-rolled regexes makes the scan PHP-tag aware: an
     * apostrophe in template prose like "Don't" is inline HTML, so it can no
     * longer open a phantom string and desync the protector (6p).
     *
     * Content without any PHP tag is treated as a bare code snippet.
     */
    private function applyToCodeTokens(string $content, callable $callback, bool $protectStrings): string
    {
        $source = $content;
        $prefixed = false;

        if (! str_contains($content, '<?')) {
            $source = '<?php '.$content;
            $prefixed = true;
        }

        $tokens = token_get_all($source);
        $result = '';
        $buffer = '';
        $inHeredoc = false;
        $inDoubleQuote = false;

        $flush = function () use (&$result, &$buffer, $callback) {
            if ($buffer !== '') {
                $result .= $callback($buffer);
                $buffer = '';
            }
        };

        foreach ($tokens as $i => $token) {
            if ($prefixed && $i === 0) {
                continue; // The synthetic open tag, not part of the content.
            }

            [$id, $text] = is_array($token) ? [$token[0], $token[1]] : [null, $token];

            if ($id === T_START_HEREDOC) {
                $inHeredoc = true;
                $flush();
                $result .= $text;

                continue;
            }
            if ($id === T_END_HEREDOC) {
                $inHeredoc = false;
                $result .= $text;

                continue;
            }
            if ($inHeredoc) {
                $result .= $text;

                continue;
            }

            // Interpolated double-quoted strings arrive as a `"` char token,
            // inner tokens, then a closing `"` — protect the whole span.
            if ($protectStrings && $id === null && $text === '"') {
                $inDoubleQuote = ! $inDoubleQuote;
                $flush();
                $result .= $text;

                continue;
            }
            if ($inDoubleQuote) {
                $result .= $text;

                continue;
            }

            $protected = $id === T_COMMENT
                || $id === T_DOC_COMMENT
                || $id === T_INLINE_HTML
                || $id === T_OPEN_TAG
                || $id === T_OPEN_TAG_WITH_ECHO
                || $id === T_CLOSE_TAG
                || ($protectStrings && $id === T_CONSTANT_ENCAPSED_STRING);

            if ($protected) {
                $flush();
                $result .= $text;
            } else {
                $buffer .= $text;
            }
        }

        $flush();

        return $result;
    }

    /**
     * Convert Formr-style arrays to Flick format.
     *
     * Formr: ['text' => 'fname,First Name:', 'email' => 'email,Email:', 'submit' => 'submit,,Submit']
     * Flick: ['fields' => ['fname' => ['type' => 'text', 'label' => 'First Name:'], ...], 'button' => 'Submit']
     */
    private function migrateFormrArrays(string $content): string
    {
        // Valid Formr field types (with optional numeric suffix)
        $fieldTypes = ['text', 'email', 'password', 'textarea', 'hidden', 'number', 'tel', 'url', 'date', 'time', 'datetime', 'color', 'range', 'search', 'week', 'month', 'file', 'checkbox', 'radio', 'select', 'submit'];

        // Inline array literals passed directly to a form builder:
        // fastform(['text' => 'name,Name:', ...]). The assignment-only
        // handling below used to leave these untouched, so Flick's create()
        // received a shape it does not understand and rendered an empty
        // form (6d). Bracket-matched and processed last-to-first so offsets
        // stay valid.
        if (preg_match_all('/\$\w+->(?:create_form|create_form_multipart|create|fastform|fastform_multipart|fastpost)\s*\(\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($m[0]) as [$match, $at]) {
                $open = $at + strlen($match) - 1;
                $close = $this->findMatchingBracket($content, $open);
                if ($close === -1) {
                    continue;
                }

                $parsed = $this->parseFormrArrayEntries(substr($content, $open + 1, $close - $open - 1), $fieldTypes);
                if ($parsed === null) {
                    continue;
                }

                $this->stats['methods']++;
                $content = substr($content, 0, $open)
                    .$this->buildFlickFormArrayLiteral($parsed)
                    .substr($content, $close + 1);
            }
        }

        // $var = [ 'type' => 'value', ... ]; assignments. The field-type keys
        // (date, time, email, ...) also occur in plain data arrays like
        // ['date' => '2024-01-01'], so the only reliable signal that an array
        // is a Formr form definition is that the variable is actually passed
        // to a form builder. Entries are parsed with quote-aware matching so
        // a label like "Bob's Name:" converts instead of silently failing.
        if (preg_match_all('/(\$\w+)\s*=\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            for ($i = count($m[0]) - 1; $i >= 0; $i--) {
                [$match, $at] = $m[0][$i];
                $varName = $m[1][$i][0];

                $open = $at + strlen($match) - 1;
                $close = $this->findMatchingBracket($content, $open);
                if ($close === -1) {
                    continue;
                }

                // Must be a whole statement: optional whitespace then `;`.
                $j = $close + 1;
                while ($j < strlen($content) && ctype_space($content[$j])) {
                    $j++;
                }
                if (($content[$j] ?? '') !== ';') {
                    continue;
                }

                $passedToFormMethod = (bool) preg_match(
                    '/->(?:create_form|create_form_multipart|create|fastform|fastform_multipart|fastpost|post|get|validate|request)\s*\(\s*'.preg_quote($varName, '/').'\b/',
                    $content
                );
                if (! $passedToFormMethod) {
                    continue;
                }

                $parsed = $this->parseFormrArrayEntries(substr($content, $open + 1, $close - $open - 1), $fieldTypes);
                if ($parsed === null) {
                    continue;
                }

                $this->stats['methods']++;
                $content = substr($content, 0, $at)
                    .$varName.' = '.$this->buildFlickFormArrayLiteral($parsed).';'
                    .substr($content, $j + 1);
            }
        }

        return $content;
    }

    /**
     * Parse the inner text of a Formr fastform array literal into fields and
     * a button label. Returns null unless EVERY entry is a simple quoted
     * 'type' => 'name,label,...' pair with a known field type — anything else
     * means the array is not a Formr form definition and must be left alone.
     *
     * @return array{fields: array<string, array{type: string, label: string}>, button: ?string}|null
     */
    private function parseFormrArrayEntries(string $inner, array $fieldTypes): ?array
    {
        $fields = [];
        $button = null;
        $sawEntry = false;
        $typeAlts = implode('|', $fieldTypes);

        foreach ($this->splitTopLevelArgs($inner) as $entry) {
            if (trim($entry) === '') {
                continue; // trailing comma
            }

            if (! preg_match('/^\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*=>\s*([\'"])((?:\\\\.|(?!\3).)*)\3\s*$/s', $entry, $m)) {
                return null;
            }

            $key = $this->unquoteLiteral($m[1], $m[2]);
            $value = $this->unquoteLiteral($m[3], $m[4]);
            if ($key === null || $value === null) {
                return null;
            }

            if (! preg_match('/^('.$typeAlts.')\d*$/', $key, $tm)) {
                return null;
            }
            $type = $tm[1];

            // Comma-delimited value spec: name,label,buttonText
            $parts = explode(',', $value);
            $name = trim($parts[0] ?? '');
            $label = trim($parts[1] ?? '');
            $buttonText = trim($parts[2] ?? '');

            $sawEntry = true;

            if ($type === 'submit') {
                $button = $buttonText ?: $label ?: 'Submit';
            } elseif ($name !== '') {
                $fields[$name] = ['type' => $type, 'label' => $label];
            }
        }

        if (! $sawEntry) {
            return null;
        }

        return ['fields' => $fields, 'button' => $button];
    }

    /**
     * Decode the raw contents of a matched quoted literal. Single-quoted
     * escapes are unescaped; double-quoted contents are only accepted when
     * they contain no escapes or interpolation (returning null bails the
     * whole array conversion rather than mis-decoding).
     */
    private function unquoteLiteral(string $quote, string $raw): ?string
    {
        if ($quote === "'") {
            return strtr($raw, ["\\'" => "'", '\\\\' => '\\']);
        }

        if (str_contains($raw, '\\') || str_contains($raw, '$')) {
            return null;
        }

        return $raw;
    }

    /**
     * Emit a Flick create() array literal from parsed fastform entries,
     * escaping single quotes so labels like "Bob's Name:" stay valid PHP.
     */
    private function buildFlickFormArrayLiteral(array $parsed): string
    {
        $esc = fn (string $s): string => str_replace(['\\', "'"], ['\\\\', "\\'"], $s);

        $output = "[\n    'fields' => [\n";
        foreach ($parsed['fields'] as $name => $field) {
            $output .= "        '".$esc($name)."' => ['type' => '".$esc($field['type'])."', 'label' => '".$esc($field['label'])."'],\n";
        }
        $output .= "    ],\n";

        if ($parsed['button'] !== null) {
            $output .= "    'button' => '".$esc($parsed['button'])."',\n";
        }

        return $output.']';
    }

    private function migrateConstructor(string $content): string
    {
        // Match: new Formr('bootstrap') or new Formr('bulma', 'hush'), under
        // any of the four spellings -- bare, Formr\Formr, and either with a
        // leading backslash. The fully qualified \Formr\Formr form used to
        // miss every pattern here and fall through to the namespace rename,
        // which swapped the class but left Formr's positional arguments in
        // place, silently swallowing 'hush' and re-enabling echo mode.
        $pattern = '/new\s+(\\\\?(?:Formr\\\\)?)Formr\s*\(\s*[\'"](\w+)[\'"]\s*(?:,\s*[\'"](\w+)[\'"])?\s*\)/';

        $content = preg_replace_callback($pattern, function ($matches) {
            $class = $this->flickClassName($matches[1]);
            $wrapper = $matches[2];
            $switch = $matches[3] ?? null;

            $config = ["'views' => '{$wrapper}'"];

            if ($switch === 'hush') {
                $config[] = "'echo' => false";
            } elseif ($switch === 'nowrap') {
                // Add TODO for nowrap
                $this->todos[] = "'nowrap' switch has no direct Flick equivalent";
            }

            $configStr = implode(', ', $config);
            $this->stats['namespaces']++;

            return "new {$class}([{$configStr}])";
        }, $content);

        // Simple new Formr() without arguments
        $content = preg_replace_callback(
            '/new\s+(\\\\?(?:Formr\\\\)?)Formr\s*\(\s*\)/',
            fn ($matches) => 'new '.$this->flickClassName($matches[1]).'()',
            $content
        );

        // Fallback: new Formr($var) / new Formr(EXPR) with non-literal args.
        // Rename to new Flick(...) with a TODO since the argument no longer
        // maps directly (Flick takes an array config). Without this the bare
        // "new Formr(" survives and fatals with "Class Formr not found".
        $content = preg_replace_callback('/new\s+(\\\\?(?:Formr\\\\)?)Formr\s*\(\s*([^)]+?)\s*\)/', function ($matches) {
            $this->stats['namespaces']++;

            return '/* TODO: FLICK MIGRATION - review constructor argument(s) */ new '
                .$this->flickClassName($matches[1]).'('.$matches[2].')';
        }, $content);

        return $content;
    }

    /**
     * The class name to emit for a constructor, given the prefix matched in
     * the source.
     *
     * A source that spelled the class qualified (`Formr\Formr` or
     * `\Formr\Formr`) may sit in a file with no `use` statement and no
     * namespace, where an unqualified `new Flick()` resolves to a global
     * `\Flick` that does not exist -- "Class \"Flick\" not found" at the first
     * line that builds a form. A fully qualified name is correct whether or
     * not an import is present, so qualified in means qualified out.
     */
    private function flickClassName(string $matchedPrefix): string
    {
        return str_contains($matchedPrefix, 'Formr\\') ? '\\Flick\\Flick' : 'Flick';
    }

    // Methods that should be commented out entirely (not just flagged)
    private array $commentOutMethods = [
        'messages' => 'Use errors(), errorMessage(), successMessage(), etc.',
        'csrf' => 'Flick adds CSRF protection automatically',
    ];

    private function migrateMethods(string $content, Receivers $receivers): string
    {
        // Scope receiver-agnostic method rewrites to variables that actually
        // hold a Formr instance, so unrelated objects (e.g. $logger->error(),
        // $config->dropdown()) are left untouched. Falls back to any variable
        // for standalone snippets with no constructor.
        $recv = $receivers->pattern();

        // First, handle methods that should be commented out entirely
        foreach ($this->commentOutMethods as $method => $note) {
            $content = $this->commentOutMethodCall($content, $recv, $method, "'{$method}': {$note}");
        }

        // Handle Pro methods with TODO comments
        foreach ($this->proMethods as $method) {
            $content = $this->flagMethodWithTodo($content, $recv, $method, "'{$method}' requires Flick Pro package");
        }

        // Handle methods with no equivalent
        foreach ($this->noEquivalentMethods as $method => $note) {
            $content = $this->flagMethodWithTodo($content, $recv, $method, "'{$method}': {$note}");
        }

        // Handle submit() vs submitted() BEFORE the method renames below:
        // in original Formr code every `->submit(` is the submission check
        // (buttons are input_submit/submit_button, not yet renamed), so
        // converting here can never touch a button. Formr's optional form-id
        // argument is preserved — Flick ignores it (the id comes from
        // constructor config) but it documents intent. A leftover submit()
        // renders a button in Flick and is always truthy, so every
        // value-consuming context must be converted, not just `if (...)`.

        // if/elseif/while ( ... $form->submit([$id]) ... )
        $content = preg_replace(
            '/\b(if|elseif|while)(\s*\(\s*)('.$recv.')->submit\s*\(\s*([^)]*?)\s*\)/',
            '$1$2$3->submitted($4)',
            $content
        );
        // negation: !$form->submit([$id])
        $content = preg_replace(
            '/(!\s*)('.$recv.')->submit\s*\(\s*([^)]*?)\s*\)/',
            '$1$2->submitted($3)',
            $content
        );
        // boolean operator on the right: $form->submit([$id]) && / ||
        $content = preg_replace(
            '/('.$recv.')->submit\s*\(\s*([^)]*?)\s*\)(\s*(?:&&|\|\|))/',
            '$1->submitted($2)$3',
            $content
        );
        // boolean operator on the left: && / || $form->submit([$id])
        $content = preg_replace(
            '/((?:&&|\|\|)\s*)('.$recv.')->submit\s*\(\s*([^)]*?)\s*\)/',
            '$1$2->submitted($3)',
            $content
        );
        // ternary condition: $form->submit([$id]) ? ... (also short ternary),
        // but not a PHP close tag or null coalescing
        $content = preg_replace(
            '/('.$recv.')->submit\s*\(\s*([^)]*?)\s*\)(\s*\?(?![>?]))/',
            '$1->submitted($2)$3',
            $content
        );
        // return statement: return $form->submit([$id]);
        $content = preg_replace(
            '/(\breturn\s+)('.$recv.')->submit\s*\(\s*([^)]*?)\s*\)/',
            '$1$2->submitted($3)',
            $content
        );

        // Apply method renames (scoped to Formr receivers). Renames run only on
        // real code — string literals, heredoc/nowdoc bodies, and comments are
        // left untouched so Formr syntax quoted in a comment or heredoc is not
        // rewritten (mig-M5).
        foreach ($this->methodMap as $old => $new) {
            $pattern = '/('.$recv.')->'.preg_quote($old, '/').'\s*\(/';
            $replacement = '$1->'.$new.'(';

            $newContent = $this->replaceOutsideCommentsAndHeredocs(
                $content,
                fn (string $code) => preg_replace($pattern, $replacement, $code)
            );
            if ($newContent !== $content) {
                $this->stats['methods']++;
                $content = $newContent;
            }
        }

        // Rename Formr methods that appear later in a method chain — e.g.
        // form_open()->input_text()->form_close() — which the receiver-anchored
        // renames above miss because ')->method(' is not preceded by a
        // variable (mig-M4).
        $content = $this->migrateMethodChains($content, $receivers);

        // Formr's input_hidden/hidden default the value to '', but Flick's
        // hidden(name, value) requires both arguments — a 1-arg call fatals
        // with ArgumentCountError. Append the empty value explicitly.
        $content = $this->replaceOutsideCommentsAndHeredocs(
            $content,
            fn (string $code) => $this->replaceBalancedCall($code, 'hidden', function ($var, $args) use ($receivers) {
                if (! $receivers->matches($var)) {
                    return null;
                }

                $args = array_map('trim', $args);
                if (count($args) !== 1 || $args[0] === '') {
                    return null;
                }

                $this->stats['methods']++;

                return "{$var}->hidden({$args[0]}, '')";
            })
        );

        // Handle fieldsetOpen with extra arguments (Formr accepts $string for attributes)
        // Match anywhere in the line, not just at line start
        $pattern = '/('.$recv.')->fieldsetOpen\s*\(\s*([\'"][^\'"]*[\'"])\s*,\s*([^\)]+)\)/';
        if (preg_match($pattern, $content)) {
            $content = preg_replace_callback(
                $pattern,
                function ($matches) {
                    $this->todos[] = "fieldsetOpen() extra argument removed: {$matches[3]}";

                    return "/* TODO: FLICK MIGRATION - fieldsetOpen() extra argument removed: {$matches[3]} */ {$matches[1]}->fieldsetOpen({$matches[2]})";
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Rename Formr methods that appear as later links in a method chain, e.g.
     * $form->form_open()->input_text('a')->form_close(). The receiver-anchored
     * renames only catch the first call (the head is a variable); subsequent
     * `)->method(` links are not preceded by a variable. This walks each chain
     * rooted at a Formr receiver and renames every mapped method in it. Runs
     * only on real code (strings/comments/heredocs are skipped) and is a no-op
     * for external-instance files.
     */
    private function migrateMethodChains(string $content, Receivers $receivers): string
    {
        if ($receivers->isExternal()) {
            return $content;
        }

        return $this->replaceOutsideCommentsAndHeredocs(
            $content,
            fn (string $code) => $this->renameChainsInCode($code, $receivers->pattern())
        );
    }

    private function renameChainsInCode(string $code, string $recv): string
    {
        if (! preg_match_all('/'.$recv.'(?=\s*->)/', $code, $matches, PREG_OFFSET_CAPTURE)) {
            return $code;
        }

        $result = '';
        $offset = 0;

        foreach ($matches[0] as [$match, $at]) {
            // Skip receivers already consumed inside a chain processed earlier.
            if ($at < $offset) {
                continue;
            }

            $result .= substr($code, $offset, $at - $offset).$match;
            $pos = $at + strlen($match);

            // Walk directly-chained ->method( ... ) links.
            while (preg_match('/\G(\s*->\s*)(\w+)(\s*)\(/', $code, $seg, PREG_OFFSET_CAPTURE, $pos)) {
                $arrowWs = $seg[1][0];
                $methodName = $seg[2][0];
                $afterName = $seg[3][0];
                $parenOpen = $seg[0][1] + strlen($seg[0][0]) - 1;
                $parenClose = $this->findMatchingParen($code, $parenOpen);
                if ($parenClose === -1) {
                    break;
                }

                $newName = $this->methodMap[$methodName] ?? $methodName;
                if ($newName !== $methodName) {
                    $this->stats['methods']++;
                }

                $result .= $arrowWs.$newName.$afterName.substr($code, $parenOpen, $parenClose - $parenOpen + 1);
                $pos = $parenClose + 1;
            }

            $offset = $pos;
        }

        $result .= substr($code, $offset);

        return $result;
    }

    /**
     * Convert Formr prebuilt form names to Flick format.
     *
     * Formr: fastform('login'), fastform('short_contact'), fastform('signup')
     * Flick: create('/login'), create('/shortContact'), create('/registration')
     *
     * The / prefix tells Flick to load a file-based form template.
     * Unknown form names are commented out with a TODO note.
     */
    private function migratePrebuiltForms(string $content, Receivers $receivers): string
    {
        // Convert known form names in both create() and request() calls
        // create() renders the form, request() validates/processes it
        $methods = ['create', 'request'];

        // create() is a common method name (factories, repositories), so only
        // rewrite calls on known Formr receivers (see Receivers).
        $recv = $receivers->pattern();

        foreach ($methods as $method) {
            foreach ($this->prebuiltForms as $formr => $form) {
                $flick = $form['path'];

                // Match: ->method('formr_name') or ->method("formr_name")
                $pattern = '/('.$recv."->$method\s*\(\s*)['\"]".preg_quote($formr, '/')."['\"](\s*\))/";
                $replacement = "$1'{$flick}'$2";

                $newContent = preg_replace($pattern, $replacement, $content);
                if ($newContent !== $content) {
                    $this->stats['methods']++;
                    $content = $newContent;
                }
            }
        }

        // Update form IDs to match Flick's prebuilt form IDs
        // Simple direct replacements for known prebuilt forms; each remap is
        // gated on the corresponding prebuilt form actually being used in the
        // file — a custom form that merely happens to be id'd "loginForm"
        // must keep its id.
        foreach ($this->prebuiltForms as $form) {
            if (! str_contains($content, "'{$form['path']}'") && ! str_contains($content, "\"{$form['path']}\"")) {
                continue;
            }

            $newId = $form['id'];

            foreach ($form['legacyIds'] as $oldId) {
                // Replace in constructor: 'id' => 'oldId'
                $content = preg_replace(
                    "/(['\"]id['\"]\s*=>\s*)['\"]".preg_quote($oldId, '/')."['\"]/",
                    "$1'{$newId}'",
                    $content
                );
                // Replace in submitted('oldId')
                $content = preg_replace(
                    '/('.$recv."->submitted\s*\(\s*)['\"]".preg_quote($oldId, '/')."['\"](\s*\))/",
                    "$1'{$newId}'$2",
                    $content
                );
            }
        }

        // Then, find any remaining create() calls with simple string names (not arrays, not / prefixed)
        // These are unknown prebuilt forms that should be commented out
        $knownNames = array_keys($this->prebuiltForms);
        $knownFlickNames = array_column($this->prebuiltForms, 'path');

        // Pattern for create('somename') where somename is a simple identifier (no /, no array)
        $pattern = '/('.$recv.')->create\s*\(\s*[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"](\s*\))/';

        $subject = $content;
        $content = preg_replace_callback($pattern, function ($matches) use ($knownNames, $knownFlickNames, $subject) {
            // Skip calls already commented/tagged from a previous run (idempotent).
            if ($this->isAlreadyMigrated($subject, $matches[0][1])) {
                return $matches[0][0];
            }

            $formName = $matches[2][0];

            // Skip if it's a known name (shouldn't happen, but safety check)
            if (in_array($formName, $knownNames) || in_array($formName, $knownFlickNames)) {
                return $matches[0][0];
            }

            // Skip if it starts with / (already Flick format)
            if (str_starts_with($formName, '/')) {
                return $matches[0][0];
            }

            $this->todos[] = "'{$formName}' prebuilt form is not included in Flick - review the disabled call";

            // Disable the call as a valid empty-string expression so it never
            // breaks the enclosing statement (a bare `//` comment would eat the
            // trailing `;` of e.g. `echo $form->create('x');`). The original
            // call is preserved inside the comment for manual review.
            return "/* TODO: FLICK MIGRATION - '{$formName}' prebuilt form is not included in Flick: {$matches[0][0]} */ ''";
        }, $subject, -1, $count, PREG_OFFSET_CAPTURE);

        return $content;
    }

    /**
     * Convert Formr mail methods to Flick mail service.
     *
     * Formr: $form->send_email($to, $subject, $body)
     * Flick: $form->mail->send($to, $subject, $body)
     *
     * Formr: $form->send_html_email($to, $subject, $html)
     * Flick: $form->mail->send($to, $subject, '', ['html' => $html])
     */
    private function migrateMailMethods(string $content, Receivers $receivers): string
    {
        // Scope to known Formr receivers: a mailer class with its own
        // send_email() must not be rewritten.
        //
        // Convert send_email()/send_html_email() to mail->send(). Balanced
        // argument matching is used so nested calls in an argument (e.g.
        // send_html_email($to, $subj, render($tpl))) do not produce broken
        // output like ['html' => render($tpl]).
        //
        // Formr signatures:
        //   send_email($to, $subject, $message, $from = '', $html = false, $headers = null)
        //   send_html_email($to, $subject, $message, $from = '', $headers = null)
        // $from maps to the 'fromAddress' option, a truthy $html flag routes
        // the body through the 'html' option, and $headers (no Flick
        // equivalent) gets an inline TODO — none of them may be dropped
        // silently (6m).
        foreach (['send_email' => false, 'send_html_email' => true] as $method => $isHtml) {
            $content = $this->replaceBalancedCall($content, $method, function ($var, $args) use ($isHtml, $receivers) {
                if (! $receivers->matches($var)) {
                    return null; // Not a known Formr receiver; leave as-is.
                }

                if (count($args) < 3) {
                    return null; // Not the expected Formr signature; leave as-is.
                }

                $args = array_map('trim', $args);
                $to = $args[0];
                $subject = $args[1];
                $body = $args[2];
                $from = $args[3] ?? '';
                $htmlArg = $isHtml ? '' : ($args[4] ?? '');
                $headersArg = $isHtml ? ($args[4] ?? '') : ($args[5] ?? '');

                $this->stats['methods']++;
                $this->mailReceivers[$var] = true;

                $options = [];
                $todoPrefix = '';
                $emptyLiterals = ["''", '""'];

                if ($from !== '' && ! in_array($from, $emptyLiterals, true)) {
                    $options[] = "'fromAddress' => {$from}";
                }

                $sendAsHtml = $isHtml;
                if (! $isHtml && $htmlArg !== '') {
                    if (in_array(strtolower($htmlArg), ['true', '1'], true)) {
                        $sendAsHtml = true;
                    } elseif (! in_array(strtolower($htmlArg), ['false', '0', "''", '""'], true)) {
                        $todoPrefix .= "/* TODO: FLICK MIGRATION - send_email() \$html flag was dynamic ({$htmlArg}); pass ['html' => \$body] when sending HTML */ ";
                        $this->todos[] = "send_email() \$html flag was dynamic ({$htmlArg}) - pass ['html' => \$body] when sending HTML";
                    }
                }

                if ($headersArg !== '' && ! in_array(strtolower($headersArg), ["''", '""', 'null'], true)) {
                    $todoPrefix .= "/* TODO: FLICK MIGRATION - the \$headers argument has no Flick equivalent: {$headersArg} */ ";
                    $this->todos[] = "send_email() \$headers argument has no Flick equivalent: {$headersArg}";
                }

                if ($sendAsHtml) {
                    $options[] = "'html' => {$body}";
                    $body = "''";
                }

                $optionsStr = $options === [] ? '' : ', ['.implode(', ', $options).']';

                return $todoPrefix."{$var}->mail->send({$to}, {$subject}, {$body}{$optionsStr})";
            });
        }

        // Add a configuration reminder TODO once per receiver, on that
        // receiver's first send. One TODO per file used to land on whichever
        // form happened to come first, leaving the others unflagged.
        foreach (array_keys($this->mailReceivers) as $var) {
            // The pattern captures any leading content on the line before $var->mail->send
            $content = preg_replace(
                '/('.preg_quote((string) $var, '/').'->mail->send\s*\()/',
                '/* TODO: FLICK MIGRATION - Mail service requires configuration in constructor (services.mail) */ $1',
                $content,
                1 // Only first occurrence
            );
            $this->todos[] = 'Mail service requires configuration in constructor (services.mail)';
        }

        return $content;
    }

    /**
     * Inject mail service configuration into constructor when mail methods are used.
     *
     * Called after migrateMailMethods() to add the required services.mail config
     * with placeholder values that developers must update.
     */
    private function migrateMailConfig(string $content): string
    {
        // Build the mail configuration template with placeholder values
        $mailConfig = "'services' => [\n".
            "        'mail' => [\n".
            "            // TODO: FLICK MIGRATION - Update these mail settings for your environment\n".
            "            'fromAddress' => 'noreply@example.com',\n".
            "            'fromName' => 'Your App Name',\n".
            "            'mailer' => [\n".
            "                'transport' => 'smtp',  // smtp, ses, mailgun, sendgrid, postmark, mailjet, mailtrap\n".
            "                'host' => 'localhost',\n".
            "                'port' => 587,\n".
            "                'encryption' => 'tls',\n".
            "                'username' => '',\n".
            "                'password' => ''\n".
            "            ]\n".
            "        ]\n".
            '    ]';

        // Every receiver that sends mail gets its own config. Recovering the
        // receiver by rescanning for the first mail->send() call meant a
        // second form's send would fail at runtime with no config at all.
        foreach (array_keys($this->mailReceivers) as $varName) {
            // Merge with a bracket-balanced scan so a nested array in the
            // existing config does not break the match. Skip if a services key
            // already exists on THIS receiver (mail is already configured, or
            // another service is and the merge needs doing by hand).
            [$content, $merged] = $this->mergeIntoFlickConstructor(
                $content,
                (string) $varName,
                $mailConfig,
                fn (string $inner) => str_contains($inner, "'services'") || str_contains($inner, '"services"')
            );

            if ($merged) {
                $this->stats['properties']++;
            }
        }

        return $content;
    }

    /**
     * Extract honeypot() method call and merge into constructor config.
     *
     * Formr: $form->honeypot('field_name');
     * Flick: new Flick(['honeypot' => 'field_name'])  // honeypot auto-added by open()
     */
    private function migrateHoneypotMethod(string $content, Receivers $receivers): string
    {
        // Scoped to known Formr receivers so an unrelated object's honeypot()
        // is never commented out or promoted to constructor config.
        $recv = $receivers->pattern();

        // Find honeypot method calls and extract the field name. EVERY call is
        // handled, per receiver variable — taking only the first match used to
        // comment out a second form's honeypot without ever migrating it,
        // silently dropping that form's spam protection (6n).
        $pattern = '/('.$recv.')->honeypot\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            $seen = [];

            foreach ($matches as $match) {
                $varName = $match[1];
                $honeypotName = $match[2];

                $dedupeKey = $varName.'|'.$honeypotName;
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                // Merge honeypot into THIS receiver's constructor with a
                // bracket-balanced scan so a nested array in the existing
                // config is handled. Skip if honeypot is already configured
                // (idempotent).
                [$content, $merged] = $this->mergeIntoFlickConstructor(
                    $content,
                    $varName,
                    "'honeypot' => '{$honeypotName}'",
                    fn (string $inner) => str_contains($inner, "'honeypot'") || str_contains($inner, '"honeypot"')
                );

                $varPattern = preg_quote($varName, '/');
                $namePattern = preg_quote($honeypotName, '/');

                if ($merged) {
                    // Only now that the key actually landed do we comment out
                    // the honeypot() call (honeypot is auto-added by open()).
                    // Doing this unconditionally silently dropped spam
                    // protection when the merge failed. Patterns are bound to
                    // this receiver AND this field name so other forms' calls
                    // are never touched.
                    $content = preg_replace(
                        '/^(\s*)('.$varPattern.'->honeypot\s*\(\s*[\'"]'.$namePattern.'[\'"]\s*\)\s*;)/m',
                        "$1/* TODO: FLICK MIGRATION - honeypot moved to constructor, auto-added by open() */\n$1//$2",
                        $content
                    );

                    // Also handle inline pattern (e.g., after <?php). The
                    // (?<!\/) lookbehind skips calls that were already
                    // commented on a previous run, keeping the transform
                    // idempotent.
                    $content = preg_replace(
                        '/(?<!\/)('.$varPattern.'->honeypot\s*\(\s*[\'"]'.$namePattern.'[\'"]\s*\)\s*;)/',
                        '/* TODO: FLICK MIGRATION - honeypot moved to constructor, auto-added by open() */ //$1',
                        $content
                    );
                } else {
                    // Constructor is elsewhere or already has honeypot
                    // configured. Flag for manual attention rather than
                    // silently dropping the call.
                    $content = preg_replace(
                        '/(?<!\/)(?<!manually \*\/ )('.$varPattern.'->honeypot\s*\(\s*[\'"]'.$namePattern.'[\'"]\s*\)\s*;)/',
                        "/* TODO: FLICK MIGRATION - add ['honeypot' => '{$honeypotName}'] to the Flick constructor manually */ \$1",
                        $content,
                        1
                    );
                    $this->todos[] = "honeypot('{$honeypotName}') could not be merged into the constructor - add ['honeypot' => '{$honeypotName}'] manually";
                }

                $this->stats['methods']++;
            }
        } else {
            // Handle honeypot() without a name - flag for manual configuration
            $noNamePattern = '/'.$recv.'->honeypot\s*\(\s*\)/';
            if (preg_match($noNamePattern, $content)) {
                // Try line-start pattern first
                $content = preg_replace(
                    '/^(\s*)('.$recv.'->honeypot\s*\(\s*\)\s*;)/m',
                    "$1/* TODO: FLICK MIGRATION - honeypot needs a field name: new Flick(['honeypot' => 'fieldname']) */\n$1//$2",
                    $content
                );

                // Also handle inline pattern (skip already-commented calls)
                $content = preg_replace(
                    '/(?<!\/)('.$recv.'->honeypot\s*\(\s*\)\s*;)/',
                    '/* TODO: FLICK MIGRATION - honeypot needs a field name */ //$1',
                    $content
                );

                $this->todos[] = 'honeypot() needs a field name - the empty-argument call was disabled';
            }
        }

        return $content;
    }

    private function migrateValidationRules(string $content, Receivers $receivers): string
    {
        // Find validation strings in request() calls (migrated from post/get/validate)
        // Formr: post('field', 'Label', 'rules') -> rules in 3rd position
        // Also handle: request('field', 'rules') -> rules in 2nd position

        // Scoped to known Formr receivers: an unrelated $api->request() call
        // must not have its arguments reinterpreted as validation rules.
        $recv = $receivers->pattern();

        // Pattern for 3-argument calls: request('field', 'Label', 'rules')
        $pattern3 = '/('.$recv.'->request\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"])([^\'"]+)([\'"]\s*\))/';

        $content = preg_replace_callback($pattern3, function ($matches) {
            $prefix = $matches[1];
            $rules = $matches[2];
            $suffix = $matches[3];

            $result = $this->convertValidationRulesWithTodos($rules);

            if ($result['rules'] !== $rules) {
                $this->stats['validation_rules']++;
            }

            $output = $prefix.$result['rules'].$suffix;

            // Add inline TODO comments for no-equivalent rules
            if (! empty($result['inline_todos'])) {
                foreach ($result['inline_todos'] as $todo) {
                    // No list push here: the rule converter already recorded
                    // each of these in $this->todos when it produced them.
                    $output = "/* TODO: FLICK MIGRATION - {$todo} */ ".$output;
                }
            }

            return $output;
        }, $content);

        // Pattern for 2-argument calls: request('field', 'rules')
        $pattern2 = '/('.$recv.'->request\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"])([^\'"]+)([\'"]\s*\))/';

        $content = preg_replace_callback($pattern2, function ($matches) {
            $prefix = $matches[1];
            $rules = $matches[2];
            $suffix = $matches[3];

            // Skip if this looks like a label (no pipe or validation keywords)
            if (! str_contains($rules, '|') && ! preg_match('/^(required|email|min|max|alpha|numeric|int|url|ip|valid_|regex|matches|hash|sanitize|alpha_numeric|an|alphanumeric)/i', $rules)) {
                return $matches[0];
            }

            $result = $this->convertValidationRulesWithTodos($rules);

            if ($result['rules'] !== $rules) {
                $this->stats['validation_rules']++;
            }

            $output = $prefix.$result['rules'].$suffix;

            // Add inline TODO comments for no-equivalent rules
            if (! empty($result['inline_todos'])) {
                foreach ($result['inline_todos'] as $todo) {
                    // No list push here: the rule converter already recorded
                    // each of these in $this->todos when it produced them.
                    $output = "/* TODO: FLICK MIGRATION - {$todo} */ ".$output;
                }
            }

            return $output;
        }, $content);

        // Also handle inline validation in create()/fastform() strings.
        // Formr inline rules (e.g. 'Name(required|min[2])') always live INSIDE
        // quoted strings, so only scan string literals — and only string
        // literals that sit inside arguments of calls on a Formr receiver (or
        // arrays traced to one, see formArrayRegions). Running over every
        // string in the file mangled unrelated literals, e.g. the regex
        // '/^cat(dog|bird)$/' became '/^cat[dog, bird]$/'.
        $regions = $this->formArrayRegions($content, $recv);
        if ($regions === []) {
            return $content;
        }

        $stringLiteralPattern = '/([\'"])((?:\\\\.|(?!\1)[^\\\\])*)\1/';

        $content = preg_replace_callback($stringLiteralPattern, function ($strMatch) use ($regions) {
            if (! $this->offsetWithinRegions($strMatch[0][1], $regions)) {
                return $strMatch[0][0];
            }

            $quote = $strMatch[1][0];
            $inner = $strMatch[2][0];

            // Pattern: Name(required|min[2]) -> Name[required, min:2]
            $converted = preg_replace_callback('/(?<![>\w])([A-Za-z_]\w*)\(([^)\'"]+)\)/', function ($matches) {
                $fieldName = $matches[1];
                $rules = $matches[2];

                // Skip PHP function calls, keywords, and common method names
                $skipWords = ['if', 'else', 'while', 'for', 'foreach', 'function', 'class', 'new', 'echo', 'print', 'return', 'isset', 'empty', 'array', 'preg_match', 'preg_replace', 'str_contains', 'file_get_contents', 'request', 'post', 'get', 'validate', 'create', 'open', 'close'];
                if (in_array(strtolower($fieldName), $skipWords)) {
                    return $matches[0];
                }

                // Skip if this doesn't look like validation rules
                if (! preg_match('/^[a-z_|[\]0-9,]+$/i', $rules)) {
                    return $matches[0];
                }

                // Check if rules contain Formr-style validation. A single bare
                // token also counts when it is a known rule name — Formr's
                // 'Name(required)' otherwise survives in paren syntax, which
                // Flick parses as a prebuilt-dropdown reference and fatals.
                if (str_contains($rules, '|') || preg_match('/\w+\[\d+\]/', $rules) || $this->isKnownRuleToken($rules)) {
                    $convertedRules = $this->convertValidationRules($rules);
                    $this->stats['validation_rules']++;

                    return "{$fieldName}[{$convertedRules}]";
                }

                return $matches[0];
            }, $inner);

            return $quote.$converted.$quote;
        }, $content, -1, $count, PREG_OFFSET_CAPTURE);

        return $content;
    }

    /**
     * Whether a bare token is a validation rule name the migrator knows —
     * either a Formr rule (mapped, modifier, or no-equivalent) or already a
     * valid Flick rule.
     */
    private function isKnownRuleToken(string $token): bool
    {
        if (preg_match('/^\w+$/', $token) !== 1) {
            return false;
        }

        return isset($this->ruleMap[$token])
            || isset($this->modifierRules[$token])
            || isset($this->noEquivalentRules[$token])
            || in_array($token, $this->flickValidRules, true);
    }

    /**
     * Is this a rule name Flick will recognise once mapping has run?
     *
     * The hand-written valid-rules list is the SOURCE spelling; ruleMap's
     * VALUES are names it produces. Listing only the first meant a successful
     * rename could still be reported as unmapped - which is what happened to
     * alphaNumeric. modifierRules is not included: that branch returns before
     * this check ever runs.
     */
    private function ruleIsKnownAfterMapping(string $ruleName): bool
    {
        return in_array($ruleName, $this->flickValidRules, true)
            || in_array($ruleName, $this->ruleMap, true);
    }

    private function convertValidationRules(string $rules): string
    {
        $result = $this->convertValidationRulesWithTodos($rules);

        return $result['rules'];
    }

    private function convertValidationRulesWithTodos(string $rules): array
    {
        $inlineTodos = [];

        // Split by pipe delimiter
        $ruleList = explode('|', $rules);
        $convertedRules = [];

        foreach ($ruleList as $rule) {
            $rule = trim($rule);
            if (empty($rule)) {
                continue;
            }

            // Extract rule name and parameter
            $ruleName = $rule;
            $param = null;

            // Handle bracket notation: min[5] -> min:5. Use a greedy inner
            // match so a parameter that itself contains ']' — e.g.
            // regex[/^[a-z]+$/] — is captured whole instead of being truncated
            // at the first ']' (which left the rule in Formr bracket syntax).
            if (preg_match('/^(\w+)\[(.+)\]$/s', $rule, $matches)) {
                $ruleName = $matches[1];
                $param = $matches[2];
            } elseif (str_contains($rule, '[')) {
                // Bracketed rule we could not parse (unbalanced) - flag it
                // rather than silently emitting invalid Flick syntax.
                $inlineTodos[] = "'{$rule}' could not be converted automatically - review the parameter manually";
                $this->todos[] = "Rule '{$rule}' could not be converted automatically - review manually";
                $convertedRules[] = $rule;

                continue;
            }

            $originalName = $ruleName;

            // Modifier rules become inline string modifiers (bcrypt, stripTags,
            // sanitizeEmail, ...) appended to the rule list. Flick keeps these
            // INLINE in the rule string, so they must not be dropped.
            if (isset($this->modifierRules[$ruleName])) {
                $convertedRules[] = $this->modifierRules[$ruleName];

                continue;
            }

            // Check if this rule has no equivalent
            if (isset($this->noEquivalentRules[$ruleName])) {
                $inlineTodos[] = "'{$ruleName}': ".$this->noEquivalentRules[$ruleName];
                $this->todos[] = $this->noEquivalentRules[$ruleName];

                continue;
            }

            // Apply rule name mapping
            if (isset($this->ruleMap[$ruleName])) {
                $ruleName = $this->ruleMap[$ruleName];
            }

            if (! $this->ruleIsKnownAfterMapping($ruleName) && preg_match('/^\w+$/', $originalName)) {
                // Token is an unknown bare rule name after mapping. Keep it
                // inline (never silently drop code) but flag it for review. The
                // ^\w+$ guard skips already-converted Flick rules (which contain
                // ',' or ':'), keeping the transform idempotent on a re-run.
                $inlineTodos[] = "'{$originalName}' has no known Flick rule equivalent - review manually";
                $this->todos[] = "Unmapped validation rule '{$originalName}' - review manually";
            }

            // Build the converted rule
            if ($param !== null) {
                $convertedRules[] = "{$ruleName}:{$param}";
            } else {
                $convertedRules[] = $ruleName;
            }
        }

        // Join with comma delimiter (Flick style)
        return [
            'rules' => implode(', ', $convertedRules),
            'inline_todos' => $inlineTodos,
        ];
    }

    /**
     * Regex sub-pattern for a property-assignment value: everything up to the
     * first semicolon OUTSIDE a quoted string, so a value like
     * 'process.php?a=1;b=2' is captured whole instead of being cut at the
     * embedded semicolon (which produced un-parseable output).
     */
    private function propertyValuePattern(): string
    {
        return '(?:[^;\'"]|\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")+';
    }

    /**
     * Comment out one `$var->prop = value;` assignment with a TODO, whether
     * the statement owns its line or sits inline after other code (e.g. right
     * after `<?php`). This two-pattern branch used to be written twice — for
     * no-equivalent properties and for unmergeable upload properties — where
     * the copies could drift independently.
     *
     * @return array{0: string, 1: bool} the content, and whether a match was replaced
     */
    private function commentOutPropertyAssignment(string $content, string $varPattern, string $prop, string $note): array
    {
        $todoMsg = 'TODO: FLICK MIGRATION - '.$note;

        // Try line-start pattern first - capture the entire assignment line
        $linePattern = '/^(\s*)('.$varPattern.'->'.preg_quote($prop, '/').'\s*=\s*'.$this->propertyValuePattern().';)/m';
        if (preg_match($linePattern, $content)) {
            return [preg_replace($linePattern, '$1// '.$todoMsg."\n".'$1// $2', $content, 1), true];
        }

        // Inline pattern (e.g., after <?php on same line)
        $inlinePattern = '/('.$varPattern.'->'.preg_quote($prop, '/').'\s*=\s*'.$this->propertyValuePattern().';)/';
        if (preg_match($inlinePattern, $content)) {
            return [preg_replace($inlinePattern, '/* '.$todoMsg.' */ // $1', $content, 1), true];
        }

        return [$content, false];
    }

    private function migrateProperties(string $content, Receivers $receivers): string
    {
        // Group properties by variable name
        $propertiesByVar = [];
        $propsNoEquivalent = [];
        $propsRequirePro = [];

        // Find property assignments: $form->property = value
        // - Scope to Formr receivers so unrelated objects ($post->comments,
        //   $config->version) are not flagged/commented out (falls back to any
        //   variable for standalone snippets).
        // - (?!=) after the "=" avoids matching comparisons like
        //   $request->method == 'POST' (which would comment out mid-statement).
        // - Skip assignments already commented/tagged to stay idempotent.
        $recv = $receivers->pattern();
        $pattern = '/('.$recv.')->(\w+)\s*=(?!=)\s*('.$this->propertyValuePattern().');/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            if ($this->isAlreadyMigrated($content, $match[0][1])) {
                continue;
            }

            $varName = $match[1][0];
            $property = $match[2][0];
            $value = trim($match[3][0]);

            // Check if it's a known Formr property that can be merged into constructor
            if (isset($this->propertyMap[$property])) {
                if (! isset($propertiesByVar[$varName])) {
                    $propertiesByVar[$varName] = [];
                }
                $propertiesByVar[$varName][$this->propertyMap[$property]] = $value;
            } elseif (in_array($property, $this->proProperties)) {
                $propsRequirePro[] = ['var' => $varName, 'prop' => $property];
            } elseif (in_array($property, $this->noEquivalentProperties)) {
                $propsNoEquivalent[] = ['var' => $varName, 'prop' => $property];
            }
        }

        // Merge properties into constructor for each variable
        foreach ($propertiesByVar as $varName => $props) {
            // Build the config items to add
            $configItems = [];
            foreach ($props as $key => $value) {
                $configItems[] = "'{$key}' => {$value}";
            }
            $configStr = implode(', ', $configItems);

            // Merge into the constructor using a bracket-balanced scan so a
            // nested array in the existing config is handled, and so property
            // values containing $1/\1 are not expanded as backreferences.
            $varPattern = preg_quote($varName, '/');
            [$content, $merged] = $this->mergeIntoFlickConstructor($content, $varName, $configStr);

            if ($merged) {
                $this->stats['properties']++;

                // Remove the property assignment lines
                foreach ($props as $key => $value) {
                    $origKey = array_search($key, $this->propertyMap, true);
                    if ($origKey !== false) {
                        $content = preg_replace(
                            '/\s*'.$varPattern.'->'.preg_quote($origKey, '/').'\s*=\s*'.$this->propertyValuePattern().';\s*\n?/',
                            "\n",
                            $content,
                            1
                        );
                    }
                }
            }
        }

        // Flag properties with no equivalent - comment out the entire line
        foreach ($propsNoEquivalent as $item) {
            $prop = $item['prop'];
            [$content] = $this->commentOutPropertyAssignment(
                $content,
                preg_quote($item['var'], '/'),
                $prop,
                "'{$prop}' has no direct Flick equivalent"
            );
            $this->todos[] = "'{$prop}' has no direct Flick equivalent";
        }

        // Flag properties requiring Pro
        foreach ($propsRequirePro as $item) {
            $varPattern = preg_quote($item['var'], '/');
            $prop = $item['prop'];

            // Try line-start pattern first, then inline pattern
            $pattern = '/^(\s*)('.$varPattern.')->'.preg_quote($prop, '/').'\s*=/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace(
                    $pattern,
                    "$1// TODO: FLICK MIGRATION - '{$prop}' requires Flick Pro\n$1$2->{$prop} =",
                    $content,
                    1
                );
            } else {
                // Inline pattern (e.g., after <?php on same line)
                $pattern = '/('.$varPattern.')->'.preg_quote($prop, '/').'\s*=/';
                $content = preg_replace(
                    $pattern,
                    "/* TODO: FLICK MIGRATION - '{$prop}' requires Flick Pro */ $1->{$prop} =",
                    $content,
                    1
                );
            }
            $this->todos[] = "'{$prop}' requires Flick Pro";
        }

        return $content;
    }

    /**
     * Migrate upload properties to services.upload config in constructor.
     *
     * Extracts upload_dir and upload_max_filesize, adds them to the Flick
     * constructor config, and removes the original property assignments.
     */
    private function migrateUploadConfig(string $content, Receivers $receivers): string
    {
        // Find all upload property assignments and extract values, keyed by
        // the receiver they were assigned on. A flat map plus a single
        // "last var wins" scalar gave the last form every form's settings and
        // gave the earlier ones none - while still deleting every source line.
        $uploadConfigByVar = [];
        $propsToRemove = [];
        $perUploadTodos = [];

        // Pattern to match upload property assignments.
        // Scope to Formr receivers so unrelated objects' upload_* assignments
        // are not extracted or commented out (falls back to any variable for
        // standalone snippets). (?!=) avoids comparisons; the idempotency
        // guard skips assignments already commented/tagged from a previous run.
        $recv = $receivers->pattern();
        $pattern = '/('.$recv.')->(\w+)\s*=(?!=)\s*('.$this->propertyValuePattern().');/';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            if ($this->isAlreadyMigrated($content, $match[0][1])) {
                continue;
            }

            $var = $match[1][0];
            $property = $match[2][0];
            $value = trim($match[3][0]);

            // Check if it's a configurable upload property
            if (isset($this->uploadPropertyMap[$property])) {
                $configKey = $this->uploadPropertyMap[$property]['config'];

                // Convert bytes to human-readable for maxFileSize
                if ($configKey === 'maxFileSize' && is_numeric($value)) {
                    $value = "'".$this->bytesToHuman((int) $value)."'";
                }

                $uploadConfigByVar[$var][$configKey] = $value;
                $propsToRemove[] = ['var' => $var, 'prop' => $property];
            }

            // Check if it's a per-upload property (needs TODO)
            if (isset($this->uploadPerUploadProperties[$property])) {
                $option = $this->uploadPerUploadProperties[$property];
                $perUploadTodos[] = ['var' => $var, 'prop' => $property, 'option' => $option];
                $propsToRemove[] = ['var' => $var, 'prop' => $property];
            }
        }

        // Add each receiver's upload config to ITS OWN constructor.
        $mergedByVar = [];
        foreach ($uploadConfigByVar as $var => $uploadConfig) {
            $varPattern = preg_quote($var, '/');

            // Build the services.upload config string
            $configItems = [];
            foreach ($uploadConfig as $key => $value) {
                $configItems[] = "'".$key."' => ".$value;
            }
            $uploadEntry = "'upload' => [".implode(', ', $configItems).']';

            // Merge with a bracket-balanced scan (nested arrays in the existing
            // config are handled) and via substr concatenation (upload path
            // values containing $1/\1 are not expanded as backreferences). When
            // the constructor already has a 'services' array (e.g. injected by
            // the mail migration), 'upload' is merged INSIDE it — appending a
            // second top-level 'services' key made PHP keep only the last one,
            // silently discarding the mail config (6o). Only merges when the
            // constructor already has a config array; an empty new Flick()
            // falls through to the per-property TODO path below, preserving
            // existing behaviour.
            if (preg_match('/'.$varPattern.'\s*=\s*new\s+Flick\s*\(\s*\[/', $content)) {
                [$content, $merged] = $this->mergeUploadServiceIntoConstructor(
                    $content,
                    $var,
                    $uploadEntry
                );

                if ($merged) {
                    $this->stats['properties']++;
                    $mergedByVar[$var] = true;
                }
            }
        }

        // Remove or comment out the original property assignments. A line is
        // only deleted once THIS receiver's merge actually landed; otherwise
        // it is commented out with a TODO, so a failed merge can never take
        // the value with it.

        foreach ($propsToRemove as $item) {
            $varPattern = preg_quote($item['var'], '/');
            $prop = $item['prop'];

            if (($mergedByVar[$item['var']] ?? false) && isset($this->uploadPropertyMap[$prop])) {
                // Property was added to config - remove the line
                $pattern = '/\s*'.$varPattern.'->'.preg_quote($prop, '/').'\s*=\s*'.$this->propertyValuePattern().';\s*\n?/';
                $content = preg_replace($pattern, "\n", $content, 1);
            } else {
                // No constructor found or per-upload property - comment out with TODO
                $note = isset($this->uploadPropertyMap[$prop])
                    ? 'Add services.upload config to constructor'
                    : "'{$prop}' is now set per-upload";

                [$content, $replaced] = $this->commentOutPropertyAssignment($content, $varPattern, $prop, $note);
                if ($replaced) {
                    $this->todos[] = $note;
                }
            }
        }

        // Add TODOs for per-upload properties, after the constructor of the
        // receiver they were assigned on.
        $todosByVar = [];
        foreach ($perUploadTodos as $todo) {
            $todosByVar[$todo['var']][] = $todo;
        }

        foreach ($todosByVar as $var => $todos) {
            // Find where to insert the TODOs (after the constructor)
            $varPattern = preg_quote($var, '/');
            $constructorPattern = '/('.$varPattern.'\s*=\s*new\s+Flick\s*\([^)]*\)\s*;)/';

            if (preg_match($constructorPattern, $content, $match)) {
                $todoComments = "\n";
                foreach ($todos as $todo) {
                    $todoComments .= '// TODO: FLICK MIGRATION - \''.$todo['prop'].'\' is now set per-upload via \''.$todo['option']."' option\n";
                    $this->todos[] = "'".$todo['prop']."' is now set per-upload via '".$todo['option']."' option";
                }

                $content = preg_replace(
                    $constructorPattern,
                    '$1'.$todoComments,
                    $content,
                    1
                );
            }
        }

        return $content;
    }

    /**
     * Convert bytes to human-readable format.
     */
    private function bytesToHuman(int $bytes): string
    {
        // Only shorten a value that divides exactly. Rounding DOWN used to be
        // the fallback - 2000000 bytes became '1M', nearly halving the limit the
        // original code allowed, with only a console note to catch it. Flick's
        // parser reads a plain byte count ('2000000B'), so an awkward number is
        // carried across intact instead of being approximated in either
        // direction.
        foreach ([1073741824 => 'G', 1048576 => 'M', 1024 => 'K'] as $unit => $suffix) {
            if ($bytes >= $unit && $bytes % $unit === 0) {
                return intdiv($bytes, $unit).$suffix;
            }
        }

        return $bytes.'B';
    }

    /**
     * Migrate file upload method calls.
     *
     * Collects file field names from file() and files() calls, then replaces
     * request() calls for those fields with upload->image() or upload->file().
     */
    private function migrateFileUploadMethods(string $content, Receivers $receivers): string
    {
        // Scoped to known Formr receivers: $disk->file('x') must neither
        // register 'x' as a form file field nor cause $api->request('x')
        // to be rewritten into an upload call.
        $recv = $receivers->pattern();

        // First pass: collect file field names from file() and files() calls
        // Pattern: ->file('fieldname' or ->files('fieldname
        $pattern = '/'.$recv.'->files?\s*\(\s*[\'"]([^\'"]+)[\'"]/';
        preg_match_all($pattern, $content, $matches);

        foreach ($matches[1] as $fieldName) {
            // Strip [] suffix if present
            $this->fileFields[] = rtrim($fieldName, '[]');
        }

        if (empty($this->fileFields)) {
            return $content;
        }

        // Second pass: replace request() calls for file fields with upload->image/file()
        foreach ($this->fileFields as $fieldName) {
            $fieldPattern = preg_quote($fieldName, '/');

            // Pattern: $var = $form->request('fieldname') or $form->request('fieldname[]')
            $pattern = '/(\$\w+)\s*=\s*('.$recv.')->request\s*\(\s*[\'"]'.$fieldPattern.'(?:\[\])?'.'[\'"]\s*(?:,\s*[\'"][^\'"]*[\'"]\s*)?\)/';

            $content = preg_replace_callback($pattern, function ($matches) use ($fieldName) {
                $resultVar = $matches[1];
                $formVar = $matches[2];

                // Determine if it's likely an image or generic file based on field name
                $method = $this->guessUploadMethod($fieldName);

                $this->stats['methods']++;

                return "// TODO: FLICK MIGRATION - Adjust upload options as needed\n    ".$resultVar.' = '.$formVar.'->upload->'.$method."('".$fieldName."')";
            }, $content);
        }

        return $content;
    }

    /**
     * Guess whether to use image() or file() based on field name.
     */
    private function guessUploadMethod(string $fieldName): string
    {
        $imageKeywords = ['avatar', 'photo', 'picture', 'image', 'img', 'logo', 'icon', 'thumbnail', 'thumb'];

        $lowerName = strtolower($fieldName);
        foreach ($imageKeywords as $keyword) {
            if (str_contains($lowerName, $keyword)) {
                return 'image';
            }
        }

        return 'file';
    }

    /**
     * Migrate reads of upload properties.
     *
     * Config-mappable properties are converted to $form->config() calls.
     * Per-upload properties are commented out with a TODO message.
     */
    private function migrateUploadPropertyReads(string $content, Receivers $receivers): string
    {
        // Scoped to known Formr receivers: reads like $settings->upload_dir
        // on unrelated objects must not become config() calls or TODOs.
        $recv = $receivers->pattern();

        // Handle config-mappable properties: convert to $form->config()
        foreach ($this->uploadPropertyMap as $prop => $mapping) {
            $propQuoted = preg_quote($prop, '/');
            $configPath = $mapping['path'];

            // Special case: upload_max_filesize used in calculations (/ * + -)
            // These need to be commented out because the value format changed from bytes to human-readable
            if ($prop === 'upload_max_filesize') {
                // Match inline PHP with arithmetic operations
                $calcPattern = '/<\?php\s+echo\s+[^;]*'.$recv.'->'.$propQuoted.'\s*[\/\*\+\-][^;]*;\s*\?>/';
                $todoReplace = '<'.'?php /* TODO: maxFileSize is now human-readable (e.g. "5M"), not bytes */ ?'.'>';
                while (preg_match($calcPattern, $content)) {
                    $content = preg_replace($calcPattern, $todoReplace, $content, 1);
                    $this->todos[] = 'maxFileSize is now human-readable (e.g. "5M"), not bytes';
                }
            }

            // Simple inline PHP echo blocks (no calculations)
            $inlinePattern = '/(<\?php\s+echo\s+)('.$recv.')->'.$propQuoted.'(\s*;\s*\?>)/';
            while (preg_match($inlinePattern, $content)) {
                $content = preg_replace(
                    $inlinePattern,
                    '$1$2->config(\''.$configPath.'\')$3',
                    $content,
                    1
                );
                $this->stats['methods']++;
            }

            // Full PHP lines: echo $form->upload_dir; — but only in real code,
            // never inside a string literal. A double-quoted "... $form->
            // upload_dir ..." is PHP property interpolation; rewriting it to a
            // method call would break the string (methods don't interpolate).
            $linePattern = '/('.$recv.')->'.$propQuoted.'(?!\s*=)/';
            $content = $this->replaceOutsideStringsAndComments(
                $content,
                function (string $code) use ($linePattern, $configPath) {
                    $result = preg_replace(
                        $linePattern,
                        '$1->config(\''.$configPath.'\')',
                        $code,
                        -1,
                        $count
                    );
                    $this->stats['methods'] += $count;

                    return $result ?? $code;
                }
            );
        }

        // Handle per-upload properties: comment out with TODO
        foreach ($this->uploadPerUploadProperties as $prop => $option) {
            $propQuoted = preg_quote($prop, '/');

            // Inline PHP echo blocks
            $inlinePhpPattern = '/<\?php\s+echo\s+[^;]*'.$recv.'->'.$propQuoted.'[^;]*;\s*\?>/';
            $todoReplacement = '<'.'?php /* TODO: '.$prop.' is now set per-upload via '.$option.' option */ ?'.'>';
            while (preg_match($inlinePhpPattern, $content)) {
                $content = preg_replace($inlinePhpPattern, $todoReplacement, $content, 1);
                $this->todos[] = "{$prop} is now set per-upload via {$option} option";
            }

            // Full PHP lines
            $pattern = '/^(\s*)([^\/\n]*'.$recv.'->'.$propQuoted.'(?!\s*=)[^;]*;)/m';
            while (preg_match($pattern, $content)) {
                $replacement = '$1/* TODO: FLICK MIGRATION - '.$prop.' is now set per-upload via '.$option.' option */'."\n".'$1// $2';
                $content = preg_replace($pattern, $replacement, $content, 1);
                $this->todos[] = "{$prop} is now set per-upload via {$option} option";
            }
        }

        return $content;
    }

    /**
     * Handle reads of properties that have no equivalent in Flick.
     *
     * Properties like session_values, required_indicator, inline_errors, show_valid
     * are read in debug/display sections but don't exist in Flick.
     */
    private function migrateNoEquivalentPropertyReads(string $content, Receivers $receivers): string
    {
        // Scoped to known Formr receivers: template echoes of unrelated
        // objects (e.g. $post->comments, $config->version) must never be
        // commented out just because the property name matches.
        $recv = $receivers->pattern();

        foreach ($this->noEquivalentProperties as $prop) {
            $propQuoted = preg_quote($prop, '/');

            // Pattern 1: Inline PHP echo with ternary
            $ternaryPattern = '/'.'<\?php\s+echo\s+('.$recv.')->'.$propQuoted.'\s*\?[^;]+;\s*\?>'.'/';
            $content = preg_replace_callback(
                $ternaryPattern,
                function ($match) use ($prop) {
                    $this->todos[] = "'{$prop}' is not available in Flick - its read was disabled";
                    $varName = $match[1];
                    $phpOpen = '<'.'?php';
                    $phpClose = '?'.'>';

                    return $phpOpen.' /* TODO: FLICK MIGRATION - '."'".$prop."'".' not available */ //echo '.$varName.'->'.$prop.' ? ... '.$phpClose;
                },
                $content
            );

            // Pattern 2: Simple inline PHP echo
            $simplePattern = '/'.'<\?php\s+echo\s+('.$recv.')->'.$propQuoted.'\s*;\s*\?>'.'/';
            $content = preg_replace_callback(
                $simplePattern,
                function ($match) use ($prop) {
                    $this->todos[] = "'{$prop}' is not available in Flick - its read was disabled";
                    $varName = $match[1];
                    $phpOpen = '<'.'?php';
                    $phpClose = '?'.'>';

                    return $phpOpen.' /* TODO: FLICK MIGRATION - '."'".$prop."'".' not available */ //echo '.$varName.'->'.$prop.'; '.$phpClose;
                },
                $content
            );
        }

        return $content;
    }

    public function getStats(): array
    {
        // 'todos' is derived, never incremented: one note per flag, counted.
        return array_merge($this->stats, ['todos' => count($this->todos)]);
    }

    public function getTodos(): array
    {
        return $this->todos;
    }

    public function resetStats(): void
    {
        $this->stats = [
            'namespaces' => 0,
            'methods' => 0,
            'validation_rules' => 0,
            'properties' => 0,
        ];
        $this->todos = [];
    }

    /**
     * Find variables that hold a Formr (or already-migrated Flick) instance.
     *
     * Scans for `$var = new Formr(...)` / `new Flick(...)` and the
     * fully-qualified variants. Because migrateConstructor() runs first,
     * most instances are already `new Flick(...)` by the time later passes
     * call this; both spellings are matched to be safe.
     *
     * Used to scope receiver-agnostic rewrites so unrelated objects (e.g.
     * $session, $request, $config) are never corrupted. Returns an empty
     * array when none are found — callers then fall back to legacy
     * behaviour so standalone code snippets (with no constructor) still
     * migrate.
     *
     * @return list<string> e.g. ['$form']
     */
    private function findFormrVariables(string $content): array
    {
        $vars = [];

        // An interposed block comment is tolerated between "=" and "new"
        // because that is exactly what this tool writes for a non-literal
        // constructor argument. Without it, a re-run over the tool's own
        // output would stop seeing the receiver.
        $pattern = '/(\$\w+)\s*=\s*(?:\/\*[\s\S]*?\*\/\s*)?new\s+\\\\?(?:Formr\\\\Formr|Flick\\\\Flick|Formr|Flick)\s*\(/';
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $var) {
                $vars[$var] = true;
            }
        }

        // Type-hinted parameters (and docblock @param lines) also identify
        // receivers: multi-file apps construct the instance elsewhere and
        // inject it, e.g. `function render(Formr $form)`.
        $hintPattern = '/\\\\?(?:Formr\\\\Formr|Flick\\\\Flick|Formr|Flick)\s+(\$\w+)/';
        if (preg_match_all($hintPattern, $content, $matches)) {
            foreach ($matches[1] as $var) {
                $vars[$var] = true;
            }
        }

        return array_keys($vars);
    }

    /**
     * Whether the content references the Formr (or already-migrated Flick)
     * class structurally — via a use statement, instantiation, static call,
     * instanceof, type hint, or fully qualified name. Deliberately narrow so
     * prose or migration comments mentioning "Flick" don't count.
     *
     * Public so the CLI can gate on a structural signal instead of a bare
     * "formr" substring — a file that merely mentions formr in a comment must
     * not be processed (which would trigger the any-variable fallback and
     * corrupt unrelated calls like $session->get('cart', 'default')).
     */
    public function hasFormrClassReference(string $content): bool
    {
        $class = '\\\\?(?:Formr\\\\Formr|Flick\\\\Flick|Formr|Flick)';

        return preg_match(
            '/\buse\s+'.$class.'\b|\bnew\s+'.$class.'\b|'.$class.'::|\binstanceof\s+'.$class.'\b|'.$class.'\s+\$\w+/',
            $content
        ) === 1;
    }

    /**
     * Whether the content contains method calls or property assignments that
     * look like Formr usage (names drawn from the migration tables). Used to
     * decide if an external-instance file deserves a manual-migration TODO.
     */
    private function hasLikelyFormrUsage(string $content): bool
    {
        $methods = array_merge(
            ['post', 'get', 'fastpost', 'validate'],
            array_keys($this->methodMap),
            array_keys($this->commentOutMethods),
            $this->proMethods,
            array_keys($this->noEquivalentMethods),
        );
        $methodAlts = implode('|', array_map(fn ($m) => preg_quote($m, '/'), $methods));

        if (preg_match('/\$\w+->(?:'.$methodAlts.')\s*\(/', $content) === 1) {
            return true;
        }

        $properties = array_merge(
            array_keys($this->propertyMap),
            $this->proProperties,
            array_keys($this->uploadPropertyMap),
            array_keys($this->uploadPerUploadProperties),
            $this->noEquivalentProperties,
        );
        $propertyAlts = implode('|', array_map(fn ($p) => preg_quote($p, '/'), $properties));

        return preg_match('/\$\w+->(?:'.$propertyAlts.')\s*=[^=]/', $content) === 1;
    }

    /**
     * Resolve the receivers for the given content. When the class is
     * referenced but no local receiver can be identified (no constructor, no
     * type hint), the instance lives in another file and receiver-scoped
     * rewrites are skipped entirely rather than falling back to any variable,
     * which used to corrupt unrelated objects like
     * $session->get('cart', 'default'). Plain snippets with no class
     * reference keep the fallback.
     *
     * Called once per migrate(), on the untouched input.
     */
    private function resolveReceivers(string $content): Receivers
    {
        $vars = $this->findFormrVariables($content);

        return new Receivers($vars, $vars === [] && $this->hasFormrClassReference($content));
    }

    /**
     * Byte-offset ranges [start, end] of array contexts traceable to the
     * form: argument lists of method calls on Formr receivers, plus
     * $var = [...] literals whose variable is passed to a form-builder
     * method on such a receiver. Used to keep value-based rewrites (like
     * dropdown 'options' renames) away from unrelated data arrays.
     *
     * Offsets are only valid for the exact $content passed in — recompute
     * after any mutation.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function formArrayRegions(string $content, string $recv): array
    {
        $regions = [];

        // Inline arguments of any call on a known Formr receiver.
        if (preg_match_all('/'.$recv.'->\w+\s*\(/', $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$match, $at]) {
                $open = $at + strlen($match) - 1;
                $close = $this->findMatchingParen($content, $open);
                if ($close !== -1) {
                    $regions[] = [$open, $close];
                }
            }
        }

        // $var = [...] array literals passed to a form-builder method.
        if (preg_match_all('/(\$\w+)\s*=\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $i => [$varName]) {
                $open = $m[0][$i][1] + strlen($m[0][$i][0]) - 1;
                $close = $this->findMatchingBracket($content, $open);
                if ($close === -1) {
                    continue;
                }

                $passedToForm = preg_match(
                    '/'.$recv.'->(?:create_form|create_form_multipart|create|fastform|fastform_multipart|fastpost|post|get|validate|request)\s*\(\s*'.preg_quote($varName, '/').'\b/',
                    $content
                ) === 1;

                if ($passedToForm) {
                    $regions[] = [$open, $close];
                }
            }
        }

        return $regions;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $regions
     */
    private function offsetWithinRegions(int $offset, array $regions): bool
    {
        foreach ($regions as [$start, $end]) {
            if ($offset >= $start && $offset <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a single file-level TODO when receiver rewrites were skipped
     * because the form instance is constructed elsewhere (see Receivers).
     * Runs as the last migration pass; guarded by a marker so re-runs stay
     * idempotent.
     */
    private function flagExternalInstanceUsage(string $content, Receivers $receivers): string
    {
        $marker = 'created elsewhere, so its method calls were not migrated automatically';

        if (str_contains($content, $marker)) {
            return $content;
        }

        if (! $receivers->isExternal() || ! $this->hasLikelyFormrUsage($content)) {
            return $content;
        }

        $todo = "// TODO: FLICK MIGRATION - The form object used in this file is {$marker}. "
            ."Update them manually (e.g. ->post('field', 'Label', 'rules') becomes ->request('field', 'rules')).";

        $this->todos[] = 'Form instance created elsewhere - migrate its method calls manually';

        if (preg_match('/^<\?php\b.*$/m', $content, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);

            return substr($content, 0, $insertAt)."\n".$todo.substr($content, $insertAt);
        }

        return $todo."\n".$content;
    }

    /**
     * Determine whether the match at $offset sits on a line that is already
     * commented out or already carries a migration TODO marker (current or
     * previous line). Keeps the comment-inserting passes idempotent so that
     * re-running the migrator does not duplicate TODO comments.
     */
    private function isAlreadyMigrated(string $content, int $offset): bool
    {
        $lineStart = strrpos(substr($content, 0, $offset), "\n");
        $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;
        $before = substr($content, $lineStart, $offset - $lineStart);

        // Already inside a line/block comment before the match on this line?
        if (str_contains($before, '//') || str_contains($before, '/*')) {
            return true;
        }

        // Current line already tagged?
        $lineEnd = strpos($content, "\n", $offset);
        $line = substr($content, $lineStart, ($lineEnd === false ? strlen($content) : $lineEnd) - $lineStart);
        if (str_contains($line, 'TODO: FLICK MIGRATION')) {
            return true;
        }

        // Previous line already tagged? The line-start TODO formats put the
        // marker on the line above a statement they leave LIVE - a Pro
        // property, a flagged method call, a dropdown's options key - so
        // nothing on the statement's own line says it was handled.
        //
        // The previous line has to be COMMENT-ONLY for that to be what is
        // happening. A TODO sitting inline on a line of real code
        // (`echo /* TODO */ $form->heading('Hi');`, or the constructor line,
        // which always gets one when its argument is not a literal) belongs to
        // that line's own statement and says nothing about the next one. Reading
        // it as "already migrated" silently skipped whatever followed.
        if ($lineStart > 1) {
            $prevLineEnd = $lineStart - 1; // index of the preceding "\n"
            $prevLineStart = strrpos(substr($content, 0, $prevLineEnd), "\n");
            $prevLineStart = ($prevLineStart === false) ? 0 : $prevLineStart + 1;
            $prevLine = trim(substr($content, $prevLineStart, $prevLineEnd - $prevLineStart));

            if (str_contains($prevLine, 'TODO: FLICK MIGRATION')
                && (str_starts_with($prevLine, '//') || str_starts_with($prevLine, '/*'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepend a migration TODO to a `$var->method(` call, choosing the
     * line-start or inline comment format based on context. Skips calls that
     * already carry a marker (idempotent).
     */
    private function flagMethodWithTodo(string $content, string $recv, string $method, string $note): string
    {
        $subject = $content;
        $pattern = '/('.$recv.')->('.preg_quote($method, '/').')\s*\(/';

        return preg_replace_callback($pattern, function ($m) use ($note, $subject) {
            $offset = $m[0][1];
            if ($this->isAlreadyMigrated($subject, $offset)) {
                return $m[0][0];
            }

            $var = $m[1][0];
            $methodName = $m[2][0];

            $lineStart = strrpos(substr($subject, 0, $offset), "\n");
            $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;
            $before = substr($subject, $lineStart, $offset - $lineStart);

            $this->todos[] = $note;

            if (trim($before) === '') {
                return '// TODO: FLICK MIGRATION - '.$note."\n".$before.$var.'->'.$methodName.'(';
            }

            return '/* TODO: FLICK MIGRATION - '.$note.' */ '.$var.'->'.$methodName.'(';
        }, $subject, -1, $count, PREG_OFFSET_CAPTURE);
    }

    /**
     * Comment out a whole `$var->method(...);` statement with a migration
     * TODO. Skips statements already commented/tagged (idempotent).
     *
     * Arguments are matched with findMatchingParen (not a regex), so a call
     * with nested parens like messages(getOpenTag(), getCloseTag()) is
     * disabled instead of surviving as an undefined-method fatal. When the
     * call sits inside a larger expression (e.g. `echo $form->messages();`),
     * it is replaced by an empty-string expression so the statement still
     * parses.
     */
    private function commentOutMethodCall(string $content, string $recv, string $method, string $note): string
    {
        if (! preg_match_all('/'.$recv.'->'.preg_quote($method, '/').'\s*\(/', $content, $m, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        // Last-to-first so earlier offsets stay valid after each rewrite.
        foreach (array_reverse($m[0]) as [$match, $offset]) {
            if ($this->isAlreadyMigrated($content, $offset)) {
                continue;
            }

            $parenOpen = $offset + strlen($match) - 1;
            $parenClose = $this->findMatchingParen($content, $parenOpen);
            if ($parenClose === -1) {
                continue;
            }

            // Optional whitespace then `;` marks a full statement.
            $j = $parenClose + 1;
            while ($j < strlen($content) && ctype_space($content[$j])) {
                $j++;
            }
            $hasSemicolon = ($content[$j] ?? '') === ';';

            $lineStart = strrpos(substr($content, 0, $offset), "\n");
            $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;
            $before = substr($content, $lineStart, $offset - $lineStart);

            $call = substr($content, $offset, $parenClose + 1 - $offset);
            $this->todos[] = $note;

            if ($hasSemicolon && trim($before) === '') {
                // Whole statement on its own line: comment it out.
                $stmt = substr($content, $offset, $j + 1 - $offset);
                $replacement = '// TODO: FLICK MIGRATION - '.$note."\n".$before.'//'.$stmt;
                $content = substr($content, 0, $offset).$replacement.substr($content, $j + 1);
            } elseif ($hasSemicolon && preg_match('/(?:<\?php|<\?|;|\{|\})\s*$/', $before)) {
                // Whole statement mid-line (e.g. after an open tag).
                $stmt = substr($content, $offset, $j + 1 - $offset);
                $replacement = '/* TODO: FLICK MIGRATION - '.$note.' */ //'.$stmt;
                $content = substr($content, 0, $offset).$replacement.substr($content, $j + 1);
            } else {
                // Part of a larger expression: swap the call for a valid
                // empty-string expression, preserving the original in the
                // comment for manual review.
                $replacement = '/* TODO: FLICK MIGRATION - '.$note.': '.$call." */ ''";
                $content = substr($content, 0, $offset).$replacement.substr($content, $parenClose + 1);
            }
        }

        return $content;
    }

    /**
     * Replace `$var->method(...)` calls using balanced-parenthesis matching
     * so nested calls in arguments (e.g. render($tpl)) are handled correctly.
     *
     * The $builder receives (string $var, array $args) where $args are the
     * top-level (comma-separated) argument strings. Returning a string
     * replaces the call; returning null leaves it unchanged.
     */
    private function replaceBalancedCall(string $content, string $method, callable $builder): string
    {
        $needle = '->'.$method;
        $needleLen = strlen($needle);
        $len = strlen($content);
        $result = '';
        $offset = 0;

        while (($pos = strpos($content, $needle, $offset)) !== false) {
            $callEnd = $pos + $needleLen;

            // Reject when the method name is a prefix of a longer identifier
            // (e.g. send_email vs send_emailNow).
            $nextChar = $content[$callEnd] ?? '';
            if ($nextChar !== '' && (ctype_alnum($nextChar) || $nextChar === '_')) {
                $result .= substr($content, $offset, $callEnd - $offset);
                $offset = $callEnd;

                continue;
            }

            // Scan back over the receiver variable name.
            $i = $pos - 1;
            while ($i >= 0 && (ctype_alnum($content[$i]) || $content[$i] === '_')) {
                $i--;
            }
            if ($i < 0 || $content[$i] !== '$') {
                $result .= substr($content, $offset, $callEnd - $offset);
                $offset = $callEnd;

                continue;
            }
            $varStart = $i;
            $var = substr($content, $varStart, $pos - $varStart);

            // Expect an opening paren after optional whitespace.
            $j = $callEnd;
            while ($j < $len && ctype_space($content[$j])) {
                $j++;
            }
            if ($j >= $len || $content[$j] !== '(') {
                $result .= substr($content, $offset, $callEnd - $offset);
                $offset = $callEnd;

                continue;
            }

            $close = $this->findMatchingParen($content, $j);
            if ($close === -1) {
                $result .= substr($content, $offset, $callEnd - $offset);
                $offset = $callEnd;

                continue;
            }

            $argsString = substr($content, $j + 1, $close - $j - 1);
            $args = $this->splitTopLevelArgs($argsString);
            $replacement = $builder($var, $args);

            $result .= substr($content, $offset, $varStart - $offset);
            if ($replacement === null) {
                $result .= substr($content, $varStart, $close + 1 - $varStart);
            } else {
                $result .= $replacement;
            }
            $offset = $close + 1;
        }

        $result .= substr($content, $offset);

        return $result;
    }

    /**
     * Merge the upload service entry into a `$var = new Flick([...])`
     * constructor. When the config already has a 'services' array, the
     * 'upload' entry is inserted inside it (bracket-balanced); otherwise a
     * fresh 'services' key is appended. Skips (returns false) when
     * services.upload is already configured, keeping re-runs idempotent.
     *
     * @return array{0: string, 1: bool} [content, merged]
     */
    private function mergeUploadServiceIntoConstructor(string $content, string $varName, string $uploadEntry): array
    {
        $varPattern = preg_quote($varName, '/');

        if (! preg_match('/'.$varPattern.'\s*=\s*new\s+Flick\s*\(\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            return [$content, false];
        }

        $bracketOpen = $m[0][1] + strlen($m[0][0]) - 1;
        $bracketClose = $this->findMatchingBracket($content, $bracketOpen);
        if ($bracketClose === -1) {
            return [$content, false];
        }

        $inner = substr($content, $bracketOpen + 1, $bracketClose - $bracketOpen - 1);

        // No services key yet: append one.
        if (! preg_match('/([\'"])services\1\s*=>\s*\[/', $inner, $sm, PREG_OFFSET_CAPTURE)) {
            return $this->mergeIntoFlickConstructor(
                $content,
                $varName,
                "'services' => [".$uploadEntry.']'
            );
        }

        // Existing services array: insert 'upload' inside it.
        $servicesOpen = $bracketOpen + 1 + $sm[0][1] + strlen($sm[0][0]) - 1;
        $servicesClose = $this->findMatchingBracket($content, $servicesOpen);
        if ($servicesClose === -1) {
            return [$content, false];
        }

        $servicesInner = substr($content, $servicesOpen + 1, $servicesClose - $servicesOpen - 1);
        if (preg_match('/([\'"])upload\1\s*=>/', $servicesInner)) {
            return [$content, false]; // Already configured (idempotent).
        }

        if (trim($servicesInner) === '') {
            $newServicesInner = $uploadEntry;
        } else {
            $sep = str_ends_with(rtrim($servicesInner), ',') ? ' ' : ', ';
            $newServicesInner = rtrim($servicesInner).$sep.$uploadEntry;
        }

        $content = substr($content, 0, $servicesOpen + 1).$newServicesInner.substr($content, $servicesClose);

        return [$content, true];
    }

    /**
     * Merge additional config entries into a `$var = new Flick(...)`
     * constructor.
     *
     * Uses a bracket-balanced scan (findMatchingBracket) so a nested array in
     * the existing config — e.g. new Flick(['attributes' => ['class' => 'x']])
     * — does not break the match the way a `[^\]]*?` matcher did. Additions
     * are concatenated as raw strings via substr (never through a preg_replace
     * REPLACEMENT), so a value containing $1/\1 is preserved verbatim instead
     * of being expanded as a backreference.
     *
     * $additions is the already-formatted config body to append (no leading
     * comma). $skip, if given, receives the trimmed existing inner config and
     * returns true to abort the merge (e.g. when a key already exists).
     *
     * @return array{0: string, 1: bool} [content, merged]
     */
    private function mergeIntoFlickConstructor(string $content, string $varName, string $additions, ?callable $skip = null): array
    {
        $varPattern = preg_quote($varName, '/');

        // Case A: new Flick([ ... ]) — existing (possibly nested) config array.
        if (preg_match('/'.$varPattern.'\s*=\s*new\s+Flick\s*\(\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $bracketOpen = $m[0][1] + strlen($m[0][0]) - 1;
            $bracketClose = $this->findMatchingBracket($content, $bracketOpen);

            if ($bracketClose !== -1) {
                $inner = substr($content, $bracketOpen + 1, $bracketClose - $bracketOpen - 1);

                if ($skip !== null && $skip(trim($inner))) {
                    return [$content, false];
                }

                if (trim($inner) === '') {
                    $newInner = $additions;
                } else {
                    $sep = str_ends_with(rtrim($inner), ',') ? ' ' : ', ';
                    $newInner = rtrim($inner).$sep.$additions;
                }

                $newContent = substr($content, 0, $bracketOpen + 1).$newInner.substr($content, $bracketClose);

                return [$newContent, true];
            }
        }

        // Case B: new Flick() — empty constructor.
        if (preg_match('/'.$varPattern.'\s*=\s*new\s+Flick\s*\(\s*\)/', $content, $m, PREG_OFFSET_CAPTURE)) {
            if ($skip !== null && $skip('')) {
                return [$content, false];
            }

            $whole = $m[0][0];
            $start = $m[0][1];
            $open = strpos($whole, '(');
            $newWhole = substr($whole, 0, $open).'(['.$additions.'])';
            $newContent = substr($content, 0, $start).$newWhole.substr($content, $start + strlen($whole));

            return [$newContent, true];
        }

        return [$content, false];
    }

    /**
     * Find the index of the parenthesis that matches the one at $openPos,
     * respecting quoted strings. Returns -1 if unbalanced.
     */
    private function findMatchingParen(string $content, int $openPos): int
    {
        return $this->findMatchingDelimiter($content, $openPos, '(', ')');
    }

    /**
     * Find the index of the square bracket that matches the one at $openPos,
     * respecting quoted strings. Returns -1 if unbalanced.
     */
    private function findMatchingBracket(string $content, int $openPos): int
    {
        return $this->findMatchingDelimiter($content, $openPos, '[', ']');
    }

    private function findMatchingDelimiter(string $content, int $openPos, string $open, string $close): int
    {
        $depth = 0;
        $len = strlen($content);
        $inString = false;
        $stringChar = '';

        for ($i = $openPos; $i < $len; $i++) {
            $ch = $content[$i];

            if ($inString) {
                if ($ch === '\\') {
                    $i++;

                    continue;
                }
                if ($ch === $stringChar) {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '\'' || $ch === '"') {
                $inString = true;
                $stringChar = $ch;

                continue;
            }

            if ($ch === $open) {
                $depth++;
            } elseif ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return -1;
    }

    /**
     * Split an argument string on top-level commas, respecting nested
     * parentheses/brackets/braces and quoted strings.
     *
     * @return list<string>
     */
    private function splitTopLevelArgs(string $argsString): array
    {
        $args = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $len = strlen($argsString);

        for ($i = 0; $i < $len; $i++) {
            $ch = $argsString[$i];

            if ($inString) {
                $current .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $current .= $argsString[$i + 1];
                    $i++;

                    continue;
                }
                if ($ch === $stringChar) {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '\'' || $ch === '"') {
                $inString = true;
                $stringChar = $ch;
                $current .= $ch;

                continue;
            }

            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
                $current .= $ch;

                continue;
            }
            if ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
                $current .= $ch;

                continue;
            }

            if ($ch === ',' && $depth === 0) {
                $args[] = $current;
                $current = '';

                continue;
            }

            $current .= $ch;
        }

        if (trim($current) !== '' || ! empty($args)) {
            $args[] = $current;
        }

        return $args;
    }

    /**
     * Parse Formr-style HTML attribute string into array format.
     *
     * Formr uses HTML attribute strings like 'class="form-select" disabled'
     * Flick uses PHP arrays like ['class' => 'form-select', 'disabled' => true]
     */
    private function parseFormrAttributes(string $attributeString): array
    {
        $attributeString = trim($attributeString);
        if (empty($attributeString)) {
            return [];
        }

        $attrs = [];

        // Match key="value" or key='value' patterns
        $quotedPattern = '/(\w[\w-]*)\s*=\s*["\']([^"\']*)["\']/';
        if (preg_match_all($quotedPattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs[$match[1]] = $match[2];
            }
            // Remove matched patterns to find standalone attributes
            $remaining = preg_replace($quotedPattern, '', $attributeString);
        } else {
            $remaining = $attributeString;
        }

        // Match standalone attributes (disabled, required, readonly, etc.)
        $remaining = trim($remaining);
        if (! empty($remaining)) {
            $standaloneAttrs = preg_split('/\s+/', $remaining);
            foreach ($standaloneAttrs as $attr) {
                $attr = trim($attr);
                if (! empty($attr) && preg_match('/^[\w-]+$/', $attr)) {
                    $attrs[$attr] = true;
                }
            }
        }

        return $attrs;
    }

    /**
     * Build Flick's 4th parameter string from parsed attributes.
     */
    private function buildFlickAttributesString(
        array $attrs,
        string $options,
        string $id,
        bool $optionsIsVariable = false,
        bool $optionsIsArray = false
    ): string {
        // Simple case: no id, no attrs, options is a string name
        if (empty($id) && empty($attrs) && ! empty($options) && ! $optionsIsVariable && ! $optionsIsArray) {
            return "'{$options}'";
        }

        // Simple case: no id, no attrs, options is a variable
        if (empty($id) && empty($attrs) && ! empty($options) && $optionsIsVariable) {
            return $options;
        }

        // Simple case: no id, no attrs, options is an inline array
        if (empty($id) && empty($attrs) && ! empty($options) && $optionsIsArray) {
            return $options;
        }

        // Build array format
        $parts = [];

        // Add id first if present
        if (! empty($id)) {
            $parts[] = "'id' => '{$id}'";
        }

        // Add options
        if (! empty($options)) {
            if ($optionsIsVariable || $optionsIsArray) {
                $parts[] = "'options' => {$options}";
            } else {
                $parts[] = "'options' => '{$options}'";
            }
        }

        // Add HTML attributes
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = "'{$key}' => true";
            } else {
                // Escape single quotes in value
                $escapedValue = str_replace("'", "\\'", $value);
                $parts[] = "'{$key}' => '{$escapedValue}'";
            }
        }

        if (empty($parts)) {
            return "''";
        }

        return '['.implode(', ', $parts).']';
    }
}
