<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\CreateAudioJob;
use App\Module\AudioExtractor\Application\MediaRequestValidator;
use App\Module\AudioExtractor\Application\Message\ExtractAudioMessage;
use App\Module\AudioExtractor\Domain\AudioJob;
use App\Module\AudioExtractor\Domain\InvalidMediaRequestException;
use App\Tests\Module\AudioExtractor\Support\InMemoryAudioJobRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateAudioJobTest extends TestCase
{
    public function test_valid_request_persists_pending_job_and_dispatches_message(): void
    {
        $jobs = new InMemoryAudioJobRepository();

        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            });

        $useCase = new CreateAudioJob(new MediaRequestValidator(), $jobs, $bus);
        $job = $useCase('https://example.com/v', 'mp3', 256);

        $this->assertSame(AudioJob::STATUS_PENDING, $job->getStatus());
        $this->assertNotNull($jobs->findById($job->getId()));
        $this->assertInstanceOf(ExtractAudioMessage::class, $dispatched);
        $this->assertSame($job->getId(), $dispatched->jobId);
    }

    public function test_invalid_request_fails_fast_without_job_or_dispatch(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $useCase = new CreateAudioJob(new MediaRequestValidator(), $jobs, $bus);

        $this->expectException(InvalidMediaRequestException::class);
        try {
            $useCase('not-a-url', 'mp3', null);
        } finally {
            $this->assertSame([], $jobs->recent());
        }
    }
}
