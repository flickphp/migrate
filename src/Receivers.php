<?php

declare(strict_types=1);

namespace Flick\Migrate;

/**
 * The form receivers for one file, resolved once from the untouched input.
 *
 * Every receiver-scoped pass used to re-derive this from the content it was
 * handed, which broke as soon as an earlier pass edited the constructor line:
 * migrateConstructor() inserts a TODO comment between "=" and "new" when the
 * argument is not a literal, the finder stopped seeing the receiver, and all
 * fourteen receiver-scoped passes silently became no-ops while the file gained
 * a TODO claiming the form was built somewhere else. Resolving once, before
 * any pass runs, removes that ordering hazard entirely.
 */
final class Receivers
{
    /**
     * @param  list<string>  $vars  receiver variables, e.g. ['$form']
     * @param  bool  $external  the class is referenced, but the instance is built in another file
     */
    public function __construct(
        private readonly array $vars,
        private readonly bool $external,
    ) {}

    /**
     * Regex sub-pattern matching the receivers: a never-matching sentinel for
     * an externally-built instance, any variable for a plain snippet with no
     * class reference, otherwise only the known receivers.
     */
    public function pattern(): string
    {
        if ($this->external) {
            return '(?!)';
        }

        if ($this->vars === []) {
            return '\$\w+';
        }

        $alts = array_map(fn (string $var) => preg_quote($var, '/'), $this->vars);

        return '(?:'.implode('|', $alts).')';
    }

    /**
     * Whether a receiver variable captured by a balanced-parenthesis scan is
     * one of ours. Driven by pattern() so the two can never disagree.
     */
    public function matches(string $var): bool
    {
        return preg_match('/^(?:'.$this->pattern().')$/', $var) === 1;
    }

    /**
     * Whether the form instance is constructed in another file, in which case
     * receiver-scoped rewrites must not run at all.
     */
    public function isExternal(): bool
    {
        return $this->external;
    }
}
