<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

final readonly class RuntimeOperationsChecklist
{
    /**
     * @return array{
     *     queues: list<string>,
     *     local: array<string, string>,
     *     cloud: list<string>,
     *     forge: list<string>,
     *     broadcasting_required: bool
     * }
     */
    public function describe(): array
    {
        $queues = ['x-change-funding', 'x-change-feedback', 'default'];

        return [
            'queues' => $queues,
            'local' => [
                'queue' => 'php artisan queue:work database --queue='.implode(',', $queues).' --sleep=3 --timeout=60',
                'scheduler' => 'php artisan schedule:work',
                'reverb' => 'php artisan reverb:start',
            ],
            'cloud' => [
                'Run the three named queues with Managed Queues or a Worker cluster.',
                'Enable the Scheduler; Laravel Cloud invokes schedule:run every minute.',
                'Attach managed WebSockets only when Reverb broadcasting is enabled.',
            ],
            'forge' => [
                'Create a Queue Worker for the three named queues with a 60-second timeout.',
                'Enable Laravel Scheduler; Forge invokes schedule:run every minute.',
                'Enable Laravel Reverb only when broadcasting is enabled.',
            ],
            'broadcasting_required' => (bool) config(
                'x-change.funding.broadcast_enabled',
                false,
            ) && config('broadcasting.default') === 'reverb',
        ];
    }
}
