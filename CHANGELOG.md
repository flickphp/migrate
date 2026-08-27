# Changelog

All notable changes to `migrate` will be documented in this file

## v1.0.1 - 2026-08-26

Fidelity fixes from running v1.0.0 against five unrelated third-party
projects. Four of six representative migrated forms fatalled on render
before these; all six render now.

- Fixed a submit button being turned into a submission check.
  `return $form->input_submit('submit', '', 'Send');` became
  `return $form->submitted('Send');`, because buttons were renamed to
  `submit()` before the submit-to-submitted conversion ran over them. The
  conversion now runs first. This was the only defect that produced wrong
  working code rather than a fatal or a silence.
- Fixed `form_open()`/`open()` keeping Formr's argument order. Formr's
  `open($name, $id, $action, $method, $string)` reached Flick's
  `open($action, $method, $attributes)` untouched, rendering
  `<form action="/" method="" id="myForm" index.php?q=admin>` — wrong target,
  empty method, the URL emitted as a bare attribute.
- Fixed a qualified `new Formr\Formr()` becoming an unqualified `new Flick()`,
  which resolves to a non-existent global `\Flick` in a file with no import.
  Qualified in, qualified out.
- Fixed `new \Formr\Formr('bootstrap', 'hush')` skipping the array-config
  conversion entirely, silently swallowing `hush` and re-enabling echo mode.
- Fixed `input_select()` with non-literal arguments falling through to a plain
  rename, which handed Flick eight positional arguments and threw. Formr's
  `$selected` (argument 7) is now carried rather than dropped, so a saved
  selection survives.
- Fixed text-family fields with non-literal arguments losing their attributes:
  `input_email('e', 'E', $user->email, '', 'disabled')` rendered a field that
  was quietly not disabled.
- Fixed Formr calls on a form held in a property (`$this->form->input_text()`)
  never being converted while the type hint was, leaving Formr calls on a Flick
  object.
- Fixed a multi-line property assignment being commented out by its first line
  only, which left the continuation lines live and the file unparseable.
- Fixed a property read surviving its commented-out assignment, where it throws
  through Flick's `__get`.
- Files that use Formr's API without ever naming the class are now flagged
  instead of skipped in silence. One 361-file project reported
  "Migration complete!" over 31 live Formr calls.
- Formr vendored by hand outside `vendor/` is now left alone entirely, rather
  than having its namespace and class rewritten.
- `submit()` in a context that cannot be converted — an assignment or a bare
  statement — is now flagged rather than left silent.

## v1.0.0 - 2026-08-25

- First release.
