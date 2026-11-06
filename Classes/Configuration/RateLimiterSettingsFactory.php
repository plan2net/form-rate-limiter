<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final readonly class RateLimiterSettingsFactory
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function create(): RateLimiterSettings
    {
        try {
            $configuration = $this->extensionConfiguration->get('form_rate_limiter');
        } catch (\Exception) {
            return new RateLimiterSettings();
        }

        return RateLimiterSettings::fromArray(is_array($configuration) ? $configuration : []);
    }
}
