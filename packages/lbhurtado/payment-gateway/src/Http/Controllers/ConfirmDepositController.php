<?php

namespace LBHurtado\PaymentGateway\Http\Controllers;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Illuminate\Http\{Request, Response};
use Illuminate\Routing\Controller;

class ConfirmDepositController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway){}

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'alias' => ['required', 'string'],
            'amount' => ['required', 'int', 'min:1'],
            'channel' => ['required', 'string'],
            'commandId' => ['required', 'int'],
            'externalTransferStatus' => ['required', 'string'],
            'operationId' => ['required', 'int'],
            'productBranchCode' => ['required', 'string'],
            'recipientAccountNumber' => ['required', 'string'],
            'recipientAccountNumberBankFormat' => ['required', 'string'],
//            'referenceCode' =>  ['required', 'string', 'exists:users,mobile'],
            'referenceCode' => ['required', 'string'],
            'referenceNumber' => ['required', 'string'],
            'registrationTime' => ['required', 'string'],
            'remarks' => ['required', 'string'],
            'sender' => ['required', 'array'],
            'sender.accountNumber' => ['required', 'string'],
            'sender.institutionCode' => ['required', 'string'],
            'sender.name' => ['required', 'string'],
            'transferType' => ['required', 'string'],
            'merchant_details' => ['nullable', 'array'],
            'merchant_details.merchant_code' => ['nullable', 'string'],
            'merchant_details.merchant_account' => ['nullable', 'string'],
        ]);

        $this->gateway->confirmDeposit($validated);

        return response()->noContent();
    }
}
