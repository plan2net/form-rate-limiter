<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\FormRateLimiter\Configuration\LimitingMode;
use Plan2net\FormRateLimiter\Configuration\RateLimiterSettings;
use Psr\Log\AbstractLogger;

final class RateLimiterSettingsTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function limits(): array
    {
        return [
            'positive' => ['3', 3],
            'zero' => ['0', 5],
            'negative' => ['-3', 5],
            'decimal' => ['2.5', 5],
            'text' => ['abc', 5],
            'empty' => ['', 5],
            'surrounding spaces' => [' 7 ', 7],
        ];
    }

    #[Test]
    #[DataProvider('limits')]
    public function acceptsOnlyPositiveWholeNumbersAsLimit(string $configured, int $expected): void
    {
        self::assertSame($expected, RateLimiterSettings::fromArray(['limit' => $configured])->limit);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function intervals(): array
    {
        return [
            'minutes' => ['30 minutes', '30 minutes'],
            'hour' => ['1 hour', '1 hour'],
            'misspelled' => ['15 minuten', '15 minutes'],
            'empty' => ['', '15 minutes'],
            'zero' => ['0 seconds', '15 minutes'],
            'negative' => ['-5 minutes', '15 minutes'],
        ];
    }

    #[Test]
    #[DataProvider('intervals')]
    public function acceptsOnlyPositiveParsableIntervals(string $configured, string $expected): void
    {
        self::assertSame($expected, RateLimiterSettings::fromArray(['interval' => $configured])->interval);
    }

    #[Test]
    public function unknownLimitingModeFallsBackToPerForm(): void
    {
        self::assertSame(LimitingMode::PerForm, RateLimiterSettings::fromArray(['limitingMode' => 'per_site'])->limitingMode);
    }

    #[Test]
    public function keepsValidFieldsWhenAnotherFieldIsInvalid(): void
    {
        $settings = RateLimiterSettings::fromArray(['limit' => 'abc', 'interval' => '1 hour', 'limitingMode' => 'global']);

        self::assertSame(5, $settings->limit);
        self::assertSame('1 hour', $settings->interval);
        self::assertSame(LimitingMode::Global, $settings->limitingMode);
    }

    #[Test]
    public function logsOneWarningPerInvalidField(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        RateLimiterSettings::fromArray(['limit' => '0', 'interval' => 'soon', 'limitingMode' => 'per_site', 'enabled' => '1'], $logger);

        self::assertSame(
            [['warning', 'limitingMode', 'per_site'], ['warning', 'limit', '0'], ['warning', 'interval', 'soon']],
            array_map(
                static fn (array $record): array => [$record['level'], $record['context']['field'], $record['context']['value']],
                $logger->records
            )
        );
    }
}
