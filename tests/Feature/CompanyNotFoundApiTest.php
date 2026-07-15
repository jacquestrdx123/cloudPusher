<?php

it('returns a friendly message when a company slug does not exist', function () {
    test()->postJson(route('api.v1.auth.register', ['company' => 'alpha']), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+27821234567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertNotFound()
        ->assertJsonPath('message', 'No company found with slug "alpha".')
        ->assertJsonMissingPath('exception');
});
