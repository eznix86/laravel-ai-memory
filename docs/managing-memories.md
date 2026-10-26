# Managing memories

The `Memories` facade is the API for a memory page in your application:

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

`add` with `creator:` and `edit` mark the memory as written or confirmed by that person, and go through the same checks as the agent. `export` returns all of a user's memories, archived and superseded ones included. `purge` removes every memory about a subject in the owner, for privacy requests.

## Adding memories from your application

Your application can write memories too, for example from an onboarding form, a CRM import or a team rule an admin sets. Use `Memories::add()` with the context the memory belongs to. Pass `creator:` when a person wrote it:

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
| A person, with `creator:` | `user`, or the one you pass | Never |
| Your application, without `creator:` | `app`, or the one you pass | Yes, like the memories the agent writes |

Every memory goes through the writer: secrets and instructions are rejected, and a close memory may make it a duplicate. A rule for every user of your application is not a memory. Put it in the agent's `instructions()`.

## Who can edit memories

`MemoryPolicy` lets users update and delete their own memories. Shared memories need an owner administrator. Extend the policy to say who that is:

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
