<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Legal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LBHurtado\XChange\Data\Legal\AgreementDocumentData;
use LBHurtado\XChange\Models\AgreementAcceptance;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class CurrentAgreementService
{
    public function enabled(): bool
    {
        return (bool) config('x-change.legal.eula.enabled', false);
    }

    public function document(): AgreementDocumentData
    {
        $path = (string) config('x-change.legal.eula.path', base_path('EULA.md'));
        $contents = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException("The required agreement document is unavailable [{$path}].");
        }

        [$metadata, $markdown] = $this->parseDocument($contents);
        $key = trim((string) ($metadata['agreement_key'] ?? ''));
        $version = trim((string) ($metadata['version'] ?? ''));

        if ($key === '' || $version === '') {
            throw new RuntimeException('The required agreement document must declare agreement_key and version.');
        }

        return new AgreementDocumentData(
            key: $key,
            version: $version,
            title: trim((string) ($metadata['title'] ?? 'End User Agreement')),
            effectiveAt: filled($metadata['effective_at'] ?? null)
                ? (string) $metadata['effective_at']
                : null,
            sha256: hash('sha256', $contents),
            markdown: $markdown,
            html: Str::markdown($markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        );
    }

    public function hasAccepted(Model $subject, ?AgreementDocumentData $document = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $document ??= $this->document();

        return AgreementAcceptance::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->where('agreement_key', $document->key)
            ->where('agreement_version', $document->version)
            ->where('agreement_sha256', $document->sha256)
            ->exists();
    }

    public function accept(Model $subject, Request $request, AgreementDocumentData $document): AgreementAcceptance
    {
        $acceptedAt = now()->utc();
        $reference = (string) Str::ulid();
        $ipAddressHash = $this->evidenceHash($request->ip());
        $userAgentHash = $this->evidenceHash($request->userAgent());
        $onboardingReference = $request->session()->get('x-change.onboarding.reference');
        $onboardingReference = is_string($onboardingReference) && $onboardingReference !== ''
            ? $onboardingReference
            : null;
        $locale = app()->getLocale();
        $evidenceSha256 = hash('sha256', implode('|', [
            $reference,
            $subject->getMorphClass(),
            (string) $subject->getKey(),
            $document->key,
            $document->version,
            $document->sha256,
            $acceptedAt->toIso8601String(),
            $onboardingReference ?? '',
            $locale,
            $ipAddressHash ?? '',
            $userAgentHash ?? '',
        ]));

        return AgreementAcceptance::query()->firstOrCreate([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'agreement_key' => $document->key,
            'agreement_version' => $document->version,
            'agreement_sha256' => $document->sha256,
        ], [
            'reference' => $reference,
            'accepted_at' => $acceptedAt,
            'onboarding_reference' => $onboardingReference,
            'locale' => $locale,
            'ip_address_hash' => $ipAddressHash,
            'user_agent_hash' => $userAgentHash,
            'evidence_sha256' => $evidenceSha256,
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function parseDocument(string $contents): array
    {
        if (! preg_match('/\A---\R(?<frontmatter>.*?)\R---\R(?<body>.*)\z/s', $contents, $matches)) {
            throw new RuntimeException('The required agreement document must begin with YAML front matter.');
        }

        $metadata = Yaml::parse($matches['frontmatter']);

        if (! is_array($metadata)) {
            throw new RuntimeException('The required agreement metadata is invalid.');
        }

        return [$metadata, trim($matches['body'])];
    }

    private function evidenceHash(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $key = (string) config('x-change.legal.eula.evidence_key', config('app.key'));

        return hash_hmac('sha256', $value, $key);
    }
}
