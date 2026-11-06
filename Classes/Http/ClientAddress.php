<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Http;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;

final class ClientAddress
{
    public static function fromRequest(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            $normalizedParams = NormalizedParams::createFromRequest($request);
        }

        return $normalizedParams->getRemoteAddress();
    }
}
