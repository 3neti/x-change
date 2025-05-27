<?php

namespace LBHurtado\Voucher\Data;

use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\LaravelData\Data;

class FeedbackInstructionData extends Data
{
    public function __construct(
        public ?string $email = null,
        public ?string $mobile = null,
        public ?string $webhook = null,
    ) {}

    /**
     * Define validation rules for the data.
     *
     * @return array
     */
    public static function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'webhook' => ['nullable', 'url'],
        ];
    }

    /**
     * Create an instance of FeedbackInstructionData from a comma-delimited input string.
     *
     * @param string $text
     * @return static
     */
    public static function fromText(string $text): self
    {
        // Trim the input to account for strings with whitespace only
        if (empty(trim($text))) {
            return new static(
                email: null,
                mobile: null,
                webhook: null
            );
        }

        // Split the input by comma
        $items = array_map('trim', explode(',', $text));

        $email = null;
        $mobile = null;
        $webhook = null;

        foreach ($items as $item) {
            // If already resolved, skip further processing
            if (!$email && filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $email = $item;
                continue;
            }

            if (!$webhook && filter_var($item, FILTER_VALIDATE_URL)) {
                $webhook = $item;
                continue;
            }

            if (!$mobile && self::isValidMobile($item)) {
                $mobile = $item;
                continue;
            }
        }

        return new static(
            email: $email,
            mobile: $mobile,
            webhook: $webhook
        );
    }

    /**
     * Validate if the given string is a valid mobile number.
     *
     * @param string $mobile
     * @return bool
     */
    protected static function isValidMobile(string $mobile): bool
    {
        // Use Phone validation rule to check if it's a mobile number
        try {
            $validator = app('validator');
            $rules = ['mobile' => [(new Phone)->country('PH')->type('mobile')]];
            $data = ['mobile' => $mobile];

            $validation = $validator->make($data, $rules);

            return !$validation->fails();
        } catch (\Exception $e) {
            return false; // Return false if validation fails unexpectedly
        }
    }
}
