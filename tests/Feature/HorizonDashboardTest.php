<?php

use App\Models\User;

it('allows guests to view horizon in the local environment', function () {
    $this->app['env'] = 'local';

    $this->get('/horizon')->assertOk();
});

it('allows global admins to view horizon outside local', function () {
    $this->app['env'] = 'production';

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/horizon')
        ->assertOk();
});

it('denies guests and non-admins outside local', function () {
    $this->app['env'] = 'production';

    $this->get('/horizon')->assertForbidden();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/horizon')
        ->assertForbidden();
});
