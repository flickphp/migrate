<?php

declare(strict_types=1);

test('no debugging statements')
    ->arch()
    ->expect('Flick\Migrate')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray']);

test('classes use strict types')
    ->arch()
    ->expect('Flick\Migrate')
    ->toUseStrictTypes();

/*
|--------------------------------------------------------------------------
| Containment rules (audit 2026-08-16, T2-A)
|--------------------------------------------------------------------------
| The same guards flick, laravel and pro carry: nothing in this package may
| exit, write cookies, or touch session state. All pass on day one — they
| exist so a future regression fails loudly. Unlike laravel/pro, migrate has
| no symlinked flick vendor, so the plain namespace-scoped form is safe.
*/

test('the migrator never exits')
    ->arch()
    ->expect('Flick\Migrate')
    ->not->toUse(['exit', 'die']);

test('the migrator never writes cookies')
    ->arch()
    ->expect('Flick\Migrate')
    ->not->toUse(['setcookie', 'setrawcookie']);

test('the migrator never touches session state')
    ->arch()
    ->expect('Flick\Migrate')
    ->not->toUse([
        'session_start',
        'session_destroy',
        'session_regenerate_id',
        'session_status',
        'session_set_cookie_params',
        'session_write_close',
        'session_id',
    ]);
