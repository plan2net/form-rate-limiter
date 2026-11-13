<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FormSubmissionRateLimitTest extends FunctionalTestCase
{
    private const CONFIRMATION = 'Thank you for your submission';

    protected array $coreExtensionsToLoad = ['form', 'fluid_styled_content'];

    protected array $testExtensionsToLoad = [
        'plan2net/form-rate-limiter',
        'typo3conf/ext/form_rate_limiter/Tests/Functional/Fixtures/Extensions/test_form',
    ];

    #[Test]
    public function navigatingBetweenPagesDoesNotConsumeTheBudget(): void
    {
        $firstPage = $this->requestPage();
        $secondPage = $this->submit($firstPage, ['name' => 'Jane'], 1);
        self::assertStringContainsString('[comment]', $secondPage);

        $response = $this->submitForResponse($secondPage, ['comment' => 'Hello'], 2);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(self::CONFIRMATION, (string) $response->getBody());
    }

    #[Test]
    public function exhaustedBudgetStillAllowsNavigatingTheForm(): void
    {
        $this->completeForm();

        $secondPage = $this->submit($this->requestPage(), ['name' => 'Jane'], 1);
        $firstPageAgain = $this->submit($secondPage, ['comment' => 'Hello'], 0);
        $secondPageAgain = $this->submit($firstPageAgain, ['name' => 'Jane'], 1);
        $correctedSecondPage = $this->submit($secondPageAgain, ['comment' => ''], 2);

        self::assertStringContainsString('[name]', $firstPageAgain);
        self::assertStringContainsString('[comment]', $correctedSecondPage);
        self::assertSame(429, $this->submitForResponse($correctedSecondPage, ['comment' => 'Hello'], 2)->getStatusCode());
    }

    #[Test]
    public function validationErrorOnTheLastPageDoesNotConsumeTheBudget(): void
    {
        $secondPage = $this->submit($this->requestPage(), ['name' => 'Jane'], 1);
        $secondPageWithError = $this->submit($secondPage, ['comment' => ''], 2);
        self::assertStringNotContainsString(self::CONFIRMATION, $secondPageWithError);

        $response = $this->submitForResponse($secondPageWithError, ['comment' => 'Hello'], 2);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(self::CONFIRMATION, (string) $response->getBody());
    }

    #[Test]
    public function submittedFormKeysCannotSwitchTheBudget(): void
    {
        $this->completeForm();
        $secondPage = $this->submit($this->requestPage(), ['name' => 'Jane'], 1);

        $response = $this->submitForResponse($secondPage, ['comment' => 'Hello'], 2, ['tx_form_formframework[spoofed][x]' => '1']);

        self::assertSame(429, $response->getStatusCode());
    }

    #[Test]
    public function finalSubmitBeyondTheLimitIsRejected(): void
    {
        $this->completeForm();

        $response = $this->completeForm();

        self::assertSame(429, $response->getStatusCode());
        self::assertStringNotContainsString(self::CONFIRMATION, (string) $response->getBody());
    }

    #[Test]
    public function rejectionTellsHowLongToWait(): void
    {
        $this->completeForm();

        $response = $this->completeForm();

        $retryAfter = $response->getHeaderLine('Retry-After');
        self::assertMatchesRegularExpression('/^\\d+$/', $retryAfter);
        self::assertGreaterThan(0, (int) $retryAfter);
        // 15 minutes, plus one second because Symfony rounds the retry time up
        self::assertLessThanOrEqual(901, (int) $retryAfter);
        self::assertStringContainsString('in 15 minutes', (string) $response->getBody());
    }

    #[Test]
    public function variantReplacingTheFinishersIsStillLimited(): void
    {
        $firstResponse = $this->completeForm('/variant');
        self::assertStringContainsString('Sent through the variant', (string) $firstResponse->getBody());

        $response = $this->completeForm('/variant');

        self::assertSame(429, $response->getStatusCode());
    }

    #[Test]
    public function rejectionIsTranslatedIntoTheSiteLanguage(): void
    {
        $this->writeSiteConfiguration('de_AT.UTF-8');
        $this->completeForm();

        $response = $this->completeForm();

        self::assertSame(429, $response->getStatusCode());
        self::assertStringContainsString('Zu viele Übermittlungen', (string) $response->getBody());
    }

    #[Test]
    public function eachFormHasItsOwnBudgetPerForm(): void
    {
        $this->completeForm('/');

        $response = $this->completeForm('/second');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(self::CONFIRMATION, (string) $response->getBody());
    }

    #[Test]
    public function allFormsShareOneBudgetInGlobalMode(): void
    {
        $this->configureRateLimiter(['limitingMode' => 'global']);
        $this->completeForm('/');

        $response = $this->completeForm('/second');

        self::assertSame(429, $response->getStatusCode());
    }

    #[Test]
    public function disabledRateLimitingNeverRejects(): void
    {
        $this->configureRateLimiter(['enabled' => '0']);
        $this->completeForm();

        $response = $this->completeForm();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(self::CONFIRMATION, (string) $response->getBody());
    }

    #[Test]
    public function whitelistedAddressIsNotLimited(): void
    {
        $this->configureRateLimiter(['whitelistIps' => '127.0.0.1']);
        $this->completeForm();

        $response = $this->completeForm();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(self::CONFIRMATION, (string) $response->getBody());
    }

    #[Test]
    public function blacklistedAddressIsForbidden(): void
    {
        $firstPage = $this->requestPage();
        $this->configureRateLimiter(['blacklistIps' => '127.0.0.1']);

        $response = $this->submitForResponse($firstPage, ['name' => 'Jane'], 1);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function blacklistWinsOverWhitelist(): void
    {
        $firstPage = $this->requestPage();
        $this->configureRateLimiter(['whitelistIps' => '127.0.0.1', 'blacklistIps' => '127.0.0.1']);

        $response = $this->submitForResponse($firstPage, ['name' => 'Jane'], 1);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function formDataInTheQueryStringDoesNotBypassTheBlacklist(): void
    {
        $firstPage = $this->requestPage();
        $this->configureRateLimiter(['blacklistIps' => '127.0.0.1']);
        [$action, $parameters] = $this->buildSubmission($firstPage, ['name' => 'Jane'], 1);

        $request = (new InternalRequest('http://localhost' . $action . '&' . $parameters))->withMethod('POST');
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function rejectionIsLoggedWhenLoggingIsEnabled(): void
    {
        $this->configureRateLimiter(['enableLogging' => '1']);
        $this->completeForm();

        self::assertSame(429, $this->completeForm()->getStatusCode());

        $log = implode('', array_map(file_get_contents(...), glob($this->instancePath . '/typo3temp/var/log/typo3_*.log') ?: []));
        self::assertStringContainsString('rate limit exceeded', $log);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->setUpFrontendRootPage(
            1,
            ['EXT:form_rate_limiter/Tests/Functional/Fixtures/page.typoscript'],
            ['include_static_file' => 'EXT:fluid_styled_content/Configuration/TypoScript/,EXT:form/Configuration/TypoScript/']
        );
        if ((new Typo3Version())->getMajorVersion() < 14) {
            $this->addTypoScriptToTemplateRecord(1, 'plugin.tx_form.settings.yamlConfigurations.100 = EXT:test_form/Configuration/Yaml/FormSetup.yaml');
        }
        $this->writeSiteConfiguration();
        $this->configureRateLimiter([]);
        $this->get(CacheManager::class)->getCache('ratelimiter')->flush();
    }

    /**
     * @param array<string, string> $overrides
     */
    private function configureRateLimiter(array $overrides): void
    {
        $this->get(ExtensionConfiguration::class)->set('form_rate_limiter', array_replace([
            'enabled' => '1',
            'limitingMode' => 'per_form',
            'limit' => '1',
            'interval' => '15 minutes',
            'whitelistIps' => '',
            'blacklistIps' => '',
            'enableLogging' => '0',
        ], $overrides));
    }

    private function completeForm(string $path = '/'): ResponseInterface
    {
        $secondPage = $this->submit($this->requestPage($path), ['name' => 'Jane'], 1);

        return $this->submitForResponse($secondPage, ['comment' => 'Hello'], 2);
    }

    private function writeSiteConfiguration(string $locale = 'en_US.UTF-8'): void
    {
        $path = $this->instancePath . '/typo3conf/sites/main';
        GeneralUtility::mkdir_deep($path);
        file_put_contents($path . '/config.yaml', <<<YAML
            rootPageId: 1
            base: 'http://localhost/'
            languages:
              -
                languageId: 0
                title: Default
                locale: {$locale}
                base: /
            YAML);
    }

    private function requestPage(string $path = '/'): string
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(string $html, array $fields, int $targetPageIndex): string
    {
        $response = $this->submitForResponse($html, $fields, $targetPageIndex);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return (string) $response->getBody();
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $leadingParameters
     */
    private function submitForResponse(string $html, array $fields, int $targetPageIndex, array $leadingParameters = []): ResponseInterface
    {
        [$action, $parameters] = $this->buildSubmission($html, $fields, $targetPageIndex, $leadingParameters);

        $request = (new InternalRequest('http://localhost' . $action))
            ->withMethod('POST')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write($parameters);

        return $this->executeFrontendSubRequest($request);
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $leadingParameters
     *
     * @return array{string, string} the form action and the encoded parameters
     */
    private function buildSubmission(string $html, array $fields, int $targetPageIndex, array $leadingParameters = []): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);

        $forms = $xpath->query('//form');
        $form = false === $forms ? null : $forms->item(0);
        self::assertInstanceOf(\DOMElement::class, $form, 'No form found in: ' . $html);

        $query = [];
        foreach ($leadingParameters as $name => $value) {
            $query[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        foreach ($xpath->query('.//input[@type="hidden"][@name]', $form) ?: [] as $input) {
            if ($input instanceof \DOMElement) {
                $query[] = rawurlencode($input->getAttribute('name')) . '=' . rawurlencode($input->getAttribute('value'));
            }
        }
        $formIdentifier = $form->getAttribute('id');
        foreach ($fields as $name => $value) {
            $query[] = rawurlencode('tx_form_formframework[' . $formIdentifier . '][' . $name . ']') . '=' . rawurlencode($value);
        }
        $query[] = rawurlencode('tx_form_formframework[' . $formIdentifier . '][__currentPage]') . '=' . $targetPageIndex;

        return [$form->getAttribute('action'), implode('&', $query)];
    }
}
