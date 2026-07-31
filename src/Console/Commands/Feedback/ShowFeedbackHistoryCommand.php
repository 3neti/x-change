<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Feedback;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryConsoleContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryConsoleRecordData;

final class ShowFeedbackHistoryCommand extends Command
{
    protected $signature = 'x-change:feedback:history
        {--channel= : Filter by channel}
        {--status= : Filter by delivery status}
        {--intent= : Filter by intent key}
        {--correlation= : Filter by correlation ID}
        {--limit=25 : Maximum records to display}
        {--json : Output JSON}';

    protected $description = 'Show redacted x-feedback delivery history without mutating delivery state.';

    public function handle(FeedbackDeliveryConsoleContract $console): int
    {
        $history = $console->history(array_filter([
            'channel' => $this->option('channel'),
            'status' => $this->option('status'),
            'intent_key' => $this->option('intent'),
            'correlation_id' => $this->option('correlation'),
        ], static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== ''));
        $records = collect($history->records)
            ->take(max(1, (int) $this->option('limit')))
            ->map(fn (FeedbackDeliveryConsoleRecordData $record): array => $this->row($record))
            ->values()
            ->all();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'total' => $history->total,
                'records' => $records,
                'filters' => $history->filters,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($records === []) {
            $this->components->info('No feedback delivery records matched the filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['Delivery ID', 'Channel', 'Recipient', 'Status', 'Attempts', 'Provider ID', 'Correlation'],
            array_map(static fn (array $record): array => [
                $record['delivery_id'],
                $record['channel'],
                $record['recipient'],
                $record['status'],
                $record['attempt_count'],
                $record['provider_message_id'] ?? '—',
                $record['correlation_id'] ?? '—',
            ], $records),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(FeedbackDeliveryConsoleRecordData $record): array
    {
        $route = $record->recipient->email ?: $record->recipient->phone;

        return [
            'delivery_id' => $record->delivery_id,
            'intent_key' => $record->intent_key,
            'channel' => $record->channel,
            'recipient' => $this->maskRoute($route),
            'status' => $record->status,
            'attempt_count' => $record->attempt_count,
            'provider_message_id' => $record->provider_message_id,
            'provider_status' => $record->provider_status,
            'correlation_id' => $record->correlation_id,
            'causation_id' => $record->causation_id,
            'last_attempted_at' => $record->last_attempted_at,
        ];
    }

    private function maskRoute(?string $route): string
    {
        if (! is_string($route) || trim($route) === '') {
            return '[unavailable]';
        }

        if (str_contains($route, '@')) {
            [$local, $domain] = array_pad(explode('@', $route, 2), 2, '');

            return Str::substr($local, 0, 1)
                .str_repeat('*', max(1, Str::length($local) - 1))
                .'@'.$domain;
        }

        return Str::mask($route, '*', 0, max(0, Str::length($route) - 4));
    }
}
