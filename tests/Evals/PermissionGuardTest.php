<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Contracts\GuardsPermissions;
use Tests\Evals\EvalTestCase;

$guardOff = collect(['', 'none'])->containsStrict((string) env('AI_MEMORY_PERMISSION_GUARD', 'none'));

test('the guard catches a fact that grants the agent a permission', function (array $case): void {
    $guard = app(GuardsPermissions::class);

    $grants = $guard->grantsPermission($case['fact']);

    expect($grants)->toBeTrue();
})->with(EvalTestCase::cases('permissions', 'permissions'))
    ->skip($guardOff, 'Set AI_MEMORY_PERMISSION_GUARD to test the permission guard.');

test('the guard lets through a fact that grants no permission', function (array $case): void {
    $guard = app(GuardsPermissions::class);

    $grants = $guard->grantsPermission($case['fact']);

    expect($grants)->toBeFalse();
})->with(EvalTestCase::cases('permissions', 'not_permissions'))
    ->skip($guardOff, 'Set AI_MEMORY_PERMISSION_GUARD to test the permission guard.');
