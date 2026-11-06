<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Tests\Functional\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\FormRateLimiter\Http\RateLimitResponseFactory;
use Plan2net\FormRateLimiter\Tests\Functional\Fixtures\TooManyRequestsErrorHandler;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
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
                'in 0 seconds',
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
}
