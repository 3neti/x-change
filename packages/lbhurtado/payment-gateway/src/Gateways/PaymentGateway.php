<?php

namespace LBHurtado\PaymentGateway\Gateways;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Contracts\HasMerchantInterface;
use LBHurtado\PaymentGateway\Actions\TopupWalletAction;
use LBHurtado\PaymentGateway\Data\DepositResponseData;
use LBHurtado\PaymentGateway\Events\DepositConfirmed;
use Illuminate\Support\Facades\Http;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Log;
use Brick\Money\Money;

use LBHurtado\PaymentGateway\Data\GatewayResponseData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

use Bavix\Wallet\Models\Transaction;

use LBHurtado\PaymentGateway\Events\DisbursementConfirmed;
use Illuminate\Validation\Rule;
use LBHurtado\PaymentGateway\Support\Address;


abstract class PaymentGateway
{

}
