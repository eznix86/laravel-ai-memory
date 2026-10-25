<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Contracts\GuardsPermissions;
use Tests\Integration\IntegrationTestCase;

$guardOff = collect(['', 'none'])->containsStrict((string) env('MEMORY_PERMISSION_GUARD', 'none'));

test('the guard catches a fact that grants the agent a permission', function (array $case): void {
    $guard = app(GuardsPermissions::class);

    $grants = $guard->grantsPermission($case['fact']);

    expect($grants)->toBeTrue();
})->with(IntegrationTestCase::cases('permissions', 'permissions'))
    ->skip($guardOff, 'Set MEMORY_PERMISSION_GUARD to test the permission guard.');

test('the guard lets through a fact that grants no permission', function (array $case): void {
    $guard = app(GuardsPermissions::class);

    $grants = $guard->grantsPermission($case['fact']);

    expect($grants)->toBeFalse();
})->with(IntegrationTestCase::cases('permissions', 'not_permissions'))
    ->skip($guardOff, 'Set MEMORY_PERMISSION_GUARD to test the permission guard.');
