<?php

declare(strict_types=1);

describe('Code Quality Rules', function () {

    test('app folder should not contain debug methods')
        ->expect('App')
        ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'var_export', 'ray', 'logger', 'error_log']);

    test('controllers should not use Request directly')
        ->expect('App\Http\Controllers')
        ->not->toUse(['Illuminate\Http\Request', 'Request']);

    test('app should not contain dangerous functions')
        ->expect('App')
        ->not->toUse(['eval', 'exec', 'system', 'shell_exec', 'global']);
});

describe('Architecture Rules', function () {

    test('controllers should extend base controller')
        ->expect('App\Http\Controllers')
        ->toExtend('App\Http\Controllers\Controller')
        ->ignoring(['App\Http\Controllers\Controller']);

    test('models should extend Eloquent')
        ->expect('App\Models')
        ->toExtend('Illuminate\Database\Eloquent\Model');

    test('form requests should extend FormRequest')
        ->expect('App\Http\Requests')
        ->toExtend('Illuminate\Foundation\Http\FormRequest');

    test('jobs should implement ShouldQueue')
        ->expect('App\Jobs')
        ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

});

describe('Naming Conventions', function () {
    test('controllers should have Controller suffix')
        ->expect('App\Http\Controllers')
        ->toHaveSuffix('Controller');

    test('form requests should have Request suffix')
        ->expect('App\Http\Requests')
        ->toHaveSuffix('Request');
});
