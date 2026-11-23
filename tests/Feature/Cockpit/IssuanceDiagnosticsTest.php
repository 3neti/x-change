<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LBHurtado\XChange\Http\Middleware\JournalIssuanceAttempt;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Cockpit\IssuanceDiagnosticJournal;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

it('records an owner bound browser report once without financial or private data', function () {
    $owner = actingAsTestUser();
    $reference = (string) Str::uuid();
    $payload = ['reference' => $reference, 'http_status' => 403, 'content_type' => 'text/html', 'request_id' => 'edge-123', 'url_evidence' => ['editor' => ['present' => true, 'length' => 42, 'fingerprint' => str_repeat('a', 64)]]];
    $this->postJson('/x/cockpit/quick-generate/diagnostics', $payload)->assertAccepted()->assertJsonPath('recorded', true);
    $this->postJson('/x/cockpit/quick-generate/diagnostics', $payload)->assertAccepted();
    $entry = ExecutionJournalEntry::query()->where('event_type', 'issuance.browser_reported')->sole();
    expect((string) $entry->actor_id)->toBe((string) $owner->getKey());
    expect($entry->money)->toBeNull();
    expect($entry->payload['source'])->toBe('browser_reported');
    expect($entry->payload['http_status'])->toBe(403);
});

it('rejects raw instructions and forged actor or event data', function () {
    actingAsTestUser();
    $this->postJson('/x/cockpit/quick-generate/diagnostics', ['reference' => (string) Str::uuid(), 'actor_id' => 99, 'event_type' => 'issuance.succeeded', 'instructions' => ['rider' => ['url' => 'https://private.test']]])->assertUnprocessable();
    expect(ExecutionJournalEntry::query()->where('event_type', 'issuance.browser_reported')->count())->toBe(0);
});

it('requires an authenticated session for diagnostic reports', function () {
    $this->postJson('/x/cockpit/quick-generate/diagnostics', ['reference' => (string) Str::uuid()])->assertUnauthorized();
});

it('keeps reports with the same reference separate for different actors and retains the first observation', function () {
    $first = actingAsTestUser();
    $reference = (string) Str::uuid();
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => $reference, 'http_status' => 403])->assertAccepted();
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => $reference, 'http_status' => 200])->assertAccepted();
    $second = actingAsTestUser();
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => $reference, 'http_status' => 419])->assertAccepted();
    $entries = ExecutionJournalEntry::query()->where('event_type', 'issuance.browser_reported')->get()->keyBy('actor_id');
    expect($entries)->toHaveCount(2)
        ->and($entries[$first->getKey()]->payload['http_status'])->toBe(403)
        ->and($entries[$second->getKey()]->payload['http_status'])->toBe(419)
        ->and(ExecutionJournalEntry::query()->whereNotNull('money')->count())->toBe(0);
});

it('rejects unsafe diagnostic fields without recording them', function (array $extra) {
    actingAsTestUser();
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => (string) Str::uuid(), ...$extra])->assertUnprocessable();
    expect(ExecutionJournalEntry::query()->count())->toBe(0);
})->with([
    'raw URL' => [['url_evidence' => ['editor' => ['present' => true, 'length' => 12, 'url' => 'https://private.test']]]],
    'invalid fingerprint' => [['url_evidence' => ['submitted' => ['present' => true, 'length' => 12, 'fingerprint' => 'not-a-hash']]]],
    'nested identity' => [['url_evidence' => ['editor' => ['present' => true, 'length' => 12, 'actor_id' => 999]]]],
    'private content type' => [['content_type' => 'text/html; secret=value']],
    'untrusted request id' => [['request_id' => 'https://private.test/?token=secret']],
    'unbounded fields' => [['error_fields' => array_fill(0, 21, 'rider.url')]],
    'arbitrary field content' => [['error_fields' => ['https://private.test/?token=secret']]],
]);

it('bounds diagnostic payload size and rate limits reports', function () {
    actingAsTestUser();
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => (string) Str::uuid(), 'request_id' => str_repeat('a', 9000)])->assertStatus(413);
    for ($index = 0; $index < 11; $index++) {
        $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => (string) Str::uuid()])->assertAccepted();
    }
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => (string) Str::uuid()])->assertTooManyRequests();
});

it('returns a safe unavailable result when journal delivery fails', function () {
    actingAsTestUser();
    $this->mock(ExecutionJournalRecorder::class)
        ->shouldReceive('record')->once()->andThrow(new RuntimeException('private credential must not escape'));
    $reference = (string) Str::uuid();
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => $reference])
        ->assertServiceUnavailable()->assertExactJson(['reference' => $reference, 'recorded' => false])
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(ExecutionJournalEntry::query()->count())->toBe(0);
});

it('requires request forgery protection for diagnostic reports', function () {
    actingAsTestUser();
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $this->postJson(route('x-change.cockpit.quick-generate.diagnostics'), ['reference' => (string) Str::uuid()])->assertStatus(419);
    expect(ExecutionJournalEntry::query()->count())->toBe(0);
});

it('fingerprints submitted URL and resolves only owned template metadata', function () {
    $owner = actingAsTestUser();
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey(),
        'name' => 'Double A', 'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['rider' => ['url' => 'https://example.test/original?secret=hidden']],
        'include_amount' => true, 'include_purpose' => true, 'status' => 'active',
    ]);
    $request = Request::create('/x/cockpit/quick-generate', 'POST', [
        'rider' => ['url' => 'https://example.test/edited?secret=hidden'],
        'feedback' => ['mobile' => '09175550123'],
        'metadata' => ['custom' => ['cockpit' => ['saved_template' => ['reference' => $template->reference]]]],
    ]);
    $request->setUserResolver(fn () => $owner);
    $journal = app(IssuanceDiagnosticJournal::class);
    $evidence = $journal->requestEvidence($request);
    expect($evidence['template_reference'])->toBe($template->reference)
        ->and($evidence['template_url_changed'])->toBeTrue()
        ->and($evidence['submitted_url']['fingerprint'])->toBe(hash('sha256', $request->input('rider.url')))
        ->and(json_encode($evidence))->not->toContain('hidden', '09175550123', 'https://');
    $other = actingAsTestUser();
    $request->setUserResolver(fn () => $other);
    $evidence = $journal->requestEvidence($request);
    expect($evidence['template_reference'])->toBeNull()->and($evidence['template_url_changed'])->toBeNull();
});

it('journals real issuance validation without retaining rejected instruction or contacts', function () {
    actingAsTestUser();
    $reference = (string) Str::uuid();
    $this->withHeader('X-XChange-Submission-Reference', $reference)
        ->postJson(route('x-change.cockpit.quick-generate.store'), [
            'cash' => ['amount' => -1, 'currency' => 'PHP'],
            'rider' => ['url' => 'not a URL with secret'],
            'feedback' => ['mobile' => 'private-mobile'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['cash.amount', 'rider.url']);
    $entries = ExecutionJournalEntry::query()->where('correlation_id', $reference)->get();
    expect($entries->pluck('event_type')->all())->toContain('issuance.received', 'issuance.validation_rejected')
        ->and($entries->toJson())->not->toContain('private-mobile', 'not a URL with secret')
        ->and($entries->whereNotNull('money'))->toBeEmpty();
});

it('records successful and failed server responses without changing their results even if the journal fails', function (bool $unavailable) {
    $owner = actingAsTestUser();
    if ($unavailable) {
        $this->mock(ExecutionJournalRecorder::class)
            ->shouldReceive('record')->andThrow(new RuntimeException('journal is offline'));
    }
    $request = Request::create('/test', 'POST');
    $request->setUserResolver(fn () => $owner);
    $request->headers->set('X-XChange-Submission-Reference', (string) Str::uuid());
    $middleware = app(JournalIssuanceAttempt::class);
    $response = $middleware->handle($request, fn () => response()->json(['result' => ['code' => 'SIMULATED']], 201));
    expect($response->getStatusCode())->toBe(201)->and($response->getData(true)['result']['code'])->toBe('SIMULATED');
    $failedRequest = clone $request;
    $failedRequest->headers->set('X-XChange-Submission-Reference', (string) Str::uuid());
    $failure = $middleware->handle($failedRequest, fn () => response()->json(['exception' => 'private stack trace'], 503));
    expect($failure->getStatusCode())->toBe(503);
    if (! $unavailable) {
        $entry = ExecutionJournalEntry::query()->where('event_type', 'issuance.succeeded')->sole();
        expect($entry->payload['result_code'])->toBe('SIMULATED')
            ->and($entry->payload['source'])->toBe('server_confirmed')
            ->and($entry->money)->toBeNull()
            ->and(ExecutionJournalEntry::query()->get()->toJson())->not->toContain('private stack trace');
    }
})->with([false, true]);

it('does not interrupt issuance when journal persistence is unavailable', function () {
    $owner = actingAsTestUser();
    Schema::drop('execution_journal_entries');
    $request = Request::create('/test', 'POST');
    $request->setUserResolver(fn () => $owner);
    $response = app(JournalIssuanceAttempt::class)
        ->handle($request, fn () => response()->json(['result' => ['code' => 'SIMULATED']], 201));
    expect($response->getStatusCode())->toBe(201)->and($response->getData(true)['result']['code'])->toBe('SIMULATED');
});
