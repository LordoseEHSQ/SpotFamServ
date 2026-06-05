<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\CancelAudioJob;
use App\Module\AudioExtractor\Domain\AudioJob;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use App\Shared\Application\Exception\NotFoundException;
use App\Tests\Module\AudioExtractor\Support\InMemoryAudioJobRepository;
use PHPUnit\Framework\TestCase;

class CancelAudioJobTest extends TestCase
{
    public function test_pending_job_is_canceled(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $jobs->save($job);

        $result = (new CancelAudioJob($jobs))($job->getId());

        $this->assertSame(AudioJob::STATUS_CANCELED, $result->getStatus());
    }

    public function test_unknown_job_is_not_found(): void
    {
        $useCase = new CancelAudioJob(new InMemoryAudioJobRepository());

        $this->expectException(NotFoundException::class);
        $useCase('00000000-0000-0000-0000-000000000000');
    }

    public function test_running_job_cannot_be_canceled_409(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();
        $jobs->save($job);

        $this->expectException(ExtractorBusyException::class);
        (new CancelAudioJob($jobs))($job->getId());
    }
}
