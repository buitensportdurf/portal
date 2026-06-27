<?php

namespace App\Tests\Service;

use App\Service\LastRelease;
use PHPUnit\Framework\TestCase;

class LastReleaseTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'release_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testReturnsDashesWhenFileMissing(): void
    {
        $release = new LastRelease('/does/not/exist.json');

        self::assertSame('-', $release->getBranch());
        self::assertSame('-', $release->getDate());
        self::assertSame('-', $release->getCommitHashShort());
        self::assertSame('-', $release->getCommitHashLong());
        self::assertSame('-', $release->getCommitDate());
    }

    public function testReadsAllFieldsFromJson(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'branch' => 'main',
            'date' => '2026-01-01 12:00:00',
            'commitHashShort' => 'abc1234',
            'commitHashLong' => 'abc1234deadbeef',
            'commitDate' => '2026-01-01 11:00:00 +0100',
        ]));

        $release = new LastRelease($this->tmpFile);

        self::assertSame('main', $release->getBranch());
        self::assertSame('2026-01-01 12:00:00', $release->getDate());
        self::assertSame('abc1234', $release->getCommitHashShort());
        self::assertSame('abc1234deadbeef', $release->getCommitHashLong());
        self::assertSame('2026-01-01 11:00:00 +0100', $release->getCommitDate());
    }

    public function testFallsBackPerMissingKey(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'branch' => 'develop',
        ]));

        $release = new LastRelease($this->tmpFile);

        self::assertSame('develop', $release->getBranch());
        self::assertSame('-', $release->getCommitHashShort());
    }
}
