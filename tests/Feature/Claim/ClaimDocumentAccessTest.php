<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\FormFlowServiceProvider;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormHandlerDocument\DocumentHandlerServiceProvider;
use LBHurtado\FormHandlerDocument\Events\DocumentSubmitted;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use LBHurtado\FormHandlerDocument\Services\StoreDocumentSubmission;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Exceptions\IncompleteClaimEvidence;
use LBHurtado\XChange\Services\Claim\ClaimDocumentAccess;
use LBHurtado\XChange\Services\Claim\ClaimDocumentEvidence;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;
use LBHurtado\XChange\Services\VoucherLifecycleService;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('supports claim documents in the new PhilHealth envelope version without changing the old version', function (): void {
    $drivers = app(DriverService::class);
    $original = $drivers->load('philhealth.bst.demo', '1.0.0');
    $updated = $drivers->load('philhealth.bst.demo', '1.1.0');
    expect($original->getDocumentType('CLAIM_DOCUMENT'))->toBeNull()
        ->and($updated->getDocumentType('CLAIM_DOCUMENT')->allowed_mimes)->toContain('application/pdf', 'image/jpeg')
        ->and($updated->getDocumentType('CLAIM_FORM'))->not->toBeNull();
});

it('authorizes only document keys issued to a claim flow with a session grant', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'DOC-AUTH',
        'metadata' => ['instructions' => ['inputs' => [
            'fields' => ['documents'],
            'documents' => [['key' => 'id', 'label' => 'Identity', 'accept' => ['image/jpeg'], 'required' => true]],
        ]]],
        'state' => 'active',
    ]);
    $flow = app(FormFlowService::class)->startFlow(FormFlowInstructionsData::from([
        'reference_id' => 'claim-DOC-AUTH-123',
        'steps' => [],
        'metadata' => ['voucher_code' => $voucher->code],
    ]));
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $access = app(ClaimDocumentAccess::class);

    expect(fn () => $access->authorizeUpload($request, $flow['flow_id'], 'id'))->toThrow(NotFoundHttpException::class);
    $access->grant($request, $voucher, $flow['flow_id']);
    $scope = $access->authorizeUpload($request, $flow['flow_id'], 'id');

    expect($scope->acceptedMimeTypes)->toBe(['image/jpeg'])
        ->and($scope->flowHash)->toHaveLength(64)
        ->and($scope->participantHash)->toHaveLength(64)
        ->and(fn () => $access->authorizeUpload($request, $flow['flow_id'], 'other'))->toThrow(NotFoundHttpException::class);
    $request->session()->forget('x-change.claim-document');
    expect(fn () => $access->authorizeUpload($request, $flow['flow_id'], 'id'))->toThrow(NotFoundHttpException::class);
});

it('accepts a claimant upload through the protected HTTP endpoint', function (): void {
    $this->app->register(MediaLibraryServiceProvider::class);
    $this->app->register(FormFlowServiceProvider::class);
    $this->app->register(DocumentHandlerServiceProvider::class);
    (require __DIR__.'/../../../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    (require __DIR__.'/../../../vendor/3neti/form-handler-document/database/migrations/2026_11_08_000001_create_form_flow_document_submissions.php')->up();
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    config()->set('document-handler.disk', 'local');
    $voucher = Voucher::query()->create([
        'code' => 'DOC-HTTP',
        'metadata' => ['instructions' => validVoucherInstructions(20, overrides: ['inputs' => [
            'fields' => ['documents'],
            'documents' => [['key' => 'id', 'label' => 'Identity', 'accept' => ['application/pdf'], 'required' => true]],
        ]])->toArray()],
        'state' => 'active',
    ]);
    $envelope = app(EnvelopeService::class)->create($voucher->code, 'x-change.documents', '1.1.0', $voucher);
    $flow = app(FormFlowService::class)->startFlow(FormFlowInstructionsData::from([
        'reference_id' => 'claim-DOC-HTTP-123', 'steps' => [], 'metadata' => ['voucher_code' => $voucher->code],
    ]));
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    app(ClaimDocumentAccess::class)->grant($request, $voucher, $flow['flow_id']);

    $response = $this->postJson(route('form-flow.documents.store', ['flowId' => $flow['flow_id'], 'key' => 'id']), [
        'file' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% sample\n"),
    ])->assertCreated()->assertJsonPath('key', 'id')->assertJsonPath('status', 'linked');
    expect($envelope->attachments()->count())->toBe(1);
    $url = route('form-flow.documents.show', ['reference' => $response->json('reference')]);
    $this->get($url)->assertOk();
    $this->flushSession();
    $this->get($url)->assertNotFound();
});

it('links a scoped upload into its voucher envelope and verifies it for claim submission', function (): void {
    $this->app->register(MediaLibraryServiceProvider::class);
    (require __DIR__.'/../../../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    (require __DIR__.'/../../../vendor/3neti/form-handler-document/database/migrations/2026_11_08_000001_create_form_flow_document_submissions.php')->up();
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    config()->set('document-handler.disk', 'local');
    $voucher = Voucher::query()->create([
        'code' => 'DOC-BRIDGE',
        'metadata' => ['instructions' => validVoucherInstructions(20, overrides: ['inputs' => [
            'fields' => ['documents'],
            'documents' => [['key' => 'id', 'label' => 'Identity', 'accept' => ['application/pdf'], 'required' => true]],
        ]])->toArray()],
        'state' => 'active',
    ]);
    $envelope = app(EnvelopeService::class)->create($voucher->code, 'x-change.documents', '1.1.0', $voucher);
    $flow = app(FormFlowService::class)->startFlow(FormFlowInstructionsData::from([
        'reference_id' => 'claim-DOC-BRIDGE-123', 'steps' => [], 'metadata' => ['voucher_code' => $voucher->code],
    ]));
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $this->app->instance('request', $request);
    $access = app(ClaimDocumentAccess::class);
    $access->grant($request, $voucher, $flow['flow_id']);
    $scope = $access->authorizeUpload($request, $flow['flow_id'], 'id');
    if (in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) && function_exists('pcntl_fork')) {
        $children = [];
        $channels = [];
        foreach ([1, 2] as $_) {
            $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($channel === false || ($pid = pcntl_fork()) === -1) {
                throw new RuntimeException('Unable to start document upload concurrency test.');
            }
            if ($pid === 0) {
                fclose($channel[0]);
                DB::purge();
                fread($channel[1], 1);
                try {
                    $uploaded = app(StoreDocumentSubmission::class)->store(
                        UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% sample\n"), $scope,
                    );
                    fwrite($channel[1], $uploaded->reference);
                    fclose($channel[1]);
                    exit(0);
                } catch (Throwable) {
                    exit(1);
                }
            }
            fclose($channel[1]);
            $children[] = $pid;
            $channels[] = $channel[0];
        }
        foreach ($channels as $channel) {
            fwrite($channel, '1');
        }
        $references = [];
        foreach ($channels as $channel) {
            $references[] = stream_get_contents($channel);
            fclose($channel);
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            expect(pcntl_wexitstatus($status))->toBe(0);
        }
        expect($references[0])->not->toBeEmpty()->toBe($references[1]);
        DB::purge();
        $submission = DocumentSubmission::query()->where('reference', $references[0])->firstOrFail();
    } else {
        $submission = app(StoreDocumentSubmission::class)->store(
            UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% sample\n"), $scope,
        );
    }
    if (in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) && function_exists('pcntl_fork')) {
        $children = [];
        $channels = [];
        foreach ([1, 2] as $_) {
            $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($channel === false || ($pid = pcntl_fork()) === -1) {
                throw new RuntimeException('Unable to start document linkage concurrency test.');
            }
            if ($pid === 0) {
                fclose($channel[0]);
                DB::purge();
                fread($channel[1], 1);
                try {
                    event(new DocumentSubmitted($flow['flow_id'], $submission));
                    exit(0);
                } catch (Throwable) {
                    exit(1);
                }
            }
            fclose($channel[1]);
            $children[] = $pid;
            $channels[] = $channel[0];
        }
        foreach ($channels as $channel) {
            fwrite($channel, '1');
            fclose($channel);
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            expect(pcntl_wexitstatus($status))->toBe(0);
        }
        DB::purge();
    } else {
        event(new DocumentSubmitted($flow['flow_id'], $submission));
        event(new DocumentSubmitted($flow['flow_id'], $submission));
    }
    app(ClaimDocumentEvidence::class)->establish($request, $voucher, $flow['flow_id']);
    app(ClaimEvidenceRequirements::class)->assertComplete($voucher, ['inputs' => ['documents' => [
        ['key' => 'id', 'reference' => $submission->reference],
    ]]]);

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')->once()->andReturn(new RedeemPayCodeResultData(
        voucher_code: $voucher->code, redeemed: true, status: 'redeemed',
        redeemer: ['mobile' => '09173011987', 'country' => 'PH'], bank_account: [], inputs: [],
        disbursement: ['status' => 'requested'], messages: ['Claim completed.'],
    ));
    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')->once()->andReturn($executor);
    $claim = (new SubmitPayCodeClaim(
        $factory, app(RecordVoucherClaim::class),
    ))->handle($voucher, [
        'mobile' => '09173011987', 'inputs' => ['documents' => [['key' => 'id', 'reference' => $submission->reference]]],
        '_meta' => ['idempotency_key' => 'claim-document-lifecycle'],
    ]);
    expect($claim->claimed)->toBeTrue();

    $attachment = $envelope->attachments()->sole();
    expect($submission->fresh()->status)->toBe('linked')
        ->and($attachment->document_id)->toBe($submission->reference)
        ->and($attachment->file_path)->toBe($submission->getFirstMedia('documents')->getPathRelativeToRoot())
        ->and(count(Storage::disk('local')->allFiles()))->toBe(1);
    $detail = app(VoucherLifecycleService::class)->showByCode($voucher->code);
    expect(data_get($detail, 'settlement_envelope.attachments.0.label'))->toBe('Identity');
    Storage::disk('local')->put($attachment->file_path, 'changed');
    expect(fn () => app(ClaimEvidenceRequirements::class)->assertComplete($voucher, ['inputs' => ['documents' => [
        ['key' => 'id', 'reference' => $submission->reference],
    ]]]))->toThrow(IncompleteClaimEvidence::class);
    Storage::disk('local')->put($attachment->file_path, "%PDF-1.4\n% sample\n");
    app(EnvelopeService::class)->reviewAttachment($attachment, 'rejected', reason: 'Please resubmit.');
    expect($submission->fresh()->status)->toBe('rejected');
    $replacement = app(StoreDocumentSubmission::class)->store(
        UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% sample\n"), $scope,
    );
    event(new DocumentSubmitted($flow['flow_id'], $replacement));
    expect($replacement->id)->not->toBe($submission->id)
        ->and($envelope->attachments()->count())->toBe(2);
});
