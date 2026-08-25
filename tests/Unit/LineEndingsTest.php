<?php

/**
 * A migrated file keeps the line endings it arrived with.
 *
 * Formr users on Windows are a large part of this tool's audience, and their
 * source is CRLF. The passes build inserted lines with a literal "\n", so a
 * TODO added on its own line used to land a lone LF in an otherwise CRLF file
 * and leave it with mixed endings. Nothing failed loudly -- git normalises on
 * commit under `text=auto` -- it just looked wrong in an editor and made a
 * needlessly noisy diff.
 *
 * Only a file whose endings are already uniform is restored. A file that
 * arrives mixed is left exactly as the passes leave it, so this never rewrites
 * endings on lines the migration did not touch.
 */

use Flick\Migrate\FormrMigrator;

/** Every fixture below is written LF here and converted per-case. */
function line_ending_fixture(string $case): string
{
    return match ($case) {
        // Inserts a TODO on its OWN line -- the case that produced the lone LF.
        'own-line todo' => "<?php\n\$form = new Formr();\n\$form->upload_dir = '/uploads';\n\$form->input_text('n', 'N');\n",
        // Inserts a TODO inline, mid-line.
        'inline todo' => "<?php\n\$form = new Formr();\necho \$form->heading('Hi');\n",
        // No TODO at all: plain renames.
        'plain rename' => "<?php\n\$form = new Formr('bootstrap');\n\$form->input_text('name', 'Name');\n",
        // A TODO inserted at the top of the file.
        'constructor todo' => "<?php\n\$form = new Formr(\$config);\n\$form->input_text('n', 'N');\n",
    };
}

$cases = ['own-line todo', 'inline todo', 'plain rename', 'constructor todo'];

it('leaves a CRLF file entirely CRLF', function (string $case) {
    $crlf = str_replace("\n", "\r\n", line_ending_fixture($case));

    $output = (new FormrMigrator)->migrate($crlf);

    $crlfCount = substr_count($output, "\r\n");
    $loneLf = substr_count($output, "\n") - $crlfCount;

    expect($loneLf)->toBe(0, "migrated output mixed a lone LF into a CRLF file ({$case})")
        ->and($crlfCount)->toBeGreaterThan(0);
})->with($cases);

it('leaves an LF file entirely LF', function (string $case) {
    $output = (new FormrMigrator)->migrate(line_ending_fixture($case));

    expect(substr_count($output, "\r"))->toBe(0, "migrated output introduced a CR into an LF file ({$case})");
})->with($cases);

it('migrates the same content either way', function (string $case) {
    $lf = line_ending_fixture($case);
    $migrator = new FormrMigrator;

    $fromLf = $migrator->migrate($lf);
    $fromCrlf = $migrator->migrate(str_replace("\n", "\r\n", $lf));

    // Same migration, only the endings differ.
    expect(str_replace("\r\n", "\n", $fromCrlf))->toBe($fromLf);
})->with($cases);

it('does not rewrite the endings of a file that arrives mixed', function () {
    // Half CRLF, half LF, and nothing here triggers a TODO. A file that is
    // already inconsistent is not this tool's to tidy: the untouched lines must
    // come back exactly as they went in.
    $mixed = "<?php\r\n\$form = new Formr('bootstrap');\n\$other = 1;\r\n\$more = 2;\n";

    $output = (new FormrMigrator)->migrate($mixed);

    expect(substr_count($output, "\r\n"))->toBe(2)
        ->and(substr_count($output, "\n") - substr_count($output, "\r\n"))->toBe(2);
});
