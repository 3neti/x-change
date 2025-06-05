<?php

namespace LBHurtado\PaymentGateway\Contracts;

use Brick\Money\Money;

use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;
use Bavix\Wallet\Interfaces\Wallet;

interface PaymentGatewayInterface
{
    public function generate(string $account, Money $amount): string;

    /**
     * Confirm a deposit transaction sent by the payment gateway (e.g., QR Ph).
     *
     * @param array $payload  The validated deposit webhook payload.
     * @return bool Whether the confirmation was successful.
     */
    public function confirmDeposit(array $payload): bool;


    /**
     * Initiates a disbursement to the given wallet/account.
     *
     * @param Wallet $user       The user initiating the disbursement.
     * @param array $validated   The validated disbursement payload.
     * @return DisburseResponseData|bool
     */
    public function disburse(Wallet $user, DisburseInputData|array $validated): DisburseResponseData|bool;

    /**
     * Confirm a disbursement operation via its operation ID.
     *
     * @param string $operationId
     * @return bool
     */
    public function confirmDisbursement(string $operationId): bool;
}
