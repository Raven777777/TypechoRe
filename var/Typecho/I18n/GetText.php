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
    //public:
    public int $error = 0; // public variable that holds error code (0 if no error)

    //private:
    private int $BYTE_ORDER = 0;        // 0: low endian, 1: big endian

    private $STREAM = null;

    private bool $short_circuit = false;

    private bool $enable_cache = false;

    private ?int $originals = null;      // offset of original table

    private ?int $translations = null;    // offset of translation table

    private ?string $pluralHeader = null;    // cache header field for plural forms

    private int $total = 0;          // total string count

    private ?array $table_originals = null;  // table for original strings (offsets)

    private ?array $table_translations = null;  // table for translated strings (offsets)

    private ?array $cache_translations = null;  // original -> translation mapping


    /* Methods */
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

        // FIXME: Do we care about revision? We should.
        $revision = $this->readInt();

        $this->total = $this->readInt();
        $this->originals = $this->readInt();
        $this->translations = $this->readInt();
    }

    /**
     * Translates a string
     *
     * @access public
     * @param string $string to be translated
     * @param integer|null $num found string number
     * @return string translated string (or original, if not found)
     */
    public function translate($string, ?int &$num): string
    {
        if ($this->short_circuit) {
            return $string;
        }
        $this->loadTables();

        if ($this->enable_cache) {
            // Caching enabled, get translated string from cache
            if (isset($this->cache_translations[$string]) && is_string($this->cache_translations[$string])) {
                // 命中缓存时同步回填查找结果, 供 GetTextMulti 判断是否继续回退
                $num = 0;
                return $this->cache_translations[$string];
            } else {
                $num = -1;
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

    /**
     * Plural version of gettext
     *
     * @access public
     * @param string single
     * @param string plural
     * @param string number
     * @param integer|null $num found string number
     * @return string plural form
     */
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

    /**
     * 关闭文件句柄
     *
     * @access public
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->STREAM)) {
            fclose($this->STREAM);
        }
    }

    /**
     * read
     *
     * @param mixed $count
     * @access private
     * @return false|string
     */
    private function read($count)
    {
        $count = abs($count);

        if ($count > 0) {
            return fread($this->STREAM, $count);
        }

        return false;
    }

    /**
     * Reads a 32bit Integer from the Stream
     *
     * @access private
     * @return Integer from the Stream
     */
    private function readInt(): int
    {
        $end = unpack($this->BYTE_ORDER == 0 ? 'V' : 'N', $this->read(4));
        return array_shift($end);
    }

    /**
     * Loads the translation tables from the MO file into the cache
     * If caching is enabled, also loads all strings into a cache
     * to speed up translation lookups
     *
     * @access private
     */
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

    /**
     * Binary search for string
     *
     * @access private
     * @param string $string
     * @param int $start (internally used in recursive function)
     * @param int $end (internally used in recursive function)
     * @return int string number (offset in originals table)
     */
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
            // start > end -> turn around and start over
            return $this->findString($string, $end, $start);
        } else {
            // Divide table in two parts
            $half = (int)(($start + $end) / 2);
            $cmp = strcmp($string, $this->getOriginalString($half));
            if ($cmp == 0) {
                // string is exactly in the middle => return it
                return $half;
            } elseif ($cmp < 0) {
                // The string is in the upper half
                return $this->findString($string, $start, $half);
            } else { // The string is in the lower half
                return $this->findString($string, $half, $end);
            }
        }
    }

    /**
     * Returns a string from the "originals" table
     *
     * @access private
     * @param int $num Offset number of original string
     * @return string Requested string if found, otherwise ''
     */
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

    /**
     * Returns a string from the "translations" table
     *
     * @access private
     * @param int $num Offset number of original string
     * @return string Requested string if found, otherwise ''
     */
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
        static $cache = [];

        $expr = $this->getPluralForms();
        $key = $expr . '|' . $n;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $total = 2;
        if (preg_match('/nplurals\s*=\s*(\d+)/i', $expr, $matches)) {
            $total = max(1, (int)$matches[1]);
        }

        $pluralExpr = 'n != 1 ? 0 : 1';
        if (preg_match('/(?<![\w])plural\s*=\s*([^;]*)/i', $expr, $matches)) {
            $pluralExpr = trim($matches[1]);
        }

        $plural = $this->evalPluralExpression($pluralExpr, $n);

        if ($plural >= $total) {
            $plural = $total - 1;
        }

        if ($plural < 0) {
            $plural = 0;
        }

        return $cache[$key] = $plural;
    }

    /**
     * 安全地求值 plural= 表达式
     *
     * 仅限整数/n/算术与比较运算, 不依赖 eval(), 因此即使 .mo 文件被篡改也不会执行任意代码.
     *
     * @param string $expr
     * @param int $n
     * @return int
     */
    private function evalPluralExpression(string $expr, int $n): int
    {
        $tokens = $this->tokenizePluralExpression($expr);

        if (empty($tokens)) {
            return 0;
        }

        $pos = 0;

        try {
            return (int)$this->parseTernary($tokens, $pos, $n);
        } catch (\Throwable $e) {
            // 表达式非法时退化为"非单数"形式, 不抛错影响页面渲染
            return 0;
        }
    }

    /**
     * @return array
     */
    private function tokenizePluralExpression(string $expr): array
    {
        // 限制长度, 避免损坏的 .mo 文件带来额外的解析开销
        if (strlen($expr) > 512) {
            return [];
        }

        $tokens = [];
        $length = strlen($expr);
        $i = 0;

        while ($i < $length) {
            $char = $expr[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if (ctype_digit($char)) {
                $num = '';
                while ($i < $length && ctype_digit($expr[$i])) {
                    $num .= $expr[$i++];
                }
                $tokens[] = ['num', (int)$num];
                continue;
            }

            if ('n' === $char || 'N' === $char) {
                $tokens[] = ['var', 'n'];
                $i++;
                continue;
            }

            $two = substr($expr, $i, 2);
            if (in_array($two, ['==', '!=', '<=', '>=', '&&', '||'], true)) {
                $tokens[] = ['op', $two];
                $i += 2;
                continue;
            }

            if (in_array($char, ['?', ':', '(', ')', '+', '-', '*', '/', '%', '<', '>', '!'], true)) {
                $tokens[] = ['op', $char];
                $i++;
                continue;
            }

            // 出现无法识别的字符时整体降级, 由调用方回退到默认复数形式
            return [];
        }

        return $tokens;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param string $op
     * @return bool
     */
    private function matchOp(array $tokens, int $pos, string $op): bool
    {
        return isset($tokens[$pos]) && 'op' === $tokens[$pos][0] && $tokens[$pos][1] === $op;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseTernary(array $tokens, int &$pos, int $n): int
    {
        $cond = $this->parseOr($tokens, $pos, $n);

        if ($this->matchOp($tokens, $pos, '?')) {
            $pos++;
            $yes = $this->parseTernary($tokens, $pos, $n);

            if (!$this->matchOp($tokens, $pos, ':')) {
                throw new \RuntimeException('Unexpected token, expected ":"');
            }

            $pos++;
            $no = $this->parseTernary($tokens, $pos, $n);

            return $cond ? $yes : $no;
        }

        return $cond;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseOr(array $tokens, int &$pos, int $n): int
    {
        $left = $this->parseAnd($tokens, $pos, $n);

        while ($this->matchOp($tokens, $pos, '||')) {
            $pos++;
            $right = $this->parseAnd($tokens, $pos, $n);
            $left = ($left || $right) ? 1 : 0;
        }

        return $left;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseAnd(array $tokens, int &$pos, int $n): int
    {
        $left = $this->parseEquality($tokens, $pos, $n);

        while ($this->matchOp($tokens, $pos, '&&')) {
            $pos++;
            $right = $this->parseEquality($tokens, $pos, $n);
            $left = ($left && $right) ? 1 : 0;
        }

        return $left;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseEquality(array $tokens, int &$pos, int $n): int
    {
        $left = $this->parseRelational($tokens, $pos, $n);

        while (true) {
            if ($this->matchOp($tokens, $pos, '==')) {
                $pos++;
                $left = ($left == $this->parseRelational($tokens, $pos, $n)) ? 1 : 0;
            } elseif ($this->matchOp($tokens, $pos, '!=')) {
                $pos++;
                $left = ($left != $this->parseRelational($tokens, $pos, $n)) ? 1 : 0;
            } else {
                break;
            }
        }

        return $left;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseRelational(array $tokens, int &$pos, int $n): int
    {
        $left = $this->parseAdditive($tokens, $pos, $n);

        while (true) {
            if ($this->matchOp($tokens, $pos, '<')) {
                $pos++;
                $left = ($left < $this->parseAdditive($tokens, $pos, $n)) ? 1 : 0;
            } elseif ($this->matchOp($tokens, $pos, '>')) {
                $pos++;
                $left = ($left > $this->parseAdditive($tokens, $pos, $n)) ? 1 : 0;
            } elseif ($this->matchOp($tokens, $pos, '<=')) {
                $pos++;
                $left = ($left <= $this->parseAdditive($tokens, $pos, $n)) ? 1 : 0;
            } elseif ($this->matchOp($tokens, $pos, '>=')) {
                $pos++;
                $left = ($left >= $this->parseAdditive($tokens, $pos, $n)) ? 1 : 0;
            } else {
                break;
            }
        }

        return $left;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseAdditive(array $tokens, int &$pos, int $n): int
    {
        $left = $this->parseMultiplicative($tokens, $pos, $n);

        while (true) {
            if ($this->matchOp($tokens, $pos, '+')) {
                $pos++;
                $left = $left + $this->parseMultiplicative($tokens, $pos, $n);
            } elseif ($this->matchOp($tokens, $pos, '-')) {
                $pos++;
                $left = $left - $this->parseMultiplicative($tokens, $pos, $n);
            } else {
                break;
            }
        }

        return $left;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseMultiplicative(array $tokens, int &$pos, int $n): int
    {
        $left = $this->parseUnary($tokens, $pos, $n);

        while (true) {
            if ($this->matchOp($tokens, $pos, '*')) {
                $pos++;
                $left = $left * $this->parseUnary($tokens, $pos, $n);
            } elseif ($this->matchOp($tokens, $pos, '/')) {
                $pos++;
                $right = $this->parseUnary($tokens, $pos, $n);
                $left = 0 === $right ? 0 : intdiv($left, $right);
            } elseif ($this->matchOp($tokens, $pos, '%')) {
                $pos++;
                $right = $this->parseUnary($tokens, $pos, $n);
                $left = 0 === $right ? 0 : $left % $right;
            } else {
                break;
            }
        }

        return $left;
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parseUnary(array $tokens, int &$pos, int $n): int
    {
        if ($this->matchOp($tokens, $pos, '!')) {
            $pos++;
            return $this->parseUnary($tokens, $pos, $n) ? 0 : 1;
        }

        if ($this->matchOp($tokens, $pos, '-')) {
            $pos++;
            return -$this->parseUnary($tokens, $pos, $n);
        }

        if ($this->matchOp($tokens, $pos, '+')) {
            $pos++;
            return $this->parseUnary($tokens, $pos, $n);
        }

        return $this->parsePrimary($tokens, $pos, $n);
    }

    /**
     * @param array $tokens
     * @param int $pos
     * @param int $n
     * @return int
     */
    private function parsePrimary(array $tokens, int &$pos, int $n): int
    {
        if (!isset($tokens[$pos])) {
            throw new \RuntimeException('Unexpected end of expression');
        }

        [$type, $value] = $tokens[$pos];

        if ('num' === $type) {
            $pos++;
            return (int)$value;
        }

        if ('var' === $type) {
            $pos++;
            return $n;
        }

        if ('op' === $type && '(' === $value) {
            $pos++;
            $result = $this->parseTernary($tokens, $pos, $n);

            if (!$this->matchOp($tokens, $pos, ')')) {
                throw new \RuntimeException('Unexpected token, expected ")"');
            }

            $pos++;
            return $result;
        }

        throw new \RuntimeException('Unexpected token');
    }

    /**
     * Get possible plural forms from MO header
     *
     * @access private
     * @return string plural form header
     */
    private function getPluralForms(): string
    {
        // lets assume message number 0 is header
        // this is true, right?
        $this->loadTables();

        // cache header field for plural forms
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
