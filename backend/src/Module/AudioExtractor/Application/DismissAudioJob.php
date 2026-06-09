<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\Port\AudioJobRepositoryInterface;
use App\Module\AudioExtractor\Domain\AudioJob;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Hard-deletes a terminal-state (failed or canceled) job. A running or done job
 * cannot be dismissed this way; a pending job must be canceled first.
 */
final readonly class DismissAudioJob
{
    public function __construct(
        private AudioJobRepositoryInterface $jobs,
    ) {
    }

    public function __invoke(string $jobId): void
    {
        $job = $this->jobs->findById($jobId);
        if ($job === null) {
            throw new NotFoundException('Job not found.');
        }

        if (!in_array($job->getStatus(), [AudioJob::STATUS_FAILED, AudioJob::STATUS_CANCELED], true)) {
            throw new ExtractorBusyException(sprintf(
                'Job is %s and cannot be dismissed (only failed or canceled jobs can be dismissed).',
                $job->getStatus(),
            ));
        }

        $this->jobs->delete($job);
    }
}
