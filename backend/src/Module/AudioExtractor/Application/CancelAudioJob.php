<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\Port\AudioJobRepositoryInterface;
use App\Module\AudioExtractor\Domain\AudioJob;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Best-effort cancel (D-032): only a job that has not started yet (pending) can be safely
 * canceled. A running yt-dlp subprocess cannot be reliably interrupted from here, so a
 * running/finished job is reported as a conflict (409) rather than faked as canceled.
 */
final readonly class CancelAudioJob
{
    public function __construct(
        private AudioJobRepositoryInterface $jobs,
    ) {
    }

    public function __invoke(string $jobId): AudioJob
    {
        $job = $this->jobs->findById($jobId);
        if ($job === null) {
            throw new NotFoundException('Job not found.');
        }

        if (!$job->isPending()) {
            throw new ExtractorBusyException(sprintf(
                'Job is %s and can no longer be canceled.',
                $job->getStatus(),
            ));
        }

        $job->cancel();
        $this->jobs->save($job);

        return $job;
    }
}
