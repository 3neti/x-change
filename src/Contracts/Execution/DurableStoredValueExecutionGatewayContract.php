<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Execution;

use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;

/**
 * Marker contract for gateways whose balances and operations survive process
 * boundaries and are committed through an atomic financial ledger.
 */
interface DurableStoredValueExecutionGatewayContract extends StoredValueExecutionGateway {}
