<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Onboarding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Onboarding\OnboardIssuer;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Console\Concerns\InteractsWithPayloadFiles;

class OnboardIssuerCommand extends Command
{
    use InteractsWithJsonOutput;
    use InteractsWithPayloadFiles;

    protected $signature = 'xchange:issuer:onboard
        {name? : Issuer or business display name}
        {--mobile= : Primary mobile number}
        {--email= : Email address}
        {--external-id= : External issuer identifier}
        {--first-name= : Representative first name}
        {--last-name= : Representative last name}
        {--country=PH : Country code}
        {--type=business : Issuer type}
        {--json : Output JSON}
        {--pretty : Pretty-print JSON or output}
        {--config= : Path to JSON payload file}';

    protected $description = 'Onboard an issuer into the x-change lifecycle.';

    public function handle(OnboardIssuer $action): int
    {
        $payload = $this->mergePayloads(
            $this->loadPayloadFromConfigOption(),
            $this->payloadFromOptions(),
        );

        $result = $action->handle($payload);

        $this->renderPayload($result->toArray(), 'Issuer onboarded successfully.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    protected function payloadFromOptions(): array
    {
        $payload = [];

        $name = $this->argument('name');
        if (is_string($name) && trim($name) !== '') {
            $payload['name'] = trim($name);
        }

        foreach ([
            'mobile' => 'mobile',
            'email' => 'email',
            'external-id' => 'external_id',
            'first-name' => 'first_name',
            'last-name' => 'last_name',
            'country' => 'country',
            'type' => 'type',
        ] as $option => $field) {
            $value = $this->option($option);

            if (is_string($value) && trim($value) !== '') {
                $payload[$field] = trim($value);
            }
        }

        return $payload;
    }
}
