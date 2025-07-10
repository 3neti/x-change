<?php

namespace App\Http\Controllers;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Voucher $voucher)
    {
        $cash = $voucher->cash;
        $qr_code = $this->gateway->generate($voucher->code, $cash->amount);

        return inertia('Vouchers/Show', [
            'voucher' => $voucher->getData(),
            'inputs' => $voucher->inputs->pluck('value', 'name')->toArray(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
