<?php

test('welcome page renders branding and admin login link', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('cloudPusher', false)
        ->assertSee('Admin login', false)
        ->assertSee('href="/admin"', false);
});
