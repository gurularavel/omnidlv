<?php

namespace App\Services\Webhooks;

/**
 * Keeps webhook URLs pointed at the public internet. Without it a company could aim a webhook
 * at 127.0.0.1, the LAN or a cloud metadata address and read the response in the delivery log.
 * The check runs when the URL is saved and again before every send (DNS can change in between),
 * and the sender pins the connection to the address checked here.
 */
class WebhookUrlGuard
{
    /** Ranges filter_var's NO_PRIV/NO_RES flags do not cover. */
    private const EXTRA_BLOCKED_V4 = [
        ['100.64.0.0', 10],   // carrier-grade NAT
        ['192.0.0.0', 24],    // IETF protocol assignments
        ['198.18.0.0', 15],   // benchmarking
    ];

    /**
     * @return array{host: string, port: int, ips: list<string>}
     *
     * @throws UnsafeWebhookUrl
     */
    public function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, $this->allowHttp() ? ['https', 'http'] : ['https'], true)) {
            throw new UnsafeWebhookUrl(__('webhooks.errors.https_only'));
        }

        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeWebhookUrl(__('webhooks.errors.invalid_url'));
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if ($this->allowPrivate()) {
            return ['host' => $host, 'port' => $port, 'ips' => []];
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw new UnsafeWebhookUrl(__('webhooks.errors.private_address'));
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            throw new UnsafeWebhookUrl(__('webhooks.errors.unresolvable'));
        }

        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw new UnsafeWebhookUrl(__('webhooks.errors.private_address'));
            }
        }

        return ['host' => $host, 'port' => $port, 'ips' => $ips];
    }

    public function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            foreach (self::EXTRA_BLOCKED_V4 as [$net, $bits]) {
                $mask = -1 << (32 - $bits);
                if (($long & $mask) === (ip2long($net) & $mask)) {
                    return false;
                }
            }

            return true;
        }

        // IPv4-mapped IPv6 (::ffff:10.0.0.1) must be judged as the IPv4 it wraps.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            return $this->isPublic($m[1]);
        }

        return true;
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    private function allowHttp(): bool
    {
        return (bool) config('services.webhooks.allow_http');
    }

    private function allowPrivate(): bool
    {
        return (bool) config('services.webhooks.allow_private_networks');
    }
}
