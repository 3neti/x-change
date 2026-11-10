<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Services\Commercial\CommissionDocumentPricing;
use Throwable;

final class CommissionDocumentPricingCommand extends Command
{
    protected $signature = 'x-change:commercial:commission-document-pricing
        {operator : Authenticated operator model key}
        {--authorization-reference= : Reference on the operator authorization}
        {--json : Output JSON}';

    protected $description = 'Commission the document fee using a designated single operator.';

    public function handle(CommissionDocumentPricing $commission): int
    {
        $model = (string) config('auth.providers.users.model');
        if (! is_subclass_of($model, Model::class)) {
            $this->error('An operator model is required.');

            return self::FAILURE;
        }

        $operator = $model::query()->find($this->argument('operator'));
        if (! $operator instanceof Model) {
            $this->error('The operator was not found.');

            return self::FAILURE;
        }

        try {
            $activation = $commission->execute($operator, (string) $this->option('authorization-reference'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode([
            'schema' => 'x-change.document-pricing-commissioning.v1',
            'activation_id' => $activation->getKey(),
            'activation_reference' => $activation->activation_reference,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
