<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\RateLimiter\Storage\CachingFrameworkStorage;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Rate limiter service for TYPO3 forms
 */
final class RateLimiterService implements SingletonInterface
{
    public function __construct(
        private readonly ConfigurationService $configurationService
    ) {}

    public function createRateLimiter(string $formIdentifier, ServerRequestInterface $request): LimiterInterface
    {
        $remoteIp = $this->getRemoteAddress($request);

        $config = $this->configurationService->getGlobalConfiguration();

        $limiterId = $this->getLimiterId($formIdentifier, $config);

        $rateLimiterConfig = [
            'id' => $limiterId,
            'policy' => $config['enabled'] ? 'sliding_window' : 'no_limit',
            'limit' => $config['limit'],
            'interval' => $config['interval'],
        ];

        $storage = $config['enabled']
            ? GeneralUtility::makeInstance(CachingFrameworkStorage::class)
            : new InMemoryStorage();

        $limiterFactory = new RateLimiterFactory($rateLimiterConfig, $storage);

        return $limiterFactory->create($remoteIp);
    }

    public function getRemoteAddress(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams') ?? NormalizedParams::createFromRequest($request);
        return $normalizedParams->getRemoteAddress();
    }

    public function isIpWhitelisted(string $ip): bool
    {
        $globalConfig = $this->configurationService->getGlobalConfiguration();
        $whitelistIps = $globalConfig['whitelistIps'] ?? '';

        if (empty($whitelistIps)) {
            return false;
        }

        return GeneralUtility::cmpIP($ip, $whitelistIps);
    }

    public function isIpBlacklisted(string $ip): bool
    {
        $globalConfig = $this->configurationService->getGlobalConfiguration();
        $blacklistIps = $globalConfig['blacklistIps'] ?? '';

        if (empty($blacklistIps)) {
            return false;
        }

        return GeneralUtility::cmpIP($ip, $blacklistIps);
    }

    /**
     * @param array{limitingMode?: string, enabled?: bool, limit?: int, interval?: string} $config
     */
    private function getLimiterId(string $formIdentifier, array $config): string
    {
        $limitingMode = $config['limitingMode'] ?? 'per_form';
        if ($limitingMode === 'global') {
            return sha1('typo3-form-rate-limiter-global');
        }
        return sha1('typo3-form-rate-limiter-' . $formIdentifier);

    }

    public function isLoggingEnabled(): bool
    {
        $globalConfig = $this->configurationService->getGlobalConfiguration();
        return (bool)($globalConfig['enableLogging'] ?? false);
    }
}
