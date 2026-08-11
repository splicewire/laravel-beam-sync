<?php

namespace Splicewire\Sync\ScaffoldPack;

/**
 * The federation_kind cascade (scaffold-pack-content-kind-registry-federation ticket 02,
 * ADR-0193): content-type default → publisher override → installer choice, where the installer
 * may only DOWNGRADE toward {@see FederationKind::Copy} (never upgrade past what the
 * publisher/deployment permit), and a self-hosted target is mechanically forced to `Copy`
 * regardless of every other input.
 *
 * Pure function over {@see FederationKind} values — no model, no persistence. The caller
 * (e.g. `GrantService`) is responsible for knowing what its content-type default is and whether
 * the target deployment is self-hosted.
 */
final class FederationCascade
{
    /**
     * @param  FederationKind  $contentDefault  the content-kind's documented default
     * @param  FederationKind|null  $publisherOverride  the publisher's chosen ceiling, if any
     * @param  FederationKind|null  $installerChoice  what the installer asked for, if anything
     * @param  bool  $selfHosted  true forces `Copy` unconditionally
     */
    public static function resolve(
        FederationKind $contentDefault,
        ?FederationKind $publisherOverride,
        ?FederationKind $installerChoice,
        bool $selfHosted,
    ): FederationKind {
        if ($selfHosted) {
            return FederationKind::Copy;
        }

        $ceiling = $publisherOverride ?? $contentDefault;

        if ($installerChoice === null) {
            return $ceiling;
        }

        return $installerChoice->rank() <= $ceiling->rank() ? $installerChoice : $ceiling;
    }
}
