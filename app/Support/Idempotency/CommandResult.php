<?php

namespace App\Support\Idempotency;

final readonly class CommandResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $resourceType,
        public string $resourcePublicId,
        public array $data,
        public bool $replay = false,
    ) {}

    public function asReplay(): self
    {
        return new self($this->resourceType, $this->resourcePublicId, $this->data, true);
    }
}
