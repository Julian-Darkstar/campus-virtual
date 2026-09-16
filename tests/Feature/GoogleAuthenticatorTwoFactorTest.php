<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Laravel\Fortify\TwoFactorAuthenticatable;

it('has Fortify two factor authentication enabled', function () {
    expect(Features::enabled(Features::twoFactorAuthentication()))->toBeTrue();
});

it('user model uses the Fortify two factor trait', function () {
    $traits = class_uses_recursive(User::class);

    expect($traits)->toContain(TwoFactorAuthenticatable::class);
});

it('exposes the expected two factor management endpoints', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes());

    $uris = $routes->map(fn ($route) => $route->uri())->all();

    expect($uris)->toContain('user/two-factor-authentication')
        ->and($uris)->toContain('user/two-factor-qr-code')
        ->and($uris)->toContain('user/confirmed-two-factor-authentication')
        ->and($uris)->toContain('user/two-factor-recovery-codes')
        ->and($uris)->toContain('two-factor-challenge');
});
