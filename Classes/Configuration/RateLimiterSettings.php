<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Configuration;

final readonly class RateLimiterSettings
{
    public function __construct(
        public bool $enabled = true,
        public LimitingMode $limitingMode = LimitingMode::PerForm,
        public int $limit = 5,
        public string $interval = '15 minutes',
        public string $whitelistIps = '',
        public string $blacklistIps = '',
        public bool $loggingEnabled = false,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function fromArray(array $configuration): self
    {
        $defaults = new self();

        return new self(
            enabled: (bool) ($configuration['enabled'] ?? $defaults->enabled),
            limitingMode: LimitingMode::tryFrom((string) ($configuration['limitingMode'] ?? '')) ?? $defaults->limitingMode,
            limit: (int) ($configuration['limit'] ?? $defaults->limit),
            interval: (string) ($configuration['interval'] ?? $defaults->interval),
            whitelistIps: (string) ($configuration['whitelistIps'] ?? $defaults->whitelistIps),
            blacklistIps: (string) ($configuration['blacklistIps'] ?? $defaults->blacklistIps),
            loggingEnabled: (bool) ($configuration['enableLogging'] ?? $defaults->loggingEnabled),
        );
    }
}
