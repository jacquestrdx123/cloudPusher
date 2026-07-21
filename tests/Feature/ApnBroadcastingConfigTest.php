<?php

it('exposes an apn broadcasting connection for the APNs notification channel', function () {
    expect(config('broadcasting.connections.apn'))
        ->toBeArray()
        ->toHaveKeys(['key_id', 'team_id', 'app_bundle_id', 'private_key_path', 'production']);
});

it('defaults APNs to the development gateway', function () {
    expect(config('broadcasting.connections.apn.production'))->toBeFalse();
});
