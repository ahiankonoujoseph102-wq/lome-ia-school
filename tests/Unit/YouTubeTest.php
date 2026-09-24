<?php

namespace Tests\Unit;

use App\Support\YouTube;
use PHPUnit\Framework\TestCase;

class YouTubeTest extends TestCase
{
    public function test_it_extracts_the_video_id_from_common_links(): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://youtu.be/dQw4w9WgXcQ?si=abc'));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://youtube.com/shorts/dQw4w9WgXcQ'));
    }

    public function test_it_rejects_invalid_values(): void
    {
        $this->assertNull(YouTube::id(''));
        $this->assertNull(YouTube::id(null));
        $this->assertNull(YouTube::id('https://example.com/video'));
    }
}
