<?php

it('renders the public privacy policy page', function () {
    $this->get(route('privacy'))
        ->assertSuccessful()
        ->assertSee('Privacy policy', false)
        ->assertSee('Delete account', false);
});
