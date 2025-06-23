<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use LBHurtado\Voucher\Models\Voucher as VoucherModel;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use LBHurtado\Contact\Data\ContactData;
use LBHurtado\Cash\Data\CashData;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class VoucherData extends Data
{
    public function __construct(
        public string                       $code,
        public ?ModelData                   $owner,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $starts_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $expires_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $redeemed_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $processed_on,
        public bool                         $processed,
        public ?VoucherInstructionsData     $instructions,
        public ?CashData                    $cash = null,
        public ?ContactData                 $contact = null
//        public ?ModelData                   $redeemer,
    ) {}

    public static function fromModel(VoucherModel $model): static
    {
        return new static(
            code: $model->code,
            owner: $model->owner
                ? ModelData::fromModel($model->owner)
                : null,
            starts_at: $model->starts_at,
            expires_at: $model->expires_at,
            redeemed_at: $model->redeemed_at,
            processed_on: $model->processed_on,
            processed: $model->processed,
            instructions: $model->instructions instanceof VoucherInstructionsData
                ? $model->instructions
                : ($model->instructions
                    ? VoucherInstructionsData::from($model->instructions)
                    : null
                ),
            cash: $model->cash instanceof CashData ? $model->cash : null,
            contact: $model->contact instanceof ContactData ? $model->contact : null,
//            redeemer: $model->redeemer
//                ? ModelData::fromModel($model->redeemer)
//                : null,
        );
    }
}

class ModelData extends Data
{
    public function __construct(
        public string      $name,
        public string      $email,
        public ?string     $mobile
    ){}

    public static function fromModel($model): static
    {
        return new static(
            name: $model->name,
            email: $model->email,
            mobile: $model->mobile ?? null,
        );
    }
}
