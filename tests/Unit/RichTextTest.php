<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_line_breaks_and_dashes_become_lines_and_lists(): void
    {
        $text = "Pour débuter avec l'IA.\r\n- Affiches\n-Vidéos\n• Chaîne YouTube\n\nFormat : enregistré.\nÀ votre rythme.";

        $this->assertSame([
            ['type' => 'paragraph', 'lines' => ['Pour débuter avec l\'IA.']],
            ['type' => 'list', 'items' => ['Affiches', 'Vidéos', 'Chaîne YouTube']],
            ['type' => 'paragraph', 'lines' => ['Format : enregistré.', 'À votre rythme.']],
        ], RichText::blocks($text));
    }

    public function test_an_old_single_block_text_stays_one_paragraph(): void
    {
        $this->assertSame([['type' => 'paragraph', 'lines' => ['Un seul bloc - avec un tiret au milieu.']]], RichText::blocks('Un seul bloc - avec un tiret au milieu.'));
        $this->assertSame([], RichText::blocks(null));
        $this->assertSame([], RichText::blocks("  \n \n"));
    }

    public function test_a_lone_dash_is_not_an_empty_bullet(): void
    {
        $this->assertSame([['type' => 'paragraph', 'lines' => ['-']]], RichText::blocks('-'));
    }
}
