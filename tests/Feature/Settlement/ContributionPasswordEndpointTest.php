<?php

declare(strict_types=1);

use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\XChange\Services\Settlement\ContributionPasswordSession;

it('verifies a password only in the current token-bound session', function (): void {
    $envelope = Envelope::query()->create([
        'reference_code' => 'PASSWORD-TEST', 'driver_id' => 'test', 'driver_version' => '1.0.0', 'status' => 'draft',
    ]);
    $token = EnvelopeContributionToken::query()->create([
        'envelope_id' => $envelope->id, 'expires_at' => now()->addHour(), 'password' => 'correct-password',
    ]);

    $this->postJson(route('x-change.contributions.password.verify'), [
        'token' => $token->token, 'password' => 'wrong-password',
    ])->assertNotFound();
    expect(app(ContributionPasswordSession::class)->isVerified($token, session()->driver()))->toBeFalse();

    $this->postJson(route('x-change.contributions.password.verify'), [
        'token' => $token->token, 'password' => 'correct-password',
    ])->assertNoContent()->assertHeader('Cache-Control', 'no-store, private');
    expect(app(ContributionPasswordSession::class)->isVerified($token, session()->driver()))->toBeTrue();

    $token->update(['revoked_at' => now()]);
    expect(app(ContributionPasswordSession::class)->isVerified($token->fresh(), session()->driver()))->toBeFalse();
    $this->postJson(route('x-change.contributions.password.verify'), [
        'token' => $token->token, 'password' => 'correct-password',
    ])->assertNotFound();
});
