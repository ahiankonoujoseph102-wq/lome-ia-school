<?php

namespace Tests\Unit;

use App\Models\Lesson;
use PHPUnit\Framework\TestCase;

class LessonDurationTest extends TestCase
{
    public function test_it_reads_the_durations_typed_by_the_admin(): void
    {
        $this->assertSame(750, Lesson::parseDuration('12:30'));
        $this->assertSame(3910, Lesson::parseDuration('1:05:10'));
        $this->assertSame(720, Lesson::parseDuration('12'));
        $this->assertSame(720, Lesson::parseDuration('12 min'));
        $this->assertNull(Lesson::parseDuration(''));
        $this->assertNull(Lesson::parseDuration('douze'));
    }

    public function test_it_formats_durations(): void
    {
        $this->assertSame('45 s', Lesson::formatDuration(45));
        $this->assertSame('12 min 30', Lesson::formatDuration(750));
        $this->assertSame('17 min', Lesson::formatDuration(1020));
        $this->assertSame('1 h 05', Lesson::formatDuration(3910));
    }
}
