<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface DisbursementStatusResolverContract
{
    public function resolveFromGatewayResponse(mixed $response): string;

    public function resolveFromGatewayException(\Throwable $e): string;
}
