<?php

namespace App\Services;

use App\Exceptions\InstructionParseException;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use App\Services\OpenAI\Client as OpenAIClient;
use Carbon\CarbonInterval;
use LBHurtado\Voucher\Enums\VoucherInputField;

class InstructionParser
{
    public function __construct(private OpenAIClient $openAI) {}

    public function fromText(string $text): VoucherInstructionsData
    {
        Log::debug('[InstructionParser] Raw instruction text:', ['text' => $text]);

        // 1) Strong system prompt
        $system = <<<'TXT'
You are a voucher‐creation / digital‐check assistant.
Any time the user says “voucher,” “check,” or “cut a check,” you should behave identically.

If and when the amount is missing just make it PHP zero.

Whenever the user says “payable to <NUMBER>” or “to account <NUMBER>,”
— fill `"cash"."validation"."mobile"` with that number.
If they don’t mention “payable to,” leave `"mobile": null`.

Support an optional `"post_date"` ISO-8601 date if they say “post date X” or “dated X”.
If they do not mention a post-date, emit `"post_date": null`.

Here is the exact JSON schema you must output—nothing else:

```jsonc
{
  "cash": {
    "amount": integer,
    "currency": string,
    "validation": {
      "secret": string|null,     // only if user says “secret is …”
      "mobile": string|null,     // fill if user says “payable to X”
      "country": string,
      "location": string,
      "radius": string
    }
  },
  "inputs": { "fields": string[] },
  "feedback": { "email": string, "mobile": string, "webhook": string },
  "rider":   { "message": string, "url": string },
  "count":   integer,
  "prefix":  string,
  "mask":    string,
  "ttl":     string,
  "post_date": string|null      // ISO date e.g. "2025-07-01" when user says “post-date July 1st”
}

*Important:*
– If the user’s text does *not* include the word “secret,” then emit `"secret": null`.
– Do **not** infer or invent a secret value unless the word “secret” appears in their instruction.

Examples:

User: “Create 3 vouchers with $10 each, radius 100m, no secret.”
→ "validation": { "secret": null, "mobile": "...", … }

User: “Create 5 vouchers with $20 each. Secret is ABC123.”
→ "validation": { "secret": "ABC123", "mobile": "...", … }

User: “Cut me a check for 5,000 PHP payable to 09171234567, secret ABC, post-date 2025-07-01.”
→ your JSON must have
"cash": {
  "amount": 5000,
  "currency": "PHP",
  "validation":{
    "secret":"ABC",
    "mobile":"09171234567",
    …
  }
},
"post_date":"2025-07-01",
…
TXT;

        Log::debug('[InstructionParser] Sending to OpenAI.chat.create', [
            'model'      => 'gpt-4',
            'system'     => $system,
            'user_input' => $text,
        ]);

        $resp = $this->openAI->chat()->create([
            'model'       => 'gpt-4',
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $text],
            ],
            'temperature' => 0.0,
        ]);

        $raw = $resp['choices'][0]['message']['content'] ?? '';
        Log::debug('[InstructionParser] OpenAI raw response:', ['raw' => $raw]);

        // 2) Decode JSON (assoc array)
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[InstructionParser] JSON decode error', ['error' => json_last_error_msg()]);
            throw new InstructionParseException("Invalid JSON from AI: {$raw}");
        }

        // 2a) If it’s wrapped in a singleton array, unwrap it
        if (isset($decoded[0]) && is_array($decoded) && count($decoded) === 1) {
            Log::warning('[InstructionParser] Unwrapped singleton array payload');
            $decoded = $decoded[0];
        }

        // 3) Merge defaults so missing keys get a null (or empty) placeholder
        $defaults = [
            'cash'     => ['amount' => null, 'currency' => null, 'validation' => [
                'secret'   => null,
                'mobile'   => null,
                'country'  => null,
                'location' => null,
                'radius'   => null,
            ]],
            'inputs'   => ['fields' => []],
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider'    => ['message' => null, 'url' => null],
            'count'    => null,
            'prefix'   => null,
            'mask'     => null,
            'ttl'      => null,
        ];

        // 3) Merge defaults so missing keys get a null (or empty) placeholder
        $merged = array_replace_recursive($defaults, $decoded);
        Log::debug('[InstructionParser] Merged instruction array:', ['merged' => $merged]);


        // ────────────────────────────────────────────────────────────────────────────
        // ✂️  Sanitise inputs.fields so we only keep the enum values we actually support
        // ────────────────────────────────────────────────────────────────────────────
        $allowed = array_column(VoucherInputField::cases(), 'value');
        $rawFields = $merged['inputs']['fields'] ?? [];
        $filtered = array_values(
            array_intersect($rawFields, $allowed)
        );

        if (empty($filtered)) {
            // fallback to your default if nothing matched
            $filtered = config('instructions.input_fields', $allowed);
            Log::warning('[InstructionParser] inputs.fields contained no valid values; falling back to default', [
                'provided' => $rawFields,
                'default'  => $filtered,
            ]);
        } else {
            Log::debug('[InstructionParser] inputs.fields filtered to valid enum values', [
                'provided' => $rawFields,
                'filtered' => $filtered,
            ]);
        }

        $merged['inputs']['fields'] = $filtered;

// 4) Finally build your DTO; it can apply its own defaults/rules
        $dto = VoucherInstructionsData::from($merged);
        Log::debug('[InstructionParser] Final VoucherInstructionsData:', ['dto' => $dto->toArray()]);

        // 4) Finally build your DTO; it can apply its own defaults/rules
        $dto = VoucherInstructionsData::from($merged);
        Log::debug('[InstructionParser] Final VoucherInstructionsData:', ['dto' => $dto->toArray()]);

        return $dto;
    }
}
