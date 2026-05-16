<?php

namespace Utils;

class Markdown
{
    public static function convert(string $text): string
    {
        static $parser;

        if (empty($parser)) {
            $parser = new HyperDown();

            $parser->hook('afterParseCode', function ($html) {
                return preg_replace("/<code class=\"([_a-z0-9-]+)\">/i", "<code class=\"lang-\\1\">", $html);
            });

            $parser->enableHtml(true);
        }

        return str_replace('<p><!--more--></p>', '<!--more-->', $parser->makeHtml($text));
    }
}
