<?php

namespace Tests\Unit;

use App\Support\GetOnBoardFunctions;
use App\Support\HtmlText;
use PHPUnit\Framework\TestCase;

class HtmlTextTest extends TestCase
{
    public function test_paragraphs_become_lines_and_inline_tags_are_removed(): void
    {
        $html = '<p><strong>Diseño y Ejecución:</strong> Crear casos de prueba.</p>'
              . '<p><strong>Reporte:</strong> Registrar fallos en <strong>Jira</strong>.</p>';

        $this->assertSame(
            "Diseño y Ejecución: Crear casos de prueba.\nReporte: Registrar fallos en Jira.",
            HtmlText::toPlain($html)
        );
    }

    public function test_list_items_get_dash_prefix_and_br_becomes_newline(): void
    {
        $html = "<p>Funciones:</p>\n<ul>\n  <li>Diseñar&nbsp;APIs</li>\n  <li>Revisar <em>PRs</em></li>\n</ul>Uno<br>Dos<br/>Tres";

        $this->assertSame(
            "Funciones:\n- Diseñar APIs\n- Revisar PRs\nUno\nDos\nTres",
            HtmlText::toPlain($html)
        );
    }

    public function test_entities_zero_width_and_repeated_whitespace(): void
    {
        $html = "<p>  Q&amp;A \u{200B}de   &quot;apps&quot;&#39;  </p><p></p><p>\u{FEFF}</p><p>Fin</p>";

        $this->assertSame("Q&A de \"apps\"'\nFin", HtmlText::toPlain($html));
    }

    public function test_empty_input_or_empty_after_cleaning_returns_null(): void
    {
        $this->assertNull(HtmlText::toPlain(null));
        $this->assertNull(HtmlText::toPlain(''));
        $this->assertNull(HtmlText::toPlain('   '));
        $this->assertNull(HtmlText::toPlain("<p> </p><p>&nbsp;</p><ul><li></li></ul>\u{200B}"));
    }

    public function test_getonboard_mapping(): void
    {
        $this->assertSame(
            ['functions' => "- Uno\n- Dos", 'functions_headline' => 'Funciones del cargo'],
            GetOnBoardFunctions::fromAttributes([
                'functions'          => '<ul><li>Uno</li><li>Dos</li></ul>',
                'functions_headline' => '  Funciones   del cargo ',
            ])
        );

        $this->assertSame(
            ['functions' => null, 'functions_headline' => null],
            GetOnBoardFunctions::fromAttributes(['functions' => '', 'functions_headline' => ''])
        );

        $this->assertSame(
            ['functions' => null, 'functions_headline' => null],
            GetOnBoardFunctions::fromAttributes([])
        );
    }
}
