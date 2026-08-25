<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// No custom test case needed

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Run a PHP snippet against the sibling flick package (monorepo layout) in a
 * SUBPROCESS and return its combined output. A subprocess is required because
 * Flick's error paths call exit(), which would kill the test runner if run
 * in-process. The snippet sees `$form`, a Flick instance configured for
 * headless rendering. Returns null when the flick package is not available
 * (e.g. standalone CI checkout) so callers can skip.
 */
function renderThroughFlick(string $phpBody): ?string
{
    $autoload = __DIR__.'/../../flick/vendor/autoload.php';
    if (! file_exists($autoload)) {
        return null;
    }

    $script = '<?php require '.var_export($autoload, true).";\n"
        .'$_SERVER["REQUEST_METHOD"] = "GET";'."\n"
        .'$form = new \\Flick\\Flick(["echo" => false, "testing" => true, "csrf" => false]);'."\n"
        .$phpBody;

    $tmp = tempnam(sys_get_temp_dir(), 'flickrender');
    file_put_contents($tmp, $script);
    exec('php '.escapeshellarg($tmp).' 2>&1', $out, $rc);
    unlink($tmp);

    return implode("\n", $out);
}

/**
 * Lint a PHP source string with `php -l`. Returns the exit code (0 = clean).
 */
function lintPhp(string $code): int
{
    $tmp = tempnam(sys_get_temp_dir(), 'flickmig');
    file_put_contents($tmp, $code);
    exec('php -l '.escapeshellarg($tmp).' 2>&1', $output, $exitCode);
    unlink($tmp);

    return $exitCode;
}
