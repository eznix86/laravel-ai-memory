<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Policies\MemoryPolicy;
use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\User;
use Tests\Fixtures\Workspace;

test('the documented example: a team admin can edit a shared memory, a member cannot', function (): void {
    $policy = new class extends MemoryPolicy
    {
        protected function administers(Model $user, Model $owner): bool
        {
            return $owner->getAttribute('admin_id') === $user->getKey();
        }
    };

    $team = (new Workspace)->forceFill(['admin_id' => 'u-1']);
    $admin = (new User)->forceFill(['id' => 'u-1']);
    $member = (new User)->forceFill(['id' => 'u-2']);
    $shared = (new AgentMemory)->setRelation('owner', $team);

    expect($policy->update($admin, $shared))->toBeTrue()
        ->and($policy->update($member, $shared))->toBeFalse();
});
