<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Domain;

use App\Module\AudioExtractor\Domain\AudioJob;
use PHPUnit\Framework\TestCase;

class AudioJobTest extends TestCase
{
    public function test_new_job_is_pending_with_self_assigned_id(): void
    {
        $job = new AudioJob('https://example.com/v', 'mp3', 256);

        $this->assertNotSame('', $job->getId());
        $this->assertSame(AudioJob::STATUS_PENDING, $job->getStatus());
        $this->assertSame(0, $job->getProgress());
        $this->assertTrue($job->isPending());
    }

    public function test_happy_path_pending_running_done(): void
    {
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();
        $this->assertSame(AudioJob::STATUS_RUNNING, $job->getStatus());

        $job->markDone('song.mp3');
        $this->assertSame(AudioJob::STATUS_DONE, $job->getStatus());
        $this->assertSame('song.mp3', $job->getResultFile());
        $this->assertSame(100, $job->getProgress());
    }

    public function test_running_job_can_fail(): void
    {
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();
        $job->markFailed('yt-dlp exited 1');

        $this->assertSame(AudioJob::STATUS_FAILED, $job->getStatus());
        $this->assertSame('yt-dlp exited 1', $job->getError());
    }

    public function test_only_pending_job_can_be_canceled(): void
    {
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->cancel();
        $this->assertSame(AudioJob::STATUS_CANCELED, $job->getStatus());
    }

    public function test_done_job_cannot_transition_back_to_running(): void
    {
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();
        $job->markDone('song.mp3');

        $this->expectException(\DomainException::class);
        $job->markRunning();
    }

    public function test_running_job_cannot_be_canceled(): void
    {
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markRunning();

        $this->expectException(\DomainException::class);
        $job->cancel();
    }

    public function test_long_error_is_truncated(): void
    {
        $job = new AudioJob('https://example.com/v', 'mp3', null);
        $job->markFailed(str_repeat('x', 5000));

        $this->assertNotNull($job->getError());
        $this->assertLessThanOrEqual(2001, mb_strlen($job->getError()));
    }
}
