<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Security;

use Plan2net\FormRateLimiter\Configuration\RateLimiterSettings;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class IpAccessPolicy
{
    public function __construct(
        private RateLimiterSettings $settings,
    ) {
    }

    public function isBlocked(string $clientAddress): bool
    {
        return $this->matches($clientAddress, $this->settings->blacklistIps);
    }

    public function isExempt(string $clientAddress): bool
    {
        return $this->matches($clientAddress, $this->settings->whitelistIps);
    }

    private function matches(string $clientAddress, string $addressList): bool
    {
        return '' !== trim($addressList) && GeneralUtility::cmpIP($clientAddress, $addressList);
    }
}
