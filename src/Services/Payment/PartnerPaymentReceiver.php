<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

class PartnerPaymentReceiver
{
    /** @return array{url:string, secret:string, resolve:string} */
    public function resolve(string $partnerReference): array
    {
        $receiver = config('x-change.partner_api.payment_events.receivers.'.$partnerReference, []);
        $url = (string) ($receiver['url'] ?? '');
        $secret = (string) ($receiver['secret'] ?? '');
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = config('x-change.partner_api.payment_events.allowed_hosts', []);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($host, $allowed, true) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || strlen($secret) < 32 || ! defined('CURLOPT_RESOLVE')) {
            throw new RuntimeException('invalid_receiver_configuration');
        }
        $addresses = $this->addresses($host);
        if ($addresses === []) {
            throw new RuntimeException('receiver_dns_unavailable');
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::checkIp($address, ['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'])) {
                throw new RuntimeException('receiver_address_not_public');
            }
        }
        $address = $addresses[0];

        return ['url' => $url, 'secret' => $secret, 'resolve' => $host.':443:'.$address];
    }

    /** @return list<string> */
    protected function addresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A);

        return array_values(array_filter(array_map(static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));
    }
}
