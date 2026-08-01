<?php

namespace Splicewire\Sync;

use Splicewire\Sync\Contracts\SyncActor;

/**
 * The default {@see SyncActor}: an unscoped SYSTEM principal a background sync run applies AS.
 *
 * A sync apply through beam's `ParticleWriter` needs an actor the write gate recognizes (the gate is
 * deny-by-default; a null actor is a guest, and a guest is refused). This is the shipped principal a
 * tenant / remote / CLI target hands the bridge when there is no more specific service account — it
 * represents "the sync mechanism itself", trusted because the run was already authorized upstream (the
 * job was dispatched by an entitled operation, the remote feed passed its own auth).
 *
 * It is NOT `Authenticatable`: it deliberately does not masquerade as an application user (which would
 * let a policy method mis-scope the write to a real account). A `SyncWriteGate` authorizes it purely by
 * TYPE — it implements {@see SyncActor} — so no user-shaped surface is needed.
 */
final class SystemSyncActor implements SyncActor
{
    public function __construct(private string $name = 'system:sync') {}

    public function syncActorName(): string
    {
        return $this->name;
    }
}
