<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Infrastructure\Http\Dto;

use Symfony\Component\HttpFoundation\Request;

/**
 * Shared request DTO for both POST (create) and PUT (update) profile endpoints.
 */
final class FamilyProfileRequest
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = $request->toArray();
        return new self(
            (string) ($data['name'] ?? ''),
            isset($data['description']) ? (string) $data['description'] : null,
        );
    }
}
