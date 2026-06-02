<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Application;

use App\Module\Scan\Application\GenerateReaderApiKey;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\RevokeReaderApiKey;
use App\Module\Scan\Domain\ReaderDevice;
use App\Shared\Application\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class GenerateReaderApiKeyTest extends TestCase
{
    public function test_generates_hash_and_returns_plain_key_that_validates(): void
    {
        $reader = new ReaderDevice('reader-1');
        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->willReturn($reader);
        $repo->expects($this->once())->method('save')->with($reader);

        $useCase = new GenerateReaderApiKey($repo);
        $plainKey = $useCase->__invoke('reader-1');

        $this->assertNotSame('', $plainKey);
        $this->assertTrue($reader->hasApiKey());
        $this->assertNotSame($plainKey, $reader->getApiKeyHash(), 'plaintext must never be stored');
        $this->assertTrue($reader->validateApiKey($plainKey));
        $this->assertFalse($reader->validateApiKey($plainKey . 'x'));
    }

    public function test_rotating_invalidates_old_key(): void
    {
        $reader = new ReaderDevice('reader-1');
        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->willReturn($reader);

        $useCase = new GenerateReaderApiKey($repo);
        $first = $useCase->__invoke('reader-1');
        $second = $useCase->__invoke('reader-1');

        $this->assertNotSame($first, $second);
        $this->assertFalse($reader->validateApiKey($first));
        $this->assertTrue($reader->validateApiKey($second));
    }

    public function test_throws_when_reader_missing(): void
    {
        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->willReturn(null);

        $useCase = new GenerateReaderApiKey($repo);
        $this->expectException(NotFoundException::class);
        $useCase->__invoke('missing');
    }

    public function test_revoke_clears_key_and_enables_fallback(): void
    {
        $reader = new ReaderDevice('reader-1');
        $reader->setApiKey('some-key');
        $this->assertTrue($reader->hasApiKey());

        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->willReturn($reader);
        $repo->expects($this->once())->method('save')->with($reader);

        $useCase = new RevokeReaderApiKey($repo);
        $result = $useCase->__invoke('reader-1');

        $this->assertFalse($result->hasApiKey());
        $this->assertFalse($result->validateApiKey('some-key'));
    }
}
