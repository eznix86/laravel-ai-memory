# Managing memories

Use the `Memories` facade to make a memory page in your app:

```php
use Eznix86\AI\Memory\Enums\MemoryKind;
use Eznix86\AI\Memory\Facades\Memories;

Memories::list($context, kind: MemoryKind::Process, subject: 'customer:42', search: 'refund');

$memory = Memories::add($context, ['kind' => 'process', 'content' => 'Deploys happen on Tuesday.'], creator: $user)->memory;

Memories::edit($memory, 'Deploys happen on Thursday.', $user);
Memories::delete($memory);
Memories::export($user);
Memories::purge($workspace, 'customer:42');
```

| Method | What it does |
|---|---|
| `add` with `creator:`, `edit` | Mark the memory as written or confirmed by that person. The same checks as for the agent apply |
| `export` | Gives all memories of a user, also the archived and superseded ones |
| `purge` | Deletes all memories about one subject in the owner, for a privacy request |

## Adding memories from your application

Your app can also write memories, for example from an onboarding form, a CRM import or a team rule from an admin. Use `Memories::add()` with the context of the memory. Add `creator:` when a person wrote it:

```php
// written by the application: profile and preference memories are personal
Memories::add(new MemoryContext(user: $user), [
    'kind' => 'profile',
    'content' => 'The user is an accountant at a 20-person firm.',
    'source' => 'onboarding',
]);

// any kind for one user, with an end date
Memories::add(new MemoryContext(user: $user, owner: $team), [
    'kind' => 'context',
    'content' => 'The user is on parental leave until June.',
    'personal' => true,
    'expires_at' => now()->addMonths(3),
]);

// written by an admin, for everyone in the team: the other kinds are shared
Memories::add(new MemoryContext(owner: $team), [
    'kind' => 'process',
    'content' => 'Refunds above 500 francs need a manager.',
], creator: $admin);
```

| Writer | `source` | Archived when the context is full or the memory fades |
|---|---|---|
| A person, with `creator:` | `user`, or the source you give | Never |
| Your app, without `creator:` | `app`, or the source you give | Yes, as for the memories of the agent |

The writer checks all memories. It rejects secrets and instructions, and it can skip a fact that a close memory already says.

A rule for all users of your app is not a memory. Put it in the agent's `instructions()`.

## Who can edit memories

`MemoryPolicy` lets users edit and delete their own memories. For a shared memory, the user must be an administrator of the owner. Extend the policy to tell who the administrators are:

```php
use Eznix86\AI\Memory\Models\AgentMemory;
use Eznix86\AI\Memory\Policies\MemoryPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AgentMemoryPolicy extends MemoryPolicy
{
    protected function administers(Model $user, Model $owner): bool
    {
        return $user->isAdminOf($owner);
    }
}

Gate::policy(AgentMemory::class, AgentMemoryPolicy::class);
```
