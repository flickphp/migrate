<?php

declare(strict_types=1);

use Flick\Migrate\FormrMigrator;

/*
 * Audit 2026-08-16, M1-A — "a TODO happened" used to be tracked by two
 * unrelated accumulators: a stats['todos'] counter (22 increment sites) and a
 * getTodos() note list (7 push sites), with no rule governing which sites fed
 * which. The CLI printed the counter as "TODO comments added: N" and the list
 * as "Manual review needed", so a file full of flagged methods reported N
 * TODOs with an empty review list, and a nowrap-only file listed a note the
 * counter never saw. The note list is now the single source of truth and the
 * counter is derived from it: count and bullets can never disagree again.
 */

beforeEach(function () {
    $this->migrator = new FormrMigrator;
});

it('records a Pro-method flag in getTodos()', function () {
    $input = "<?php\n\$form = new Formr\Formr('bootstrap');\n\$form->recaptcha_passed();\n";
    $this->migrator->migrate($input);

    expect(implode("\n", $this->migrator->getTodos()))->toContain('recaptcha_passed');
});

it('records a commented-out method in getTodos()', function () {
    $input = "<?php\n\$form = new Formr\Formr('bootstrap');\n\$form->messages();\n";
    $this->migrator->migrate($input);

    expect(implode("\n", $this->migrator->getTodos()))->toContain('messages');
});

it('records a no-equivalent property in getTodos()', function () {
    $input = "<?php\n\$form = new Formr\Formr('bootstrap');\n\$form->charset = 'utf-8';\n";
    $this->migrator->migrate($input);

    expect(implode("\n", $this->migrator->getTodos()))->toContain('charset');
});

it('reports a todo count that always equals the review list', function () {
    $input = "<?php\n\$form = new Formr\Formr('bootstrap');\n"
        ."\$form->recaptcha_passed();\n"
        ."\$form->messages();\n"
        ."\$form->charset = 'utf-8';\n"
        ."\$form->post('x', 'X', 'required|totally_unknown_rule');\n";
    $this->migrator->migrate($input);

    $stats = $this->migrator->getStats();

    expect($stats['todos'])->toBeGreaterThan(0)
        ->and($stats['todos'])->toBe(count($this->migrator->getTodos()));
});

it('counts the nowrap note it used to list without counting', function () {
    $input = "<?php\n\$form = new Formr\Formr('bootstrap', 'nowrap');\n";
    $this->migrator->migrate($input);

    $todos = implode("\n", $this->migrator->getTodos());

    expect($todos)->toContain('nowrap')
        ->and($this->migrator->getStats()['todos'])->toBe(count($this->migrator->getTodos()));
});
