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
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class RateLimitResponseFactory
{
    private const LABELS = 'LLL:EXT:form_rate_limiter/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    public function tooManyRequests(ServerRequestInterface $request, string $formIdentifier, ?int $retryAfter = null): ResponseInterface
    {
        $languageService = $this->getLanguageService($request);
        $message = null === $retryAfter
            ? $languageService->sL(self::LABELS . 'tooManyRequests.message')
            : $this->retryAfterMessage($languageService, $retryAfter);

        $response = $this->create(
            $request,
            429,
            $languageService->sL(self::LABELS . 'tooManyRequests.title'),
            $message,
            $formIdentifier,
            $retryAfter
        );
        if (null !== $retryAfter) {
            $response = $response->withHeader('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    public function forbidden(ServerRequestInterface $request, string $formIdentifier): ResponseInterface
    {
        $languageService = $this->getLanguageService($request);

        return $this->create(
            $request,
            403,
            $languageService->sL(self::LABELS . 'forbidden.title'),
            $languageService->sL(self::LABELS . 'forbidden.message'),
            $formIdentifier,
            null
        );
    }

    private function retryAfterMessage(LanguageService $languageService, int $seconds): string
    {
        $minutes = max(1, (int) round($seconds / 60));
        [$unit, $count] = $minutes < 60 ? ['minute', $minutes] : ['hour', (int) round($minutes / 60)];
        $label = 1 === $count ? $unit : $unit . 's';

        return sprintf($languageService->sL(self::LABELS . 'tooManyRequests.retryAfter.' . $label), $count);
    }

    private function getLanguageService(ServerRequestInterface $request): LanguageService
    {
        $siteLanguage = $request->getAttribute('language');

        return $siteLanguage instanceof SiteLanguage
            ? $this->languageServiceFactory->createFromSiteLanguage($siteLanguage)
            : $this->languageServiceFactory->create('default');
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
