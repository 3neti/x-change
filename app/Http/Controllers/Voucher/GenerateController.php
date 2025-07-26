<?php

namespace App\Http\Controllers\Voucher;

use App\Http\Requests\VoucherInstructionDataRequest;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Illuminate\Support\Facades\{Cache, Log};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;


//TODO: test this
class GenerateController extends Controller
{
    public function create(Request $request)
    {
        $data = Cache::get($this->getCacheKeyForUser($request->user()->id))
            ?? VoucherInstructionsData::generateFromScratch();

        return Inertia::render('Generate', [
            'data' => $data,
            'availableInputs' => VoucherInputField::valuesToCsv(),
            'labelMap' => ['kyc' => 'KYC', 'gross_monthly_income' => 'GMI']
        ]);
    }

    public function store(VoucherInstructionDataRequest $request)
    {
        $instructions = $request->getData();
        logger('[DisburseController@store] Parsed instructions', $instructions->toArray());

        Cache::put(
            $this->getCacheKeyForUser($request->user()->id),
            $instructions->toArray(),
            now()->addDays(7) // keep for 1 week for now
        );

        $vouchers = GenerateVouchers::run($instructions);

        return redirect()->back()->with('event', [
            'name' => 'vouchers_generated',
            'data' => [
                'vouchers' => $vouchers->pluck('code')->all(),
            ],
        ]);
    }

    protected function getCacheKeyForUser(int $userId): string
    {
        return "disburse.last_data.user:{$userId}";
    }
}
