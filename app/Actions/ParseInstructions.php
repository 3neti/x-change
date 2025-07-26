<?php

namespace App\Actions;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\InstructionParser;

class ParseInstructions
{
    use AsAction;

    public function __construct(
        protected InstructionParser $parser
    ) {}

    /**
     * Parse raw instruction text into a structured VoucherInstructionsData object.
     *
     * @param  string  $text
     * @return \LBHurtado\Voucher\Data\VoucherInstructionsData
     * @todo explicitly add owner in the parameter
     */
    public function handle(string $text): VoucherInstructionsData
    {
        return $this->parser->fromText($text);
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string',
        ];
    }

    public function asController(ActionRequest $request)
    {
        $validated = $request->validated();

        return $this->handle($validated['text']);
    }
}
