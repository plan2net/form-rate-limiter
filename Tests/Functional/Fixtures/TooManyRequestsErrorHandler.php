<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Tests\Functional\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;

final class TooManyRequestsErrorHandler implements PageErrorHandlerInterface
{
    /**
     * @param array<mixed> $configuration
     */
    public function __construct(
        private readonly int $statusCode,
        array $configuration,
    ) {
    }

    /**
     * @param array<mixed> $reasons
     */
    public function handlePageError(ServerRequestInterface $request, string $message, array $reasons = []): ResponseInterface
    {
        return new HtmlResponse('Site error page', $this->statusCode);
    }
}
