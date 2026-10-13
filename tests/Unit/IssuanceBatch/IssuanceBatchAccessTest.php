<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('promotes a valid batch token to a short-lived browser session', function (): void {
    $token = 'batch-token';
    $batch = new IssuanceBatch([
        'reference' => '01BATCH',
        'metadata' => ['access_token_hash' => hash('sha256', $token)],
    ]);
    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());
    $request->headers->set('X-Issuance-Batch-Token', $token);

    app(IssuanceBatchAccess::class)->authorize($request, $batch);

    expect($request->session()->get('x-change.batch-access.01BATCH'))
        ->toBeInt()
        ->toBeGreaterThanOrEqual(now()->timestamp);
});

it('rejects a batch request without a valid token or recovery session', function (): void {
    $batch = new IssuanceBatch([
        'reference' => '01BATCH',
        'metadata' => ['access_token_hash' => hash('sha256', 'correct-token')],
    ]);
    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());
    $request->headers->set('X-Issuance-Batch-Token', 'wrong-token');

    expect(function () use ($request, $batch): void {
        app(IssuanceBatchAccess::class)->authorize($request, $batch);
    })
        ->toThrow(NotFoundHttpException::class);
});
