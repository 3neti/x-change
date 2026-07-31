<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Actions\Funding\RefreshFundingLiquidity;
use Throwable;

final class RefreshTreasuryLiquidityCommand extends Command
{
    protected $signature = 'xchange:treasury:refresh-liquidity {--json : Emit a machine-readable result}';

    protected $description = 'Refresh cached provider liquidity without posting money';

    public function handle(
        SystemUserResolverContract $systemUsers,
        RefreshFundingLiquidity $refreshLiquidity,
    ): int {
        try {
            $operator = $systemUsers->resolve();

            if (! $operator instanceof Authenticatable) {
                $this->components->error('The system principal cannot authorize a liquidity refresh.');

                return self::FAILURE;
            }

            $result = $refreshLiquidity->handle($operator);
        } catch (Throwable) {
            $this->components->error('Provider liquidity could not be refreshed.');

            return self::FAILURE;
        }

        $payload = [
            'schema' => 'x-change.treasury-liquidity-refresh.v1',
            'success' => $result->succeeded() && ! $result->hasIncompleteConnections(),
            'refreshed' => $result->refreshed,
            'failed' => $result->failed,
            'busy' => $result->busy,
            'unavailable' => $result->unavailable,
            'review_required' => $result->reviewRequired,
            'financial_posting' => false,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->components->info(sprintf(
                'Provider liquidity refresh: %d refreshed, %d failed, %d busy, %d unavailable.',
                $result->refreshed,
                $result->failed,
                $result->busy,
                $result->unavailable,
            ));
        }

        return $payload['success'] ? self::SUCCESS : self::FAILURE;
    }
}
