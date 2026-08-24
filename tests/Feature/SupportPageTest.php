<?php

it('renders the public support page', function () {
    $this->get(route('support'))
        ->assertSuccessful()
        ->assertSee('Support', false)
        ->assertSee('Delete account', false)
        ->assertSee('contact form', false);
});
