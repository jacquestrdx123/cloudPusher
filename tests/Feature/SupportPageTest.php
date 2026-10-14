<?php

it('renders the public support page', function () {
    config(['pushservice.invitation_ttl_days' => 7]);

    $this->get(route('support'))
        ->assertSuccessful()
        ->assertSee('Support', false)
        ->assertSee('Delete account', false)
        ->assertSee('Send messages', false)
        ->assertSee('Add team members', false)
        ->assertSee('expires after 7 days', false)
        ->assertSee('contact form', false);
});

it('shows a direct support e-mail address', function () {
    config(['pushservice.support_email' => 'support@cloudpusher.test']);

    $this->get(route('support'))
        ->assertSuccessful()
        ->assertSee('mailto:support@cloudpusher.test', false);
});

it('hides the support e-mail line when no address is configured', function () {
    config(['pushservice.support_email' => null]);

    $this->get(route('support'))
        ->assertSuccessful()
        ->assertDontSee('mailto:', false);
});
