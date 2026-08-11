<?php

use Splicewire\Sync\ScaffoldPack\FederationCascade;
use Splicewire\Sync\ScaffoldPack\FederationKind;

it('falls back to the content-type default when nothing overrides it', function () {
    $resolved = FederationCascade::resolve(
        contentDefault: FederationKind::Pointer,
        publisherOverride: null,
        installerChoice: null,
        selfHosted: false,
    );

    expect($resolved)->toBe(FederationKind::Pointer);
});

it('lets a publisher override the content-type default', function () {
    $resolved = FederationCascade::resolve(
        contentDefault: FederationKind::Copy,
        publisherOverride: FederationKind::Shadow,
        installerChoice: null,
        selfHosted: false,
    );

    expect($resolved)->toBe(FederationKind::Shadow);
});

it('lets the installer downgrade toward copy', function () {
    $resolved = FederationCascade::resolve(
        contentDefault: FederationKind::Shadow,
        publisherOverride: null,
        installerChoice: FederationKind::Copy,
        selfHosted: false,
    );

    expect($resolved)->toBe(FederationKind::Copy);
});

it('clamps an installer attempt to upgrade past the publisher/deployment ceiling', function () {
    $resolved = FederationCascade::resolve(
        contentDefault: FederationKind::Copy,
        publisherOverride: null,
        installerChoice: FederationKind::Shadow,
        selfHosted: false,
    );

    expect($resolved)->toBe(FederationKind::Copy);
});

it('forces copy on a self-hosted target regardless of every other input', function () {
    $resolved = FederationCascade::resolve(
        contentDefault: FederationKind::Shadow,
        publisherOverride: FederationKind::Shadow,
        installerChoice: FederationKind::Shadow,
        selfHosted: true,
    );

    expect($resolved)->toBe(FederationKind::Copy);
});
