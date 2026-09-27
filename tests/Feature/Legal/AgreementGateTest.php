<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Middleware\RequireCurrentAgreementAcceptance;
use LBHurtado\XChange\Models\AgreementAcceptance;

beforeEach(function () {
    $this->agreementPath = storage_path('framework/testing-eula.md');
    file_put_contents($this->agreementPath, <<<'MARKDOWN'
---
agreement_key: shared-host-beta-terms
version: 1.0.0-beta
title: Beta End User Agreement
effective_at: 2026-09-27
---
# Beta terms

Client Funds remain attributable to the Principal.
MARKDOWN);

    config()->set('x-change.legal.eula.enabled', true);
    config()->set('x-change.legal.eula.path', $this->agreementPath);

    Route::middleware(['web', 'auth', RequireCurrentAgreementAcceptance::class])
        ->get('/agreement-protected', fn () => response('protected'));
});

afterEach(function () {
    @unlink($this->agreementPath);
});

it('blocks authenticated access until the current agreement is accepted', function () {
    $user = actingAsTestUser();

    $this->get('/agreement-protected')
        ->assertRedirect(route('x-change.legal.eula.show'));

    $page = $this->get(route('x-change.legal.eula.show'));
    $page->assertOk()
        ->assertSee('Beta End User Agreement')
        ->assertSee('Client Funds remain attributable to the Principal.');

    $sha256 = hash('sha256', file_get_contents($this->agreementPath));
    $this->post(route('x-change.legal.eula.accept'), [
        'accepted' => '1',
        'agreement_sha256' => $sha256,
    ])->assertRedirect('/agreement-protected');

    $this->get('/agreement-protected')->assertOk()->assertSee('protected');

    $acceptance = AgreementAcceptance::query()->sole();

    expect($acceptance->subject_id)->toBe((string) $user->getKey())
        ->and($acceptance->agreement_version)->toBe('1.0.0-beta')
        ->and($acceptance->agreement_sha256)->toBe($sha256)
        ->and($acceptance->accepted_at)->not->toBeNull()
        ->and($acceptance->ip_address_hash)->toHaveLength(64)
        ->and($acceptance->user_agent_hash)->toHaveLength(64)
        ->and($acceptance->evidence_sha256)->toHaveLength(64);
});

it('requires renewed acceptance when the document changes', function () {
    actingAsTestUser();
    $sha256 = hash('sha256', file_get_contents($this->agreementPath));

    $this->post(route('x-change.legal.eula.accept'), [
        'accepted' => '1',
        'agreement_sha256' => $sha256,
    ]);

    file_put_contents($this->agreementPath, str_replace(
        'Client Funds remain attributable to the Principal.',
        'Updated Client Funds disclosure.',
        file_get_contents($this->agreementPath),
    ));

    $this->get('/agreement-protected')
        ->assertRedirect(route('x-change.legal.eula.show'));
});

it('rejects stale document evidence and never records a boolean flag', function () {
    actingAsTestUser();

    $this->post(route('x-change.legal.eula.accept'), [
        'accepted' => '1',
        'agreement_sha256' => str_repeat('0', 64),
    ])->assertSessionHasErrors('agreement_sha256');

    expect(AgreementAcceptance::query()->count())->toBe(0);
});

it('returns a machine-readable precondition response for json requests', function () {
    actingAsTestUser();

    $this->getJson('/agreement-protected')
        ->assertStatus(428)
        ->assertJsonPath('agreement.key', 'shared-host-beta-terms')
        ->assertJsonPath('agreement.version', '1.0.0-beta');
});

it('signs the user out when the agreement is declined', function () {
    actingAsTestUser();

    $this->post(route('x-change.legal.eula.decline'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('does not place the agreement gate on public claim and payment routes', function () {
    expect(Route::getRoutes()->getByName('x-change.claim.show')?->gatherMiddleware())
        ->not->toContain(RequireCurrentAgreementAcceptance::class)
        ->and(Route::getRoutes()->getByName('x-change.pay.show')?->gatherMiddleware())
        ->not->toContain(RequireCurrentAgreementAcceptance::class);
});
