<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Middleware;

use Plan2net\FormRateLimiter\Service\RateLimiterService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Controller\ErrorPageController;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerInterface;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerNotConfiguredException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Middleware to handle form rate limiting
 */
final class FormRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterService $rateLimiterService,
        private readonly LoggerInterface $logger
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $formIdentifier = $this->getFormIdentifierFromRequest($request);
        if ($formIdentifier === null) {
            return $handler->handle($request);
        }

        // Get remote IP
        $remoteIp = $this->rateLimiterService->getRemoteAddress($request);

        // Check IP whitelist/blacklist
        $ipCheckResult = $this->checkIpAccess($remoteIp, $formIdentifier, $request, $handler);
        if ($ipCheckResult !== null) {
            return $ipCheckResult;
        }

        // Apply rate limiting
        $rateLimiter = $this->rateLimiterService->createRateLimiter($formIdentifier, $request);
        $limiterResult = $rateLimiter->consume();

        if (!$limiterResult->isAccepted()) {
            $retryAfter = $limiterResult->getRetryAfter();
            $retryAfterSeconds = $retryAfter?->getTimestamp() ?? 60;
            $message = sprintf(
                'Rate limit exceeded for form "%s". Try again in %d seconds.',
                $formIdentifier,
                $retryAfterSeconds
            );

            if ($this->rateLimiterService->isLoggingEnabled()) {
                $this->logger->warning('Rate limit exceeded', [
                    'form' => $formIdentifier,
                    'ip' => $remoteIp,
                    'retryAfter' => $retryAfterSeconds,
                    'userAgent' => $request->getHeaderLine('User-Agent'),
                ]);
            }

            return $this->createRateLimitResponse($request, $formIdentifier, $message, $retryAfterSeconds);
        }

        return $handler->handle($request);
    }

    private function checkIpAccess(string $remoteIp, string $formIdentifier, ServerRequestInterface $request, RequestHandlerInterface $handler): ?ResponseInterface
    {
        // Check if IP is whitelisted (skip rate limiting)
        if ($this->rateLimiterService->isIpWhitelisted($remoteIp)) {
            if ($this->rateLimiterService->isLoggingEnabled()) {
                $this->logger->info('Form submission allowed - IP whitelisted', [
                    'form' => $formIdentifier,
                    'ip' => $remoteIp,
                    'userAgent' => $request->getHeaderLine('User-Agent'),
                ]);
            }
            return $handler->handle($request);
        }

        // Check if IP is blacklisted (block immediately)
        if ($this->rateLimiterService->isIpBlacklisted($remoteIp)) {
            if ($this->rateLimiterService->isLoggingEnabled()) {
                $this->logger->error('Form submission blocked - IP blacklisted', [
                    'form' => $formIdentifier,
                    'ip' => $remoteIp,
                    'userAgent' => $request->getHeaderLine('User-Agent'),
                ]);
            }
            return $this->createRateLimitResponse($request, $formIdentifier, 'IP address is blocked');
        }

        return null;
    }

    private function getFormIdentifierFromRequest(ServerRequestInterface $request): ?string
    {
        // Only check POST requests
        if ($request->getMethod() !== 'POST') {
            return null;
        }

        $params = $request->getParsedBody() ?? [];

        // Check if this is a TYPO3 form submission
        if (!isset($params['tx_form_formframework']) || !is_array($params['tx_form_formframework'])) {
            return null;
        }

        $formData = $params['tx_form_formframework'];

        // The form identifier is the first key in the form data
        $formIdentifier = array_key_first($formData);

        if (!$formIdentifier) {
            return null;
        }

        return $formIdentifier;
    }

    protected function createRateLimitResponse(
        ServerRequestInterface $request,
        string $formIdentifier,
        string $message,
        ?int $retryAfter = null
    ): ResponseInterface {
        // Check if this is an AJAX request
        $isAjax = $this->isAjaxRequest($request);

        if ($isAjax) {
            $response = new JsonResponse([
                'error' => $message,
                'formIdentifier' => $formIdentifier,
                'retryAfter' => $retryAfter,
            ], 429);
        } else {
            // For regular form submissions, return HTML response
            // Use TYPO3's error handler if available
            $errorHandler = $this->getErrorHandlerFromSite($request, 429);
            if ($errorHandler instanceof PageErrorHandlerInterface) {
                return $errorHandler->handlePageError(
                    $request,
                    'Rate limit exceeded',
                );
            }
            
            // Fallback to TYPO3 error page
            $response = new Response();
            $content = $this->createErrorPageContent($message, $retryAfter);
            $response->getBody()->write($content);
            $response = $response->withStatus(429);
        }

        if ($retryAfter) {
            $response = $response->withHeader('Retry-After', (string)$retryAfter);
        }

        return $response;
    }

    protected function isAjaxRequest(ServerRequestInterface $request): bool
    {
        $contentType = $request->getHeaderLine('Content-Type');
        $accept = $request->getHeaderLine('Accept');
        $xRequestedWith = $request->getHeaderLine('X-Requested-With');

        return str_contains($contentType, 'application/json')
            || str_contains($accept, 'application/json')
            || $xRequestedWith === 'XMLHttpRequest';
    }

    protected function createErrorPageContent(string $message, ?int $retryAfter = null): string
    {
        $retryMessage = $retryAfter ? " Please try again in {$retryAfter} seconds." : '';
        $fullMessage = $message . $retryMessage;

        // Use TYPO3's ErrorPageController for professional error pages
        $errorController = GeneralUtility::makeInstance(ErrorPageController::class);
        return $errorController->errorAction(
            'Rate Limit Exceeded',
            $fullMessage,
            1752839959,
            429
        );
    }

    protected function getErrorHandlerFromSite(ServerRequestInterface $request, int $statusCode = 429): ?PageErrorHandlerInterface
    {
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            try {
                return $site->getErrorHandler($statusCode);
            } catch (PageErrorHandlerNotConfiguredException $e) {
                // No error handler found. continue to fallback
            }
        }
        return null;
    }
}
