<?php

namespace Tests\Unit;

use App\Enums\StudentStatus;
use PHPUnit\Framework\TestCase;

class StudentStatusTest extends TestCase
{
    public function test_every_status_has_a_spanish_label(): void
    {
        foreach (StudentStatus::cases() as $status) {
            $this->assertNotSame($status->value, $status->label());
        }
    }
}
