<?php

/**
 * Process-level tests for bin/migrate-formr: each test shells out to the real
 * CLI against fixtures in the system temp dir (never inside the package) and
 * asserts on exit codes, output, and on-disk file state.
 */
function flick_cli_run(array $args): array
{
    $bin = realpath(__DIR__.'/../../bin/migrate-formr');
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($bin);

    foreach ($args as $arg) {
        $cmd .= ' '.escapeshellarg($arg);
    }

    exec($cmd.' 2>&1', $outputLines, $exitCode);

    return ['output' => implode("\n", $outputLines), 'exit' => $exitCode];
}

function flick_cli_fixture_content(): string
{
    return <<<'PHP'
<?php
use Formr\Formr;

$form = new Formr('bootstrap');
echo $form->input_text('name');
PHP;
}

/**
 * Strip ANSI colour codes so assertions can match on plain text.
 */
function flick_cli_plain(string $text): string
{
    return preg_replace('/\033\[[0-9;]*m/', '', $text);
}

/**
 * Pull the "Changes preview:" block out of a dry-run's output as a list of
 * plain (colour-stripped, indent-stripped) diff lines.
 *
 * @return list<string>
 */
function flick_cli_preview(string $output): array
{
    $lines = explode("\n", flick_cli_plain($output));
    $preview = [];
    $inPreview = false;

    foreach ($lines as $line) {
        if (str_contains($line, 'Changes preview:')) {
            $inPreview = true;

            continue;
        }

        if (! $inPreview) {
            continue;
        }

        if (trim($line) === '') {
            break;
        }

        $preview[] = ltrim($line, ' ');
    }

    return $preview;
}

function flick_cli_rmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        @chmod($file->getPathname(), 0777);
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }

    @rmdir($dir);
}

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/flick-cli-'.bin2hex(random_bytes(6));
    mkdir($this->tmpDir, 0777, true);
});

afterEach(function () {
    flick_cli_rmdir($this->tmpDir);
});

describe('migrate-formr CLI', function () {
    test('rejects an unknown flag with exit 1 and does not touch the target file', function () {
        $file = $this->tmpDir.'/app.php';
        file_put_contents($file, flick_cli_fixture_content());

        // A typo like --dryrun must never fall through to an in-place rewrite.
        $result = flick_cli_run([$file, '--dryrun']);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('Unknown option: --dryrun')
            ->and(file_get_contents($file))->toBe(flick_cli_fixture_content());
    });

    test('rejects more than one path argument with exit 1 and modifies nothing', function () {
        $one = $this->tmpDir.'/one.php';
        $two = $this->tmpDir.'/two.php';
        file_put_contents($one, flick_cli_fixture_content());
        file_put_contents($two, flick_cli_fixture_content());

        $result = flick_cli_run([$one, $two]);

        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('Multiple paths provided')
            ->and(file_get_contents($one))->toBe(flick_cli_fixture_content())
            ->and(file_get_contents($two))->toBe(flick_cli_fixture_content());
    });

    test('reports an unreadable file and continues migrating the rest', function () {
        $unreadable = $this->tmpDir.'/unreadable.php';
        $normal = $this->tmpDir.'/normal.php';
        file_put_contents($unreadable, flick_cli_fixture_content());
        file_put_contents($normal, flick_cli_fixture_content());
        chmod($unreadable, 0000);

        $result = flick_cli_run([$this->tmpDir]);

        // A read failure now makes the run exit non-zero so CI can detect it
        // (mig-L1), while the readable file is still migrated.
        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('Could not read file')
            ->and($result['output'])->toContain('unreadable.php')
            // the readable file was still migrated
            ->and(file_get_contents($normal))->toContain('new Flick')
            ->and(file_get_contents($normal))->not->toContain('new Formr');
    })->skip(
        PHP_OS_FAMILY === 'Windows',
        'chmod cannot make a file unreadable to its owner on Windows'
    );
    // Windows has no owner-read bit for chmod to clear: the file stays readable
    // and only turns read-only, so the run reports "Could not write file" and
    // this scenario cannot be built. The read-only half of the behaviour -- exit
    // non-zero, keep migrating the rest -- is covered there by the unwritable
    // test immediately below, which does reproduce on both platforms.

    test('reports an unwritable file, does not count it as modified, exits non-zero, and migrates the rest', function () {
        $readonly = $this->tmpDir.'/readonly.php';
        $normal = $this->tmpDir.'/normal.php';
        file_put_contents($readonly, flick_cli_fixture_content());
        file_put_contents($normal, flick_cli_fixture_content());
        chmod($readonly, 0444);

        $result = flick_cli_run([$this->tmpDir]);

        // A write failure makes the run exit non-zero (mig-L1).
        expect($result['exit'])->toBe(1)
            ->and($result['output'])->toContain('Could not write file')
            ->and($result['output'])->toContain('readonly.php')
            ->and($result['output'])->toMatch('/Files modified:\s+1\b/')
            // the read-only file is untouched on disk
            ->and(file_get_contents($readonly))->toBe(flick_cli_fixture_content())
            ->and(file_get_contents($normal))->toContain('new Flick')
            // no orphan backup left behind for the file that failed to write
            ->and(file_exists($readonly.'.bak'))->toBeFalse();
    });

    test('mig-M2: writes a .bak backup of the original before overwriting in place', function () {
        $file = $this->tmpDir.'/app.php';
        $original = flick_cli_fixture_content();
        file_put_contents($file, $original);

        $result = flick_cli_run([$file]);

        expect($result['exit'])->toBe(0)
            // the file was migrated in place
            ->and(file_get_contents($file))->toContain('new Flick')
            // a backup of the untouched original sits alongside it
            ->and(file_exists($file.'.bak'))->toBeTrue()
            ->and(file_get_contents($file.'.bak'))->toBe($original);
    });

    test('mig-M2: a dry run modifies nothing and writes no backup', function () {
        $file = $this->tmpDir.'/app.php';
        $original = flick_cli_fixture_content();
        file_put_contents($file, $original);

        $result = flick_cli_run([$file, '--dry-run']);

        expect($result['exit'])->toBe(0)
            ->and(file_get_contents($file))->toBe($original)
            ->and(file_exists($file.'.bak'))->toBeFalse();
    });

    test('mig-C1/L2: a file that only mentions formr in a comment is left byte-identical', function () {
        $file = $this->tmpDir.'/notes.php';
        $content = <<<'PHP'
<?php
// migrated away from formr last year
$cart = $session->get('cart', 'default');
PHP;
        file_put_contents($file, $content);

        $result = flick_cli_run([$file]);

        // No structural Formr class reference -> skipped entirely, no corruption
        // of $session->get() and no backup written.
        expect(file_get_contents($file))->toBe($content)
            ->and(file_exists($file.'.bak'))->toBeFalse();
    });

    test('a single unwritable Formr file does not report that no Formr code was found', function () {
        $file = $this->tmpDir.'/readonly.php';
        file_put_contents($file, flick_cli_fixture_content());
        chmod($file, 0444);

        $result = flick_cli_run([$file]);

        // The headline used to key off "files modified", so the one file that
        // failed to write printed "No Formr code found to migrate." directly
        // above "1 file(s) could not be read or written".
        expect($result['exit'])->toBe(1)
            ->and($result['output'])->not->toContain('No Formr code found')
            ->and($result['output'])->toContain('Files processed:')
            ->and($result['output'])->toContain('could not be read or written');
    });

    test('a second run over an already-migrated file does not report that no Formr code was found', function () {
        $file = $this->tmpDir.'/app.php';
        file_put_contents($file, flick_cli_fixture_content());

        flick_cli_run([$file]);
        $second = flick_cli_run([$file]);

        // The docs tell people to dry-run and then apply, so a re-run over
        // already-migrated `new Flick(...)` code is the normal workflow.
        expect($second['exit'])->toBe(0)
            ->and($second['output'])->not->toContain('No Formr code found')
            ->and($second['output'])->toContain('Files processed:');
    });

    test('a directory with no Formr code still reports that none was found', function () {
        file_put_contents($this->tmpDir.'/a.php', "<?php\n\$cart = \$session->get('cart', 'default');\n");
        file_put_contents($this->tmpDir.'/b.php', "<?php\necho 'hello';\n");

        $result = flick_cli_run([$this->tmpDir]);

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->toContain('No Formr code found');
    });

    test('the dry-run preview never reports an unchanged line as an addition', function () {
        $file = $this->tmpDir.'/external.php';
        $content = <<<'PHP'
<?php
use Formr\Formr;

$email = $form->post('email', 'Email', 'required');
echo $email;
PHP;
        file_put_contents($file, $content);

        $result = flick_cli_run([$file, '--dry-run']);
        $preview = flick_cli_preview($result['output']);

        // The form instance lives in another file, so the only line the
        // migrator rewrites is the `use` statement (plus the inserted TODO).
        // Every other source line survives untouched and must not be
        // reported as an addition just because the insertion shifted it.
        $unchanged = array_values(array_filter(
            array_map('trim', explode("\n", $content)),
            fn (string $line) => $line !== '' && $line !== 'use Formr\Formr;'
        ));

        $added = array_map(
            fn (string $line) => trim(substr($line, 1)),
            array_values(array_filter($preview, fn (string $line) => str_starts_with($line, '+')))
        );

        expect($preview)->not->toBeEmpty()
            ->and(array_values(array_intersect($added, $unchanged)))->toBe([]);
    });

    test('the dry-run preview budgets source lines and reports how many it suppressed', function () {
        $file = $this->tmpDir.'/big.php';
        $lines = ['<?php', "\$form = new Formr('bootstrap');", 'if (true) {'];
        for ($i = 1; $i <= 50; $i++) {
            $lines[] = "    echo \$form->input_text('field{$i}');";
        }
        $lines[] = '}';
        file_put_contents($file, implode("\n", $lines));

        $result = flick_cli_run([$file, '--dry-run']);
        $preview = flick_cli_preview($result['output']);

        $removed = array_filter($preview, fn (string $line) => str_starts_with($line, '-'));

        // The budget is spent on source lines, not on emitted lines, so a
        // 20-line budget shows 20 source lines - and the marker says how
        // many changed lines it held back. Lines are emitted verbatim, so
        // indentation stays visible.
        expect(count($removed))->toBeGreaterThanOrEqual(20)
            ->and(implode("\n", $preview))->toMatch('/\.\.\. \(\d+ more changed lines? not shown\)/')
            ->and($preview)->toContain("-     echo \$form->input_text('field1');");
    });

    test('skips vendor, node_modules, and hidden directories during directory walks', function () {
        mkdir($this->tmpDir.'/vendor/formr', 0777, true);
        mkdir($this->tmpDir.'/node_modules/pkg', 0777, true);
        mkdir($this->tmpDir.'/.hidden', 0777, true);

        $app = $this->tmpDir.'/app.php';
        $vendored = $this->tmpDir.'/vendor/formr/formr.php';
        $noded = $this->tmpDir.'/node_modules/pkg/helper.php';
        $hidden = $this->tmpDir.'/.hidden/secret.php';

        foreach ([$app, $vendored, $noded, $hidden] as $file) {
            file_put_contents($file, flick_cli_fixture_content());
        }

        $result = flick_cli_run([$this->tmpDir]);

        expect($result['exit'])->toBe(0)
            // only app.php was discovered at all
            ->and($result['output'])->toContain('Scanning 1 PHP file(s)')
            ->and(file_get_contents($app))->toContain('new Flick')
            // excluded trees are byte-identical
            ->and(file_get_contents($vendored))->toBe(flick_cli_fixture_content())
            ->and(file_get_contents($noded))->toBe(flick_cli_fixture_content())
            ->and(file_get_contents($hidden))->toBe(flick_cli_fixture_content());
    });
});
