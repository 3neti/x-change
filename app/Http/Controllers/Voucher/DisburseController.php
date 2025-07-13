<?php

namespace App\Http\Controllers\Voucher;

use App\Http\Requests\VoucherInstructionDataRequest;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Propaganistas\LaravelPhone\Rules\Phone;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Illuminate\Http\Request;
use Carbon\CarbonInterval;
use Inertia\Inertia;

class DisburseController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $data = VoucherInstructionsData::from([
            'cash' => [
                'amount' => 0,
                'currency' => Number::defaultCurrency(),
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => config('x-change.generate.country'),
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => [],
//                'fields' => ['mobile', 'signature', 'name'],
            ],
            'feedback' => [
                'mobile' => null,
                'email' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
            ],
            'count' => 1, // New field for count
            'prefix' => null, // New field for prefix
            'mask' => null, // New field for mask
            'ttl' => null, // New field for ttl
        ]);

        return Inertia::render('Disburse', [
            'data' => $data,
            'availableInputs' => VoucherInputField::valuesToCsv(),
            'labelMap' => ['kyc' => 'KYC', 'gross_monthly_income' => 'GMI']
        ]);
    }

    public function store(VoucherInstructionDataRequest $request)
    {
        $user = $request->user();
//        dd($request->all());
        $validated = $request->validated();
//dd($validated);
        $instructions = VoucherInstructionsData::from([
            'cash' => [
                'amount' => $validated['cash']['amount'],
                'currency' => $validated['cash']['currency'],
                'validation' => [
                    'secret'   => $validated['cash']['validation']['secret'] ?? null,
                    'mobile'   => $validated['cash']['validation']['mobile'] ?? null,
                    'country'  => $validated['cash']['validation']['country'] ?? null,
                    'location' => $validated['cash']['validation']['location'] ?? null,
                    'radius'   => $validated['cash']['validation']['radius'] ?? null,
                ],
            ],
            'inputs' => [
                'fields' => $validated['inputs']['fields'] ?? null, // still static
//                'fields' => ['mobile', 'signature', 'name'], // still static
            ],
            'feedback' => [
                'email'   => $validated['feedback']['email'] ?? null,
                'mobile'  => $validated['feedback']['mobile'] ?? null,
                'webhook' => $validated['feedback']['webhook'] ?? null,
            ],
            'rider' => [
                'message' => $validated['rider']['message'] ?? '',
                'url'     => $validated['rider']['url'] ?? config('x-change.redeem.success.rider'),
            ],
            'count'      => $validated['count'],
            'prefix'     => $validated['prefix'] ?? config('x-change.generate.prefix'),
            'mask'       => $validated['mask'] ?? config('x-change.generate.mask'),
            'ttl'        => $validated['ttl'] ?? null,
            'starts_at'  => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        logger('[DisburseController@store] Parsed instructions', $instructions->toArray());
//dd($instructions);
        $vouchers = GenerateVouchers::run($instructions);

        return redirect()->back()->with('event', [
            'name' => 'vouchers_generated',
            'data' => [
                'vouchers' => $vouchers->pluck('code')->all(),
            ],
        ]);
    }
}
