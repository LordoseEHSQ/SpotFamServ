<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\DismissAudioJob;
use App\Module\AudioExtractor\Domain\AudioJob;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use App\Shared\Application\Exception\NotFoundException;
use App\Tests\Module\AudioExtractor\Support\InMemoryAudioJobRepository;
use PHPUnit\Framework\TestCase;

class DismissAudioJobTest extends TestCase
{
    public function test_failed_job_is_deleted(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();
        $job->markFailed('extraction error');
        $jobs->save($job);
        $id = $job->getId();

        (new DismissAudioJob($jobs))($id);

        $this->assertNull($jobs->findById($id));
    }

    public function test_canceled_job_is_deleted(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->cancel();
        $jobs->save($job);
        $id = $job->getId();

        (new DismissAudioJob($jobs))($id);

        $this->assertNull($jobs->findById($id));
    }

    public function test_unknown_job_throws_not_found(): void
    {
        $useCase = new DismissAudioJob(new InMemoryAudioJobRepository());

        $this->expectException(NotFoundException::class);
        $useCase('00000000-0000-0000-0000-000000000000');
    }

    public function test_running_job_cannot_be_dismissed(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();
        $jobs->save($job);

        $this->expectException(ExtractorBusyException::class);
        (new DismissAudioJob($jobs))($job->getId());
    }

    public function test_done_job_cannot_be_dismissed(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();
        $job->markDone('result.mp3');
        $jobs->save($job);

        $this->expectException(ExtractorBusyException::class);
        (new DismissAudioJob($jobs))($job->getId());
    }

    public function test_pending_job_cannot_be_dismissed(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $jobs->save($job);

        $this->expectException(ExtractorBusyException::class);
        (new DismissAudioJob($jobs))($job->getId());
    }
}
