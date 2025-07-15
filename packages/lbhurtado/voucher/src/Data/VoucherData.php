<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use LBHurtado\Voucher\Models\Voucher as VoucherModel;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\{Data, DataCollection};
use LBHurtado\ModelInput\Data\InputData;
use LBHurtado\Contact\Data\ContactData;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Cash\Data\CashData;
use LBHurtado\Cash\Models\Cash;
use Illuminate\Support\Carbon;

class VoucherData extends Data
{
    public function __construct(
        public string                       $code,
        public ?ModelData                   $owner,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon                      $created_at,
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
        /** @var InputData[] */
        public DataCollection               $inputs,
        public ?CashData                    $cash = null,
        public ?ContactData                 $contact = null,
//        public ?ModelData                   $redeemer,
    ) {}

    public static function fromModel(VoucherModel $model): static
    {
        return new static(
            code: $model->code,
            owner: $model->owner
                ? ModelData::fromModel($model->owner)
                : null,
            created_at: $model->created_at,
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
            inputs: new DataCollection(InputData::class, $model->inputs),
            cash: $model->cash instanceof Cash ? CashData::fromModel($model->cash) : null,
            contact: $model->contact instanceof Contact ? ContactData::fromModel($model->contact) : null
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
