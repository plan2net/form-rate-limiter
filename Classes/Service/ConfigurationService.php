<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Service to handle global extension configuration
 */
final class ConfigurationService implements SingletonInterface
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {}

    /**
     * @return array{enabled: bool, limitingMode: string, limit: int, interval: string, whitelistIps: string, blacklistIps: string, enableLogging: bool}
     */
    public function getGlobalConfiguration(): array
    {
        try {
            $config = $this->extensionConfiguration->get('form_rate_limiter');
            return [
                'enabled' => (bool)($config['enabled'] ?? true),
                'limitingMode' => $config['limitingMode'] ?? 'per_form',
                'limit' => (int)($config['limit'] ?? 5),
                'interval' => $config['interval'] ?? '15 minutes',
                'whitelistIps' => $config['whitelistIps'] ?? '',
                'blacklistIps' => $config['blacklistIps'] ?? '',
                'enableLogging' => (bool)($config['enableLogging'] ?? false),
            ];
        } catch (\Exception $e) {
            // Return default configuration if extension configuration is not available
            return [
                'enabled' => true,
                'limitingMode' => 'per_form',
                'limit' => 5,
                'interval' => '15 minutes',
                'whitelistIps' => '',
                'blacklistIps' => '',
                'enableLogging' => false,
            ];
        }
    }
}
