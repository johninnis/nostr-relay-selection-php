<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core, its normalisation corpus-locked to nostr-core's — see ADR-0003

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol;

use Override;

final readonly class RelayUrl
{
    private const int MAX_LENGTH = 200;
    private const string URL_CHARACTERS = '#^[A-Za-z0-9\-._~:/?\[\]@!$&()*+,;=%]+$#D';
    private const string ENCODED_CONTROL = '/%(?:[01][0-9a-f]|20|7f)/i';
    private const string HOSTNAME = '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/D';
    private const string NUMERIC_LABEL = '/^(?:0x[0-9a-f]*|[0-9]+)$/iD';
    private const string DOTTED_QUAD = '/^(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)$/D';
    private const string PRIVATE_IPV4 = '/^(?:10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.)/';
    private const string RAW_AUTHORITY = '#^[a-z]+://([^/?]*)#i';
    private const string DIGITS_ONLY_PORT = '/^[^:]*(?::[0-9]*)?$/D';

    private function __construct(private string $url, private string $host)
    {
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->url;
    }

    public function isOnion(): bool
    {
        return str_ends_with($this->host, '.onion');
    }

    public function isLoopback(): bool
    {
        return 'localhost' === $this->host || ($this->isIpv4() && str_starts_with($this->host, '127.'));
    }

    public function isLocalAddr(): bool
    {
        return $this->isLoopback()
            || str_ends_with($this->host, '.local')
            || ($this->isIpv4() && 1 === preg_match(self::PRIVATE_IPV4, $this->host));
    }

    public function isInsecure(): bool
    {
        return str_starts_with($this->url, 'ws://') && !$this->isOnion();
    }

    private function isIpv4(): bool
    {
        return 1 === preg_match(self::DOTTED_QUAD, $this->host);
    }

    public static function tryFromString(?string $url): ?self
    {
        $trimmed = trim($url ?? '');
        if (1 !== preg_match(self::URL_CHARACTERS, $trimmed) || 1 === preg_match(self::ENCODED_CONTROL, $trimmed)) {
            return null;
        }
        $lower = strtolower($trimmed);
        if ((!str_starts_with($lower, 'ws://') && !str_starts_with($lower, 'wss://')) || !self::hasDigitsOnlyPort($trimmed)) {
            return null;
        }

        $parsed = parse_url($trimmed);
        if (false === $parsed || isset($parsed['user']) || isset($parsed['pass']) || !isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }
        $scheme = strtolower($parsed['scheme']);
        $host = strtolower($parsed['host']);
        $port = $parsed['port'] ?? null;
        if (1 !== preg_match(self::HOSTNAME, $host) || str_contains($host, '..') || !self::isCanonicalNumericHost($host) || 0 === $port) {
            return null;
        }

        $path = rtrim(self::removeDotSegments($parsed['path'] ?? ''), ',.;!/');
        $portSuffix = null === $port || self::isDefaultPort($scheme, $port) ? '' : ':'.$port;
        $query = ($parsed['query'] ?? '') === '' ? '' : '?'.$parsed['query'];
        $normalised = $scheme.'://'.$host.$portSuffix.$path.$query;
        $afterHost = substr($normalised, (int) strpos($normalised, $host) + strlen($host));

        $valid = strlen($normalised) <= self::MAX_LENGTH
            && 1 !== preg_match('#wss?://#', $afterHost)
            && !str_contains($path, '//')
            && ('' === $path || !str_contains($path, $host));

        return $valid ? new self($normalised, $host) : null;
    }

    private static function hasDigitsOnlyPort(string $url): bool
    {
        preg_match(self::RAW_AUTHORITY, $url, $authority);

        return 1 === preg_match(self::DIGITS_ONLY_PORT, $authority[1] ?? '');
    }

    private static function isCanonicalNumericHost(string $host): bool
    {
        $labels = explode('.', rtrim($host, '.'));

        return 1 !== preg_match(self::NUMERIC_LABEL, end($labels)) || 1 === preg_match(self::DOTTED_QUAD, $host);
    }

    private static function removeDotSegments(string $path): string
    {
        $output = [];
        foreach (explode('/', $path) as $segment) {
            $dots = str_ireplace('%2e', '.', $segment);
            if ('..' === $dots) {
                array_pop($output);
            } elseif ('.' !== $dots) {
                $output[] = $segment;
            }
        }

        $resolved = implode('/', $output);

        return str_starts_with($resolved, '/') || '' === $resolved ? $resolved : '/'.$resolved;
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ('wss' === $scheme && 443 === $port) || ('ws' === $scheme && 80 === $port);
    }
}
