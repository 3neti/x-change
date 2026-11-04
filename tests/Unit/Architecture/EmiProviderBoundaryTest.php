<?php

declare(strict_types=1);

arch('x-change application code does not import NetBank implementation namespaces')
    ->expect('LBHurtado\XChange')
    ->not->toUse('LBHurtado\PaymentGateway');

arch('x-change application code does not import Paynamics implementation namespaces')
    ->expect('LBHurtado\XChange')
    ->not->toUse('LBHurtado\EmiPaynamicsConstellation');

it('does not configure NetBank implementation classes in package defaults', function (): void {
    $configuration = file_get_contents(__DIR__.'/../../../config/x-change.php');

    expect($configuration)->not->toContain('LBHurtado\\PaymentGateway');
});
