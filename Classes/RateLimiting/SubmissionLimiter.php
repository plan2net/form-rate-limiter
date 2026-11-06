<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\RateLimiting;

use Plan2net\FormRateLimiter\Configuration\LimitingMode;
use Plan2net\FormRateLimiter\Configuration\RateLimiterSettings;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use TYPO3\CMS\Core\RateLimiter\Storage\CachingFrameworkStorage;

final readonly class SubmissionLimiter
{
    public function __construct(
        private RateLimiterSettings $settings,
        private CachingFrameworkStorage $storage,
    ) {
    }

    public function consume(string $formIdentifier, string $clientAddress): RateLimit
    {
        $factory = new RateLimiterFactory([
            'id' => $this->bucketFor($formIdentifier),
            'policy' => 'sliding_window',
            'limit' => $this->settings->limit,
            'interval' => $this->settings->interval,
        ], $this->storage);

        return $factory->create($clientAddress)->consume();
    }

    private function bucketFor(string $formIdentifier): string
    {
        return match ($this->settings->limitingMode) {
            LimitingMode::Global => sha1('typo3-form-rate-limiter-global'),
            LimitingMode::PerForm => sha1('typo3-form-rate-limiter-' . $formIdentifier),
        };
    }
}
