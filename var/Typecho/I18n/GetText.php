<?php

namespace Typecho\I18n;

/*
   Copyright (c) 2003 Danilo Segan <danilo@kvota.net>.
   Copyright (c) 2005 Nico Kaiser <nico@siriux.net>

   This file is part of PHP-gettext.

   PHP-gettext is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   PHP-gettext is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with PHP-gettext; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

 */

/**
 * This file is part of PHP-gettext
 *
 * @author Danilo Segan <danilo@kvota.net>, Nico Kaiser <nico@siriux.net>
 * @category typecho
 * @package I18n
 */
class GetText
{
    public int $error = 0;

    private int $BYTE_ORDER = 0;

    private $STREAM = null;

    private bool $short_circuit = false;

    private bool $enable_cache = false;

    private ?int $originals = null;

    private ?int $translations = null;

    private ?string $pluralHeader = null;

    private int $total = 0;

    private ?array $table_originals = null;

    private ?array $table_translations = null;

    private ?array $cache_translations = null;
    /**
     * Constructor
     *
     * @param string $file file name
     * @param boolean $enable_cache Enable or disable caching of strings (default on)
     */
    public function __construct(string $file, bool $enable_cache = true)
    {
        // If there isn't a StreamReader, turn on short circuit mode.
        if (!file_exists($file)) {
            $this->short_circuit = true;
            return;
        }

        // Caching can be turned off
        $this->enable_cache = $enable_cache;
        $this->STREAM = @fopen($file, 'rb');

        $unpacked = unpack('c', $this->read(4));
        $magic = array_shift($unpacked);

        if (-34 == $magic) {
            $this->BYTE_ORDER = 0;
        } elseif (-107 == $magic) {
            $this->BYTE_ORDER = 1;
        } else {
            $this->error = 1; // not MO file
            return;
        }

        $this->readInt();

        $this->total = $this->readInt();
        $this->originals = $this->readInt();
        $this->translations = $this->readInt();
    }

    public function translate($string, ?int &$num): string
    {
        if ($this->short_circuit) {
            return $string;
        }
        $this->loadTables();

        if ($this->enable_cache) {
            // Caching enabled, get translated string from cache
            if (array_key_exists($string, $this->cache_translations)) {
                return $this->cache_translations[$string];
            } else {
                return $string;
            }
        } else {
            // Caching not enabled, try to find string
            $num = $this->findString($string);
            if ($num == -1) {
                return $string;
            } else {
                return $this->getTranslationString($num);
            }
        }
    }

    public function ngettext($single, $plural, $number, ?int &$num): string
    {
        $number = intval($number);

        if ($this->short_circuit) {
            if ($number != 1) {
                return $plural;
            } else {
                return $single;
            }
        }

        // find out the appropriate form
        $select = $this->selectString($number);

        // this should contains all strings separated by NULLs
        $key = $single . chr(0) . $plural;


        if ($this->enable_cache) {
            if (!array_key_exists($key, $this->cache_translations)) {
                return ($number != 1) ? $plural : $single;
            } else {
                $result = $this->cache_translations[$key];
                $list = explode(chr(0), $result);
                return $list[$select] ?? '';
            }
        } else {
            $num = $this->findString($key);
            if ($num == -1) {
                return ($number != 1) ? $plural : $single;
            } else {
                $result = $this->getTranslationString($num);
                $list = explode(chr(0), $result);
                return $list[$select] ?? '';
            }
        }
    }

    public function __destruct()
    {
        fclose($this->STREAM);
    }

    private function read($count)
    {
        $count = abs($count);

        if ($count > 0) {
            return fread($this->STREAM, $count);
        }

        return false;
    }

    private function readInt(): int
    {
        $end = unpack($this->BYTE_ORDER == 0 ? 'V' : 'N', $this->read(4));
        return array_shift($end);
    }

    private function loadTables()
    {
        if (
            is_array($this->cache_translations) &&
            is_array($this->table_originals) &&
            is_array($this->table_translations)
        ) {
            return;
        }

        /* get original and translations tables */
        fseek($this->STREAM, $this->originals);
        $this->table_originals = $this->readIntArray($this->total * 2);
        fseek($this->STREAM, $this->translations);
        $this->table_translations = $this->readIntArray($this->total * 2);

        if ($this->enable_cache) {
            $this->cache_translations = ['' => null];
            /* read all strings in the cache */
            for ($i = 0; $i < $this->total; $i++) {
                if ($this->table_originals[$i * 2 + 1] > 0) {
                    fseek($this->STREAM, $this->table_originals[$i * 2 + 2]);
                    $original = fread($this->STREAM, $this->table_originals[$i * 2 + 1]);
                    fseek($this->STREAM, $this->table_translations[$i * 2 + 2]);
                    $translation = fread($this->STREAM, $this->table_translations[$i * 2 + 1]);
                    $this->cache_translations[$original] = $translation;
                }
            }
        }
    }

    /**
     * Reads an array of Integers from the Stream
     *
     * @param int $count How many elements should be read
     * @return array of Integers
     */
    private function readIntArray(int $count): array
    {
        return unpack(($this->BYTE_ORDER == 0 ? 'V' : 'N') . $count, $this->read(4 * $count));
    }

    private function findString(string $string, int $start = -1, int $end = -1): int
    {
        if (($start == -1) or ($end == -1)) {
            // findString is called with only one parameter, set start end end
            $start = 0;
            $end = $this->total;
        }
        if (abs($start - $end) <= 1) {
            // We're done, now we either found the string, or it doesn't exist
            $txt = $this->getOriginalString($start);
            if ($string == $txt) {
                return $start;
            } else {
                return -1;
            }
        } elseif ($start > $end) {
            return $this->findString($string, $end, $start);
        } else {
            $half = (int)(($start + $end) / 2);
            $cmp = strcmp($string, $this->getOriginalString($half));
            if ($cmp == 0) {
                return $half;
            } elseif ($cmp < 0) {
                return $this->findString($string, $start, $half);
            } else {
                return $this->findString($string, $half, $end);
            }
        }
    }

    private function getOriginalString(int $num): string
    {
        $length = $this->table_originals[$num * 2 + 1];
        $offset = $this->table_originals[$num * 2 + 2];
        if (!$length) {
            return '';
        }
        fseek($this->STREAM, $offset);
        $data = fread($this->STREAM, $length);
        return (string)$data;
    }

    private function getTranslationString(int $num): string
    {
        $length = $this->table_translations[$num * 2 + 1];
        $offset = $this->table_translations[$num * 2 + 2];
        if (!$length) {
            return '';
        }
        fseek($this->STREAM, $offset);
        $data = fread($this->STREAM, $length);
        return (string)$data;
    }

    /**
     * Detects which plural form to take
     *
     * @param int $n count
     * @return int array index of the right plural form
     */
    private function selectString(int $n): int
    {
        [$total, $expression] = $this->parsePluralHeader($this->getPluralForms());
        $plural = 0;

        try {
            $plural = $this->evaluatePluralExpression($expression, $n);
        } catch (\Throwable) {
            $plural = ($n == 1) ? 0 : 1;
        }

        if ($plural < 0) {
            $plural = 0;
        }

        if ($plural >= $total) {
            $plural = $total - 1;
        }

        return $plural;
    }

    private function parsePluralHeader(string $header): array
    {
        if (preg_match('/nplurals\s*=\s*(\d+)\s*;\s*plural\s*=\s*(.+?)\s*;?$/i', trim($header), $matches)) {
            $total = (int) $matches[1];
            $expression = trim($matches[2]);
            if ($total < 1) {
                $total = 1;
            }
            if ($expression !== '') {
                return [$total, $expression];
            }
        }

        return [2, 'n != 1'];
    }

    private function evaluatePluralExpression(string $expression, int $n): int
    {
        $tokens = $this->tokenizePluralExpression($expression);
        $index = 0;
        $result = $this->parsePluralTernary($tokens, $index, $n);
        $token = $tokens[$index] ?? ['type' => 'eof'];

        if (($token['type'] ?? 'eof') !== 'eof') {
            throw new \RuntimeException('Invalid plural expression');
        }

        return (int) $result;
    }

    private function tokenizePluralExpression(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $offset = 0;
        $operators = ['||', '&&', '==', '!=', '<=', '>=', '?', ':', '(', ')', '+', '-', '*', '/', '%', '<', '>', '!'];

        while ($offset < $length) {
            $char = $expression[$offset];

            if (ctype_space($char)) {
                $offset++;
                continue;
            }

            if (preg_match('/\G\d+/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'number', 'value' => (int) $match[0]];
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\Gn/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'name', 'value' => $match[0]];
                $offset += 1;
                continue;
            }

            $matched = false;
            foreach ($operators as $operator) {
                $operatorLength = strlen($operator);
                if (substr($expression, $offset, $operatorLength) === $operator) {
                    $tokens[] = ['type' => 'op', 'value' => $operator];
                    $offset += $operatorLength;
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                continue;
            }

            throw new \RuntimeException('Invalid plural expression');
        }

        $tokens[] = ['type' => 'eof', 'value' => null];
        return $tokens;
    }

    private function parsePluralTernary(array $tokens, int &$index, int $n): int
    {
        $condition = $this->parsePluralOr($tokens, $index, $n);

        if (!$this->matchPluralOperator($tokens, $index, '?')) {
            return $condition;
        }

        $whenTrue = $this->parsePluralTernary($tokens, $index, $n);
        $this->expectPluralOperator($tokens, $index, ':');
        $whenFalse = $this->parsePluralTernary($tokens, $index, $n);

        return $condition != 0 ? $whenTrue : $whenFalse;
    }

    private function parsePluralOr(array $tokens, int &$index, int $n): int
    {
        $left = $this->parsePluralAnd($tokens, $index, $n);

        while ($this->matchPluralOperator($tokens, $index, '||')) {
            $right = $this->parsePluralAnd($tokens, $index, $n);
            $left = ($left != 0 || $right != 0) ? 1 : 0;
        }

        return $left;
    }

    private function parsePluralAnd(array $tokens, int &$index, int $n): int
    {
        $left = $this->parsePluralComparison($tokens, $index, $n);

        while ($this->matchPluralOperator($tokens, $index, '&&')) {
            $right = $this->parsePluralComparison($tokens, $index, $n);
            $left = ($left != 0 && $right != 0) ? 1 : 0;
        }

        return $left;
    }

    private function parsePluralComparison(array $tokens, int &$index, int $n): int
    {
        $left = $this->parsePluralAdditive($tokens, $index, $n);

        while (true) {
            $operator = $this->peekPluralOperator($tokens, $index);
            if (!in_array($operator, ['==', '!=', '<', '<=', '>', '>='], true)) {
                break;
            }

            $index++;
            $right = $this->parsePluralAdditive($tokens, $index, $n);

            if ($operator === '==') {
                $left = ($left == $right) ? 1 : 0;
            } elseif ($operator === '!=') {
                $left = ($left != $right) ? 1 : 0;
            } elseif ($operator === '<') {
                $left = ($left < $right) ? 1 : 0;
            } elseif ($operator === '<=') {
                $left = ($left <= $right) ? 1 : 0;
            } elseif ($operator === '>') {
                $left = ($left > $right) ? 1 : 0;
            } else {
                $left = ($left >= $right) ? 1 : 0;
            }
        }

        return $left;
    }

    private function parsePluralAdditive(array $tokens, int &$index, int $n): int
    {
        $left = $this->parsePluralMultiplicative($tokens, $index, $n);

        while (true) {
            $operator = $this->peekPluralOperator($tokens, $index);
            if ($operator !== '+' && $operator !== '-') {
                break;
            }

            $index++;
            $right = $this->parsePluralMultiplicative($tokens, $index, $n);
            $left = $operator === '+' ? $left + $right : $left - $right;
        }

        return $left;
    }

    private function parsePluralMultiplicative(array $tokens, int &$index, int $n): int
    {
        $left = $this->parsePluralUnary($tokens, $index, $n);

        while (true) {
            $operator = $this->peekPluralOperator($tokens, $index);
            if (!in_array($operator, ['*', '/', '%'], true)) {
                break;
            }

            $index++;
            $right = $this->parsePluralUnary($tokens, $index, $n);

            if ($operator === '*') {
                $left *= $right;
            } elseif ($operator === '/') {
                $left = $right === 0 ? 0 : (int) ($left / $right);
            } else {
                $left = $right === 0 ? 0 : $left % $right;
            }
        }

        return $left;
    }

    private function parsePluralUnary(array $tokens, int &$index, int $n): int
    {
        if ($this->matchPluralOperator($tokens, $index, '!')) {
            return $this->parsePluralUnary($tokens, $index, $n) == 0 ? 1 : 0;
        }

        if ($this->matchPluralOperator($tokens, $index, '-')) {
            return -$this->parsePluralUnary($tokens, $index, $n);
        }

        if ($this->matchPluralOperator($tokens, $index, '+')) {
            return $this->parsePluralUnary($tokens, $index, $n);
        }

        return $this->parsePluralPrimary($tokens, $index, $n);
    }

    private function parsePluralPrimary(array $tokens, int &$index, int $n): int
    {
        $token = $tokens[$index] ?? ['type' => 'eof'];
        $type = $token['type'] ?? 'eof';

        if ($type === 'number') {
            $index++;
            return (int) $token['value'];
        }

        if ($type === 'name' && ($token['value'] ?? '') === 'n') {
            $index++;
            return $n;
        }

        if ($this->matchPluralOperator($tokens, $index, '(')) {
            $value = $this->parsePluralTernary($tokens, $index, $n);
            $this->expectPluralOperator($tokens, $index, ')');
            return $value;
        }

        throw new \RuntimeException('Invalid plural expression');
    }

    private function matchPluralOperator(array $tokens, int &$index, string $operator): bool
    {
        $token = $tokens[$index] ?? ['type' => 'eof', 'value' => null];
        if (($token['type'] ?? null) === 'op' && ($token['value'] ?? null) === $operator) {
            $index++;
            return true;
        }

        return false;
    }

    private function expectPluralOperator(array $tokens, int &$index, string $operator): void
    {
        if (!$this->matchPluralOperator($tokens, $index, $operator)) {
            throw new \RuntimeException('Invalid plural expression');
        }
    }

    private function peekPluralOperator(array $tokens, int $index): ?string
    {
        $token = $tokens[$index] ?? ['type' => 'eof', 'value' => null];
        if (($token['type'] ?? null) === 'op') {
            return $token['value'];
        }

        return null;
    }

    private function getPluralForms(): string
    {
        $this->loadTables();

        if (!is_string($this->pluralHeader)) {
            if ($this->enable_cache) {
                $header = $this->cache_translations[""];
            } else {
                $header = $this->getTranslationString(0);
            }
            
            if (!is_null($header) && preg_match("/plural\-forms: ([^\n]*)\n/i", $header, $regs)) {
                $expr = $regs[1];
            } else {
                $expr = "nplurals=2; plural=n == 1 ? 0 : 1;";
            }
            $this->pluralHeader = $expr;
        }
        return $this->pluralHeader;
    }
}
