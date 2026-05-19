<?php

declare(strict_types=1);

// Duplicate of innis/nostr-core's RelayUrl value object. innis/nostr-relay-selection
// ships zero runtime dependencies (see README "Zero runtime dependencies"), so it must
// not depend on innis/nostr-core; the type is re-declared here instead. Normalisation
// and validation are kept identical to nostr-core's so a URL routed through one and
// stored by the other compares equal as a string and both libraries accept and reject
// the same inputs at the boundary. Malformed hostnames, fragments, %20 in paths,
// concatenated URLs, out-of-range ports, and URLs over 200 chars are all rejected.

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol;

final readonly class RelayUrl
{
    private function __construct(private string $url)
    {
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url;
    }

    public function __toString(): string
    {
        return $this->url;
    }

    public function isOnion(): bool
    {
        return str_ends_with($this->host(), '.onion');
    }

    public function isLoopback(): bool
    {
        $host = $this->host();

        return 'localhost' === $host
            || '::1' === $host
            || str_starts_with($host, '127.');
    }

    public function isLocalAddr(): bool
    {
        if ($this->isLoopback()) {
            return true;
        }

        $host = $this->host();
        if (str_ends_with($host, '.local')) {
            return true;
        }
        if (1 === preg_match('#^10\.#', $host)) {
            return true;
        }
        if (1 === preg_match('#^192\.168\.#', $host)) {
            return true;
        }
        if (1 === preg_match('#^172\.(1[6-9]|2[0-9]|3[0-1])\.#', $host)) {
            return true;
        }

        return false;
    }

    public function isInsecure(): bool
    {
        return str_starts_with($this->url, 'ws://') && !$this->isOnion();
    }

    private function host(): string
    {
        $parsed = parse_url($this->url);

        return strtolower((string) ($parsed['host'] ?? ''));
    }

    public static function fromString(?string $url): ?self
    {
        if (null === $url) {
            return null;
        }

        $normalised = self::normalise($url);
        if (null === $normalised) {
            return null;
        }

        if (!self::isValid($normalised)) {
            return null;
        }

        return new self($normalised);
    }

    private static function normalise(string $url): ?string
    {
        $trimmed = trim($url);
        if ('' === $trimmed) {
            return null;
        }

        $lower = strtolower($trimmed);
        if (!str_starts_with($lower, 'ws://') && !str_starts_with($lower, 'wss://')) {
            return null;
        }

        $parsed = parse_url($trimmed);
        if (false === $parsed || !isset($parsed['scheme'], $parsed['host']) || '' === $parsed['host']) {
            return null;
        }

        if (isset($parsed['fragment'])) {
            return null;
        }

        $scheme = strtolower((string) $parsed['scheme']);
        $assembled = $scheme.'://'.strtolower((string) $parsed['host']);

        if (isset($parsed['port']) && !self::isDefaultPort($scheme, (int) $parsed['port'])) {
            $assembled .= ':'.$parsed['port'];
        }

        if (isset($parsed['path']) && '/' !== $parsed['path']) {
            $cleanPath = rtrim((string) $parsed['path'], ',.;!');
            $cleanPath = rtrim($cleanPath, '/');
            if ('' !== $cleanPath && '/' !== $cleanPath) {
                $assembled .= $cleanPath;
            }
        }

        if (isset($parsed['query'])) {
            $assembled .= '?'.$parsed['query'];
        }

        return $assembled;
    }

    private static function isValid(string $url): bool
    {
        if (strlen($url) > 200) {
            return false;
        }

        $parsed = parse_url($url);
        if (false === $parsed
            || !isset($parsed['scheme'], $parsed['host'])
            || !in_array($parsed['scheme'], ['ws', 'wss'], true)) {
            return false;
        }

        $host = (string) $parsed['host'];

        if (1 !== preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/', $host)) {
            return false;
        }

        if (isset($parsed['port']) && ($parsed['port'] < 1 || $parsed['port'] > 65535)) {
            return false;
        }

        $afterHost = substr($url, (int) strpos($url, $host) + strlen($host));
        if (1 === preg_match('#wss?://#', $afterHost)) {
            return false;
        }

        if (isset($parsed['path'])) {
            $path = (string) $parsed['path'];
            if (str_contains($path, '%20')) {
                return false;
            }
            if (str_contains($path, '//')) {
                return false;
            }
            if (str_contains($path, $host)) {
                return false;
            }
        }

        return true;
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ('wss' === $scheme && 443 === $port) || ('ws' === $scheme && 80 === $port);
    }
}
