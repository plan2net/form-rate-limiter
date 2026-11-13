<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Tests\Functional\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\FormRateLimiter\Http\RateLimitResponseFactory;
use Plan2net\FormRateLimiter\Tests\Functional\Fixtures\TooManyRequestsErrorHandler;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RateLimitResponseFactoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = ['plan2net/form-rate-limiter'];

    /**
     * @return array<string, array{\Closure(): ServerRequestInterface, string}>
     */
    public static function responsePaths(): array
    {
        return [
            'JSON' => [
                static fn (): ServerRequestInterface => (new ServerRequest('http://localhost/', 'POST'))
                    ->withHeader('Accept', 'application/json'),
                '"retryAfter":0',
            ],
            'fallback error page' => [
                static fn (): ServerRequestInterface => new ServerRequest('http://localhost/', 'POST'),
                'in one minute',
            ],
            'site error handler' => [
                static fn (): ServerRequestInterface => (new ServerRequest('http://localhost/', 'POST'))
                    ->withAttribute('site', new Site('main', 1, [
                        'base' => 'http://localhost/',
                        'errorHandling' => [
                            [
                                'errorCode' => 429,
                                'errorHandler' => 'PHP',
                                'errorPhpClassFQCN' => TooManyRequestsErrorHandler::class,
                            ],
                        ],
                    ])),
                'Site error page',
            ],
        ];
    }

    /**
     * @param \Closure(): ServerRequestInterface $createRequest
     */
    #[Test]
    #[DataProvider('responsePaths')]
    public function zeroSecondsToWaitIsStillSent(\Closure $createRequest, string $expectedBody): void
    {
        $response = $this->get(RateLimitResponseFactory::class)->tooManyRequests($createRequest(), 'contact-1', 0);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('0', $response->getHeaderLine('Retry-After'));
        self::assertStringContainsString($expectedBody, (string) $response->getBody());
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function waitingTimes(): array
    {
        return [
            'less than a minute' => [20, 'default', 'Please try again in one minute.'],
            'a few minutes' => [130, 'default', 'Please try again in 2 minutes.'],
            'almost an hour' => [3586, 'default', 'Please try again in one hour.'],
            'more than an hour' => [7000, 'default', 'Please try again in 2 hours.'],
            'minutes in German' => [600, 'de', 'Bitte versuchen Sie es in 10 Minuten erneut.'],
            'an hour in German' => [3586, 'de', 'Bitte versuchen Sie es in einer Stunde erneut.'],
        ];
    }

    #[Test]
    #[DataProvider('waitingTimes')]
    public function tellsTheWaitInWholeMinutesOrHours(int $seconds, string $language, string $expectedText): void
    {
        $request = (new ServerRequest('http://localhost/', 'POST'))
            ->withAttribute('language', new SiteLanguage(0, 'de' === $language ? 'de_AT.UTF-8' : 'en_US.UTF-8', new Uri('http://localhost/'), []));

        $response = $this->get(RateLimitResponseFactory::class)->tooManyRequests($request, 'contact-1', $seconds);

        self::assertStringContainsString($expectedText, (string) $response->getBody());
        self::assertSame((string) $seconds, $response->getHeaderLine('Retry-After'));
    }
}
