<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\Message\ExtractAudioMessage;
use App\Module\AudioExtractor\Application\Port\AudioJobRepositoryInterface;
use App\Module\AudioExtractor\Domain\AudioJob;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Accepts an extraction request: validates synchronously (bad input → 422 before any job
 * exists), persists a pending {@see AudioJob}, then dispatches it to the async worker
 * (D-032). Returns immediately so the HTTP layer can answer 202 + job id – php-fpm is freed
 * at once instead of blocking for minutes.
 */
final readonly class CreateAudioJob
{
    public function __construct(
        private MediaRequestValidator $validator,
        private AudioJobRepositoryInterface $jobs,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(string $url, string $formatValue, ?int $bitrateKbps): AudioJob
    {
        // Validate up front so invalid input fails fast with 422 (never creates a job).
        $request = $this->validator->validate($url, $formatValue, $bitrateKbps);

        $job = new AudioJob($request->url, $request->format->value, $request->bitrateKbps);
        $this->jobs->save($job);

        // The id is self-assigned at construction, so dispatch is safe even before flush.
        $this->bus->dispatch(new ExtractAudioMessage($job->getId()));

        return $job;
    }
}
