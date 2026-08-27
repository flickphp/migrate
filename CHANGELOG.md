# Changelog

All notable changes to `migrate` will be documented in this file

## v1.0.1 - 2026-08-27

### What's Changed
- No breaking changes.
- Fixed `input_submit()` being converted to `submitted()` instead of `submit()`.
- Fixed `form_open()`/`open()` keeping Formr's argument order, which rendered a broken `<form>` tag.
- Fixed `new Formr\Formr()` becoming an unqualified `new Flick()`.
- Fixed `new Formr('bootstrap', 'hush')` losing the `hush` option.
- Fixed `input_select()` with variable arguments throwing. The selected value is now kept.
- Fixed text fields with variable arguments losing their attributes (`disabled`, etc).
- Fixed Formr calls on a form stored in a property (`$this->form->input_text()`) not being converted.
- Fixed a multi-line property assignment being only partly commented out, which left the file unparseable.
- Fixed reads of a commented-out property throwing at runtime.
- Files that use Formr without naming the class are now flagged instead of skipped.
- Formr vendored by hand outside `vendor/` is now left alone.
- `submit()` in a context that can't be converted is now flagged instead of left silent.

## v1.0.0 - 2026-08-25

- First release.
