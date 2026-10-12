<?php

namespace App\Tests\Migrations;

use DoctrineMigrations\Version20261012010828;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LegacyRuleConversionTest extends TestCase
{
    public static function rules(): iterable
    {
        // The rules found in production
        yield ['First saturday of next month', 'FREQ=MONTHLY;BYDAY=1SA'];
        yield ['+2 months, first monday of this month', 'FREQ=MONTHLY;INTERVAL=2;BYDAY=1MO'];
        // Simple intervals
        yield ['1 week', 'FREQ=WEEKLY'];
        yield ['+1 day', 'FREQ=DAILY'];
        yield ['2 weeks', 'FREQ=WEEKLY;INTERVAL=2'];
        yield ['next week', 'FREQ=WEEKLY'];
        yield ['P1D', 'FREQ=DAILY'];
        yield ['last friday of next month', 'FREQ=MONTHLY;BYDAY=-1FR'];
        yield ['1 day;2 weeks', null];
        yield ['whenever', null];
    }

    #[DataProvider('rules')]
    public function testConvertRule(string $legacy, ?string $expected): void
    {
        require_once __DIR__ . '/../../migrations/Version20261012010828.php';

        self::assertSame($expected, Version20261012010828::convertRule($legacy));
    }
}
