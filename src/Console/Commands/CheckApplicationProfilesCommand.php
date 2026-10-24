<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Configuration\ApplicationProfileCompiler;
use Throwable;

final class CheckApplicationProfilesCommand extends Command
{
    protected $signature = 'x-change:app-profiles:check {--json : Output the compilation report as JSON}';

    protected $description = 'Validate every packaged app profile and the selected host composition';

    public function handle(ApplicationProfileCompiler $compiler): int
    {
        $keys = array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob(dirname(__DIR__, 3).'/config/app-profiles/*.yaml') ?: [],
        );
        $report = ['profiles' => [], 'selected' => null, 'errors' => []];
        if ($keys === []) {
            $report['errors']['catalog'] = 'No packaged app profiles were found.';
        }

        foreach ($keys as $key) {
            try {
                $report['profiles'][$key] = count($compiler->compile($key)->navigation);
            } catch (Throwable $exception) {
                $report['errors'][$key] = $exception->getMessage();
            }
        }
        try {
            $report['selected'] = $compiler->fromConfig()->toArray();
        } catch (Throwable $exception) {
            $report['errors']['selected'] = $exception->getMessage();
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->line(sprintf('Compiled %d app profiles; %d errors.', count($report['profiles']), count($report['errors'])));
            foreach ($report['errors'] as $key => $error) {
                $this->error("{$key}: {$error}");
            }
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
