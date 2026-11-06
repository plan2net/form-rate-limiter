<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Configuration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
    public static function fromArray(array $configuration, LoggerInterface $logger = new NullLogger()): self
    {
        $defaults = new self();
        $valueOf = static function (string $field, callable $isValid, mixed $default) use ($configuration, $logger): mixed {
            if (!array_key_exists($field, $configuration)) {
                return $default;
            }
            $value = trim((string) $configuration[$field]);
            if ($isValid($value)) {
                return $value;
            }
            $logger->warning('Invalid form rate limiter setting, using the default instead', [
                'field' => $field,
                'value' => (string) $configuration[$field],
            ]);

            return $default;
        };

        return new self(
            enabled: (bool) ($configuration['enabled'] ?? $defaults->enabled),
            limitingMode: LimitingMode::from($valueOf(
                'limitingMode',
                static fn (string $value): bool => null !== LimitingMode::tryFrom($value),
                $defaults->limitingMode->value
            )),
            limit: (int) $valueOf('limit', self::isPositiveWholeNumber(...), $defaults->limit),
            interval: (string) $valueOf('interval', self::isPositiveInterval(...), $defaults->interval),
            whitelistIps: (string) ($configuration['whitelistIps'] ?? $defaults->whitelistIps),
            blacklistIps: (string) ($configuration['blacklistIps'] ?? $defaults->blacklistIps),
            loggingEnabled: (bool) ($configuration['enableLogging'] ?? $defaults->loggingEnabled),
        );
    }

    private static function isPositiveWholeNumber(string $value): bool
    {
        return ctype_digit($value) && (int) $value >= 1;
    }

    private static function isPositiveInterval(string $value): bool
    {
        try {
            $interval = @\DateInterval::createFromDateString($value);
        } catch (\DateMalformedIntervalStringException) {
            return false;
        }
        if (!$interval instanceof \DateInterval) {
            return false;
        }
        $reference = new \DateTimeImmutable('@0');

        return $reference->add($interval) > $reference;
    }
}
