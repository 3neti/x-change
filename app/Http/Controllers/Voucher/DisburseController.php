<?php

namespace App\Http\Controllers\Voucher;

use App\Http\Controllers\Controller;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class DisburseController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $data = VoucherInstructionsData::from([
            'cash' => [
                'amount' => 50,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => null,
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => ['mobile', 'signature', 'name'],
            ],
            'feedback' => [
                'email' => $user->email,
                'mobile' => '09179876543',
//                'webhook' => 'https://company.com/webhook',
            ],
            'rider' => [
                'message' => null,
                'url' => null,
            ],
            'count' => 1, // New field for count
            'prefix' => config('x-change.generate.prefix'), // New field for prefix
            'mask' => config('x-change.generate.mask'), // New field for mask
            'ttl' => CarbonInterval::hours(24), // New field for ttl
        ]);

        return Inertia::render('Disburse', [
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'amount'     => 'required|numeric|min:1',
            'currency'   => 'required|string|size:3',

            'secret'     => 'nullable|string',
            'mobile'     => 'nullable|string',
            'country'    => 'nullable|string|size:2',
            'location'   => 'nullable|string',
            'radius'     => 'nullable|string',

            'email'      => 'nullable|email',
            'webhook'    => 'nullable|url',

            'message'    => 'nullable|string',
            'url'        => 'nullable|url',

            'count'      => 'required|integer|min:1',
            'prefix'     => 'nullable|string',
            'mask' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match("/^[\*\-]+$/", $value)) {
                        $fail('The :attribute may only contain asterisks (*) and hyphens (-).');
                    }

                    if (substr_count($value, '*') < 4) {
                        $fail('The :attribute must contain at least 4 asterisks (*).');
                    }

                    if (substr_count($value, '*') > 6) {
                        $fail('The :attribute must contain at most 6 asterisks (*).');
                    }
                },
            ],
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $instructions = VoucherInstructionsData::from([
            'cash' => [
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'validation' => [
                    'secret'   => $validated['secret'] ?? null,
                    'mobile'   => $validated['mobile'] ?? null,
                    'country'  => $validated['country'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'radius'   => $validated['radius'] ?? null,
                ],
            ],
            'inputs' => [
                'fields' => ['mobile', 'signature', 'name'], // optional: dynamically passed
            ],
            'feedback' => [
                'email'   => $validated['email'] ?? $user->email,
                'mobile'  => $validated['mobile'] ?? null,
                'webhook' => $validated['webhook'] ?? null,
            ],
            'rider' => [
                'message' => $validated['message'] ?? '',
                'url'     => $validated['url'] ?? config('x-change.redeem.success.rider'),
            ],
            'count' => $validated['count'],
            'prefix' => $validated['prefix'] ?? 'PROMO',
            'mask' => $validated['mask'] ?? '****-****-****',
            'ttl' => CarbonInterval::hours(24), // Optional: make this configurable later
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        // Example: log or dispatch the generator (you can replace this with GenerateVouchers::run($data))
        logger('[DisburseController@store] Parsed instructions', $instructions->toArray());

//        Log::debug('[DisburseController] Generated vouchers', [
//            'codes' => $vouchers->pluck('code')->all(),
//            'count' => $vouchers->count(),
//        ]);

//        dd($instructions);
        $vouchers = GenerateVouchers::run($instructions);

        return redirect()->back()
            ->with('event', [
                'name' => 'vouchers_generated',
                'data' => [
                    'vouchers' => $vouchers->pluck('code')->all(),
                ],
            ]);
    }
}
