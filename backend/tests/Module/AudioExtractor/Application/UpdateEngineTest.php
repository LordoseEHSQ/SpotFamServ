<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\ExtractAudio;
use App\Module\AudioExtractor\Application\Port\MediaEngineInterface;
use App\Module\AudioExtractor\Application\UpdateEngine;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class UpdateEngineTest extends TestCase
{
    public function test_update_runs_when_idle(): void
    {
        $engine = $this->createMock(MediaEngineInterface::class);
        $engine->expects($this->once())->method('update')->willReturn('2026.06.05');

        $useCase = new UpdateEngine($engine, new LockFactory(new InMemoryStore()));
        $this->assertSame('2026.06.05', $useCase());
    }

    public function test_update_is_rejected_while_extraction_holds_the_lock(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $held = $locks->createLock(ExtractAudio::ENGINE_LOCK_KEY);
        $this->assertTrue($held->acquire());

        $engine = $this->createMock(MediaEngineInterface::class);
        $engine->expects($this->never())->method('update');

        $useCase = new UpdateEngine($engine, $locks);

        $this->expectException(ExtractorBusyException::class);
        $useCase();
    }
}
