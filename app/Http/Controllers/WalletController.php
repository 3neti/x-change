<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Services\DepositQRCode;
use Illuminate\Http\Request;
use Brick\Money\Money;
use App\Models\User;

class WalletController extends Controller
{
    public function __construct(protected DepositQRCode $qrcode){}

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = Money::of($validated['amount'], 'PHP');
        $user = Auth::user();

        try {
            if (!$user instanceof User) {
                throw new \RuntimeException('The user must implement ChannelsInterface to generate QR code.');
            }
            $qrCode = $this->qrcode->generate($user, $amount);

            return response()->json([
                'success' => true,
                'qr_code' => $qrCode,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
