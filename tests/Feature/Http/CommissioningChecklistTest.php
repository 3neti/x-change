<?php

declare(strict_types=1);

it('protects the detailed commissioning checklist with a rotated session-bound token', function (): void {
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token', 'commissioning-secret-one');

    $this->get('/x/commissioning/checklist')->assertNotFound();

    $this->post('/x/commissioning/checklist', [
        'access_token' => 'wrong-secret',
    ])->assertNotFound();

    $this->post('/x/commissioning/checklist', [
        'access_token' => 'commissioning-secret-one',
    ])->assertRedirect('/x/commissioning/checklist');

    $this->get('/x/commissioning/checklist')
        ->assertSuccessful()
        ->assertSee('Commission X-Change')
        ->assertSee('Deployment Configuration');

    config()->set('x-change.commissioning.access_token', 'commissioning-secret-two');

    $this->get('/x/commissioning/checklist')->assertNotFound();
});
