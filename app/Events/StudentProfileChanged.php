<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentProfileChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @param array<int, string> $changedFields */
    public function __construct(
        public readonly int $studentId,
        public readonly string $operation,
        public readonly array $changedFields,
        public readonly ?int $actorId,
    ) {}
}
