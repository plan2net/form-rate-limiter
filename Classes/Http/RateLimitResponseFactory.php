<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Controller\ErrorPageController;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerInterface;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerNotConfiguredException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class RateLimitResponseFactory
{
    public function tooManyRequests(ServerRequestInterface $request, string $formIdentifier, ?int $retryAfter = null): ResponseInterface
    {
        $message = null === $retryAfter
            ? 'Too many submissions of this form. Please try again later.'
            : sprintf('Too many submissions of this form. Please try again in %d seconds.', $retryAfter);

        $response = $this->create($request, 429, 'Too many submissions', $message, $formIdentifier, $retryAfter);
        if (null !== $retryAfter) {
            $response = $response->withHeader('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    public function forbidden(ServerRequestInterface $request, string $formIdentifier): ResponseInterface
    {
        return $this->create(
            $request,
            403,
            'Submission blocked',
            'Submissions from your network are not allowed.',
            $formIdentifier,
            null
        );
    }

    private function create(
        ServerRequestInterface $request,
        int $statusCode,
        string $title,
        string $message,
        string $formIdentifier,
        ?int $retryAfter,
    ): ResponseInterface {
        if ($this->isAjaxRequest($request)) {
            return new JsonResponse([
                'error' => $message,
                'formIdentifier' => $formIdentifier,
                'retryAfter' => $retryAfter,
            ], $statusCode);
        }

        $errorHandler = $this->getErrorHandlerFromSite($request, $statusCode);
        if ($errorHandler instanceof PageErrorHandlerInterface) {
            return $errorHandler->handlePageError($request, $message);
        }

        $response = new Response();
        $response->getBody()->write(
            GeneralUtility::makeInstance(ErrorPageController::class)->errorAction($title, $message, 0, $statusCode)
        );

        return $response->withStatus($statusCode);
    }

    private function isAjaxRequest(ServerRequestInterface $request): bool
    {
        return str_contains($request->getHeaderLine('Content-Type'), 'application/json')
            || str_contains($request->getHeaderLine('Accept'), 'application/json')
            || 'XMLHttpRequest' === $request->getHeaderLine('X-Requested-With');
    }

    private function getErrorHandlerFromSite(ServerRequestInterface $request, int $statusCode): ?PageErrorHandlerInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return null;
        }
        try {
            return $site->getErrorHandler($statusCode);
        } catch (PageErrorHandlerNotConfiguredException) {
            return null;
        }
    }
}
