<?php

namespace App\Http\Controllers\Voucher;

use LBHurtado\Voucher\Data\VoucherData;
use Spatie\LaravelData\DataCollection;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ViewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $pages = 10;
        $vouchers = $user->vouchers()
            ->latest() // Orders by latest first
            ->paginate($pages) // Keep pagination enabled
            ->withQueryString(); // Keeps query parameters during navigation
//        return $vouchers;
//        return new DataCollection(VoucherData::class, $vouchers->items());
//dd(new DataCollection(VoucherData::class, $vouchers->items()));
//        dd($vouchers->total());
//        return [
//            'vouchers' => new DataCollection(VoucherData::class, $vouchers->items()),
//            'pagination' => [
//                'current_page' => $vouchers->currentPage(),
//                'last_page' => $vouchers->lastPage(),
//                'per_page' => $vouchers->perPage(),
//                'total' => $vouchers->total(),
//                'next_page_url' => $vouchers->nextPageUrl(),
//                'prev_page_url' => $vouchers->previousPageUrl(),
//            ]
//        ];
        return inertia('View', [
            'vouchers' => new DataCollection(VoucherData::class, $vouchers->items()),
            'pagination' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'next_page_url' => $vouchers->nextPageUrl(),
                'prev_page_url' => $vouchers->previousPageUrl(),
            ],
        ]);
    }
}
