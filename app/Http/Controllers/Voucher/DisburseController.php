<?php

namespace App\Http\Controllers\Voucher;

use App\Http\Requests\VoucherInstructionDataRequest;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DisburseController extends Controller
{
    public function create(Request $request)
    {
        $cached = Cache::get($this->getCacheKeyForUser($request->user()->id));

        $data = $cached
            ? VoucherInstructionsData::from($cached)
            : VoucherInstructionsData::from($this->rawDefaultInstructions());

        return Inertia::render('Disburse', [
            'data' => $data,
            'availableInputs' => VoucherInputField::valuesToCsv(),
            'labelMap' => ['kyc' => 'KYC', 'gross_monthly_income' => 'GMI']
        ]);
    }

    public function store(VoucherInstructionDataRequest $request)
    {
        $instructions = $request->toData();
        logger('[DisburseController@store] Parsed instructions', $instructions->toArray());

        Cache::put(
            $this->getCacheKeyForUser($request->user()->id),
            $instructions->toArray(),
            now()->addDays(7) // keep for 1 week (adjust as needed)
        );

        $vouchers = GenerateVouchers::run($instructions);

        return redirect()->back()->with('event', [
            'name' => 'vouchers_generated',
            'data' => [
                'vouchers' => $vouchers->pluck('code')->all(),
            ],
        ]);
    }

    //TODO: refactor this
    protected function rawDefaultInstructions(): array
    {
        return [
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
        ];
    }
    private function getCacheKeyForUser(int $userId): string
    {
        return "disburse.last_data.user:{$userId}";
    }
}
