<?php
// app/Http/Controllers/Api/CutCheckController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Actions\CutCheck;

class CutCheckController extends Controller
{
    public function __construct(protected CutCheck $cutCheck) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
        ]);

        // run your LLM → voucher generator
        $vouchers = $this->cutCheck->handle($data['text']);

        $payload = $vouchers->map(fn($v) => [
            'code'       => $v->code,
            'amount'     => (string)$v->instructions->cash->getAmount(),
            'expires_at' => $v->expires_at?->toDateTimeString(),
        ]);

        return response()->json(['vouchers' => $payload]);

//        // pluck just the codes to return
//        $codes = $vouchers->pluck('code');
//
//        return response()->json([
//            'codes' => $codes,
//        ]);
    }
}
