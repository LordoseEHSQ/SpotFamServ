<?php

declare(strict_types=1);

namespace App\Tests\Module\Provisioning\Application;

use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Application\ProvisioningException;
use App\Module\Provisioning\Application\RegisterArtifact;
use App\Module\Provisioning\Domain\FlashArtifact;
use PHPUnit\Framework\TestCase;

final class RegisterArtifactTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'artifact_test_');
        file_put_contents($this->tmpFile, 'FIRMWARE_BINARY_CONTENT');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testCreatesNewArtifactWhenNoneExists(): void
    {
        $repo = $this->createMock(FlashArtifactRepositoryInterface::class);
        $repo->method('findByBoardChannelVersion')->willReturn(null);
        $repo->expects($this->once())->method('save')->with($this->isInstanceOf(FlashArtifact::class));

        $useCase = new RegisterArtifact($repo);
        $artifact = $useCase('esp32-wroom-32', 'stable', '1.0.0', 'fw.bin', 'ESP32-D0WD-V3', $this->tmpFile);

        $this->assertSame('esp32-wroom-32', $artifact->getBoard());
        $this->assertSame('stable', $artifact->getChannel());
        $this->assertSame('1.0.0', $artifact->getVersion());
        $this->assertSame('fw.bin', $artifact->getFilename());
        $this->assertSame('ESP32-D0WD-V3', $artifact->getExpectedChip());
        $this->assertSame(hash_file('sha256', $this->tmpFile), $artifact->getSha256());
        $this->assertSame(filesize($this->tmpFile), $artifact->getSizeBytes());
    }

    public function testUpdatesExistingArtifact(): void
    {
        $existing = new FlashArtifact('esp32-wroom-32', 'stable', '1.0.0', 'old.bin', 'oldhash', 'ESP32-D0WD-V3', 100);

        $repo = $this->createMock(FlashArtifactRepositoryInterface::class);
        $repo->method('findByBoardChannelVersion')->willReturn($existing);
        $repo->expects($this->once())->method('save')->with($existing);

        $useCase = new RegisterArtifact($repo);
        $artifact = $useCase('esp32-wroom-32', 'stable', '1.0.0', 'fw.bin', 'ESP32-D0WD-V3', $this->tmpFile);

        $this->assertSame($existing, $artifact);
        $this->assertSame('fw.bin', $artifact->getFilename());
        $this->assertSame(hash_file('sha256', $this->tmpFile), $artifact->getSha256());
    }

    public function testThrowsWhenFileUnreadable(): void
    {
        $repo = $this->createMock(FlashArtifactRepositoryInterface::class);
        $repo->method('findByBoardChannelVersion')->willReturn(null);

        $useCase = new RegisterArtifact($repo);

        $this->expectException(ProvisioningException::class);
        $useCase('esp32', 'stable', '1.0.0', 'missing.bin', 'ESP32', '/nonexistent/missing.bin');
    }
}
