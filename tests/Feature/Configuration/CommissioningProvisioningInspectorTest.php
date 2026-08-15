<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Provisioning\CommissioningProvisioningInspector;

it('reports governed provisioning, delivery, and production API ceremony readiness without identities', function (): void {
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');

    $result = app(CommissioningProvisioningInspector::class)->inspect();

    expect($result)
        ->toMatchArray([
            'storage_ready' => true,
            'delivery_queue_ready' => true,
            'partner_api_storage_ready' => true,
            'revoked_count' => 0,
            'superseded_count' => 0,
            'production_mandate_pending_count' => 0,
            'production_client_count' => 0,
        ])
        ->not->toHaveKeys(['operator_name', 'operator_email', 'client_secret', 'claim_token']);
});
