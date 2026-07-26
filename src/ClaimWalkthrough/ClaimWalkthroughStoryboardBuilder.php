<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ClaimWalkthroughStoryboardBuilder
{
    public function __construct(
        private readonly ClaimWalkthroughArtifactStore $store,
        private readonly ClaimWalkthroughPdfRenderer $pdfs,
    ) {}

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array{run_id: string, root: string, screenshots: string, storyboard_frames: string}  $run
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function build(array $scenario, array $run, array $actions, array $metadata = []): array
    {
        $generatedAt = CarbonImmutable::now()->toIso8601String();
        $checkpoints = array_map(
            fn (array $checkpoint): array => $this->normalizeCheckpoint($checkpoint, $run['root']),
            $scenario['checkpoints'] ?? []
        );

        $storyboard = [
            'schema_version' => 'x-change.claim-walkthrough.storyboard.v1',
            'generated_at' => $generatedAt,
            'run_id' => $run['run_id'],
            'scenario' => [
                'key' => $scenario['key'] ?? null,
                'label' => $scenario['label'] ?? null,
                'description' => $scenario['description'] ?? null,
                'fixture' => $scenario['fixture'] ?? [],
            ],
            'checkpoints' => $checkpoints,
            'actions' => $actions,
            'metadata' => $metadata,
        ];

        $artifacts = [
            'root' => $run['root'],
            'storyboard_json' => $run['root'].'/claim-walkthrough-storyboard.json',
            'storyboard_html' => $run['root'].'/claim-walkthrough-storyboard.html',
            'storyboard_pdf' => $run['root'].'/walkthrough-storyboard.pdf',
            'report_json' => $run['root'].'/claim-walkthrough-report.json',
            'metadata_json' => $run['root'].'/recording-metadata.json',
            'action_log_jsonl' => $run['root'].'/action-log.jsonl',
        ];
        $artifacts['view_options'] = $this->viewOptions($artifacts);

        $report = [
            'schema_version' => 'x-change.claim-walkthrough.report.v1',
            'generated_at' => $generatedAt,
            'run_id' => $run['run_id'],
            'scenario' => $scenario['key'] ?? null,
            'passed' => true,
            'dry_run' => (bool) ($metadata['dry_run'] ?? false),
            'checkpoint_count' => count($checkpoints),
            'artifacts' => $artifacts,
        ];

        $this->store->writeJson($artifacts['storyboard_json'], $storyboard);
        $this->store->writeHtml($artifacts['storyboard_html'], $this->renderHtml($storyboard));
        $this->store->writePdf($artifacts['storyboard_pdf'], $this->pdfs->render($storyboard));
        $this->store->writeJson($artifacts['report_json'], $report);
        $this->store->writeJson($artifacts['metadata_json'], $metadata);
        $this->store->writeJsonLines($artifacts['action_log_jsonl'], $actions);

        return $report;
    }

    /**
     * @param  array<string, string>  $artifacts
     * @return array<string, array<string, mixed>>
     */
    private function viewOptions(array $artifacts): array
    {
        return [
            'default' => [
                'label' => 'Default PDF',
                'kind' => 'pdf',
                'path' => $artifacts['storyboard_pdf'],
                'url' => 'file://'.$artifacts['storyboard_pdf'],
                'open_command' => 'open '.escapeshellarg($artifacts['storyboard_pdf']),
            ],
            'html' => [
                'label' => 'HTML storyboard',
                'kind' => 'html',
                'path' => $artifacts['storyboard_html'],
                'url' => 'file://'.$artifacts['storyboard_html'],
                'open_command' => 'open '.escapeshellarg($artifacts['storyboard_html']),
            ],
            'folder' => [
                'label' => 'Artifact folder',
                'kind' => 'folder',
                'path' => $artifacts['root'],
                'url' => 'file://'.$artifacts['root'],
                'open_command' => 'open '.escapeshellarg($artifacts['root']),
            ],
            'current_app' => [
                'label' => 'Current app paths',
                'kind' => 'paths',
                'root' => $artifacts['root'],
                'pdf' => $artifacts['storyboard_pdf'],
                'html' => $artifacts['storyboard_html'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array<string, mixed>
     */
    private function normalizeCheckpoint(array $checkpoint, string $root): array
    {
        $relativeScreenshot = (string) ($checkpoint['screenshot'] ?? 'screenshots/'.Str::slug((string) $checkpoint['key']).'.png');

        return [
            ...$checkpoint,
            'status' => 'pending_capture',
            'screenshot_path' => $root.'/'.$relativeScreenshot,
            'qa_prompt' => $this->qaPrompt($checkpoint),
        ];
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     */
    private function qaPrompt(array $checkpoint): string
    {
        return sprintf(
            '[%s] Confirm that %s. Route: %s.',
            $checkpoint['actor'] ?? 'actor',
            $checkpoint['expected'] ?? 'the screen matches the expected claim experience',
            $checkpoint['route'] ?? 'n/a',
        );
    }

    /**
     * @param  array<string, mixed>  $storyboard
     */
    private function renderHtml(array $storyboard): string
    {
        $scenario = $storyboard['scenario'];
        $checkpointCards = collect($storyboard['checkpoints'])
            ->map(function (array $checkpoint): string {
                $title = e((string) $checkpoint['title']);
                $actor = e((string) $checkpoint['actor']);
                $route = e((string) $checkpoint['route']);
                $expected = e((string) $checkpoint['expected']);
                $screenshot = e((string) $checkpoint['screenshot_path']);

                return <<<HTML
                    <section class="checkpoint">
                        <div class="meta">{$actor} · {$route}</div>
                        <h2>{$title}</h2>
                        <p>{$expected}</p>
                        <div class="frame">Frame pending browser capture<br><small>{$screenshot}</small></div>
                    </section>
                HTML;
            })
            ->implode(PHP_EOL);

        $label = e((string) ($scenario['label'] ?? 'Claim walkthrough'));
        $description = e((string) ($scenario['description'] ?? ''));
        $runId = e((string) $storyboard['run_id']);

        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>{$label}</title>
                <style>
                    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
                    main { max-width: 960px; margin: 0 auto; padding: 40px 24px; }
                    header { margin-bottom: 28px; }
                    h1 { margin: 0 0 8px; font-size: 32px; line-height: 1.1; }
                    h2 { margin: 8px 0; font-size: 20px; }
                    p { color: #334155; line-height: 1.6; }
                    .run { color: #64748b; font-size: 13px; }
                    .checkpoint { border: 1px solid #d9e2ec; background: white; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
                    .meta { color: #b91c1c; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
                    .frame { margin-top: 16px; min-height: 180px; border: 1px dashed #94a3b8; border-radius: 6px; display: grid; place-items: center; text-align: center; color: #64748b; background: #f8fafc; padding: 16px; }
                    small { overflow-wrap: anywhere; }
                </style>
            </head>
            <body>
                <main>
                    <header>
                        <div class="run">Run {$runId}</div>
                        <h1>{$label}</h1>
                        <p>{$description}</p>
                    </header>
                    {$checkpointCards}
                </main>
            </body>
            </html>
        HTML;
    }
}
