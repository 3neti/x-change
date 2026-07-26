<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

final class ClaimWalkthroughPdfRenderer
{
    private const PageWidth = 842;

    private const PageHeight = 595;

    /**
     * @param  array<string, mixed>  $storyboard
     */
    public function render(array $storyboard): string
    {
        $pages = [
            $this->coverPage($storyboard),
            ...array_map(
                fn (array $checkpoint): string => $this->checkpointPage($storyboard, $checkpoint),
                $storyboard['checkpoints'] ?? []
            ),
        ];

        return $this->document($pages);
    }

    /**
     * @param  array<string, mixed>  $storyboard
     */
    private function coverPage(array $storyboard): string
    {
        $scenario = $storyboard['scenario'] ?? [];
        $fixture = $scenario['fixture'] ?? [];
        $lines = [
            'Scenario: '.($scenario['key'] ?? 'unknown'),
            'Run: '.($storyboard['run_id'] ?? 'unknown'),
            'Generated: '.($storyboard['generated_at'] ?? 'unknown'),
            'Amount: '.($fixture['amount'] ?? 'n/a'),
            'Money movement: '.($fixture['money_movement'] ?? false ? 'enabled' : 'disabled'),
            'Form-flow default splash: '.($fixture['form_flow_default_splash'] ?? false ? 'enabled' : 'disabled'),
            'Rider splash: '.($fixture['rider_splash'] ?? false ? 'enabled' : 'disabled'),
            'Rider redirect: '.($fixture['rider_redirect'] ?? false ? 'enabled' : 'disabled'),
            'Feedback: '.($fixture['feedback'] ?? false ? 'enabled' : 'disabled'),
            '',
            (string) ($scenario['description'] ?? ''),
        ];

        return $this->page(
            title: (string) ($scenario['label'] ?? 'Claim walkthrough'),
            kicker: 'x-change claim QA storyboard',
            lines: $lines,
            footer: 'Open the HTML storyboard for interactive review. This PDF is the portable QA briefing artifact.',
        );
    }

    /**
     * @param  array<string, mixed>  $storyboard
     * @param  array<string, mixed>  $checkpoint
     */
    private function checkpointPage(array $storyboard, array $checkpoint): string
    {
        $lines = [
            'Actor: '.($checkpoint['actor'] ?? 'unknown'),
            'Route: '.($checkpoint['route'] ?? 'unknown'),
            'Status: '.($checkpoint['status'] ?? 'unknown'),
            '',
            'Expected:',
            (string) ($checkpoint['expected'] ?? ''),
            '',
            'QA prompt:',
            (string) ($checkpoint['qa_prompt'] ?? ''),
            '',
            'Screenshot target:',
            (string) ($checkpoint['screenshot_path'] ?? 'pending'),
        ];

        return $this->page(
            title: (string) ($checkpoint['title'] ?? 'Checkpoint'),
            kicker: (string) (($storyboard['scenario']['label'] ?? 'Claim walkthrough').' checkpoint'),
            lines: $lines,
            footer: 'Frame status: '.($checkpoint['status'] ?? 'pending_capture'),
        );
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function page(string $title, string $kicker, array $lines, string $footer): string
    {
        $commands = [
            '0.98 0.99 1.00 rg',
            '0 0 '.self::PageWidth.' '.self::PageHeight.' re',
            'f',
            '0.70 0.10 0.10 rg',
            '0 532 '.self::PageWidth.' 63 re',
            'f',
            '1 1 1 rg',
            'BT',
            '/F2 11 Tf',
            '48 566 Td',
            '('.$this->escape($kicker).') Tj',
            '/F2 24 Tf',
            '0 -28 Td',
            '('.$this->escape($title).') Tj',
            'ET',
            '0.12 0.12 0.12 rg',
            'BT',
            '/F1 11 Tf',
            '48 492 Td',
        ];

        foreach ($this->wrapLines($lines, 106) as $line) {
            $commands[] = '('.$this->escape($line).') Tj';
            $commands[] = '0 -16 Td';
        }

        $commands = [
            ...$commands,
            'ET',
            '0.72 0.76 0.82 RG',
            '0.8 w',
            '48 54 m',
            '794 54 l',
            'S',
            '0.40 0.45 0.52 rg',
            'BT',
            '/F1 8 Tf',
            '48 36 Td',
            '('.$this->escape($footer).') Tj',
            'ET',
        ];

        return implode("\n", $commands);
    }

    /**
     * @param  array<int, string>  $contents
     */
    private function document(array $contents): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $kids = [];
        $nextObject = 5;

        foreach ($contents as $content) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $kids[] = "{$pageObject} 0 R";
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PageWidth.' '.self::PageHeight.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= str_pad((string) $offsets[$number], 10, '0', STR_PAD_LEFT).' 00000 n '.PHP_EOL;
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function escape(string $value): string
    {
        $value = str_replace(
            ['₱', '“', '”', '‘', '’', '—', '–'],
            ['PHP ', '"', '"', "'", "'", '-', '-'],
            $value,
        );

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\(', '\)', ' ', ' '],
            $value,
        );
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function wrapLines(array $lines, int $width): array
    {
        $wrapped = [];

        foreach ($lines as $line) {
            $chunks = explode("\n", wordwrap($line, $width, "\n", true));
            array_push($wrapped, ...($chunks === [] ? [''] : $chunks));
        }

        return array_slice($wrapped, 0, 24);
    }
}
