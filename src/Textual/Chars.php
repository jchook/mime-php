<?php

namespace Virtu\Mime\Textual;

use Iterator;
use RuntimeException;

/**
 * String of characters
 * TODO: make a stream version of this?
 */
class Chars implements Iterator
{
	private $current;
	private $index = -1;
	private $pointer = 0;
	private $string;

	public function __construct(string $string, string $charset = 'utf-8')
	{
		$this->charset = strtolower($charset);
		$this->string = $string;
		$this->rewind();
	}

	public function getCharset(): string
	{
		return $this->charset;
	}

	public function getString(): string
	{
		return $this->string;
	}

	public function getPointer(): string
	{
		return $this->pointer;
	}

	public function setCharset(string $charset): void
	{
		$this->charset = $charset;
	}

	public function setString(string $string): void
	{
		$this->string = $string;
		$this->rewind();
	}

	public function setPointer(int $pointer): void
	{
		$this->pointer = $pointer;
	}

	public function current() // : mixed
	{
		return $this->current;
	}

	public function key() // : scalar
	{
		return $this->index;
	}

	public function next() // : void
	{
		switch ($this->charset) {
			case 'utf-8':
			case 'us-ascii':
				$this->current = self::nextCharUtf8($this->string, $this->pointer);
				if ($this->valid()) {
					$this->index++;
				}
				break;

			default:
				throw new RuntimeException(
					'Unrecognized charset: ' . $this->charset
				);
		}
	}

	public function rewind() // : void
	{
		$this->pointer = 0;
		$this->index = -1;
		$this->next();
	}

	public function valid() // : bool
	{
		return !is_null($this->current);
	}


	/**
	 * mb_substr() and other mb functions are SO SLOW.
	 * This is somehow much faster.
	 */
	public static function nextCharUtf8(&$string, &$pointer)
	{
		// EOF
		if (!isset($string[$pointer])) {
			return null;
		}

		// Get the byte value at the pointer
		$char = ord($string[$pointer]);

		// ASCII
		if ($char < 128) {
			return $string[$pointer++];
		}

		// UTF-8
		if ($char < 224) {
			$bytes = 2;
		} elseif ($char < 240) {
			$bytes = 3;
		} elseif ($char < 248) {
			$bytes = 4;
		} else {
			// throw error?
			throw new RuntimeException('Invalid UTF-8 string');
		}
		// Deprecated UTF-8 codepoints
		// } elseif ($char == 252) {
		// 	$bytes = 5;
		// } else {
		// 	$bytes = 6;
		// }

		// Get full multibyte char
		$str = substr($string, $pointer, $bytes);

		// Increment pointer according to length of char
		$pointer += $bytes;

		// Return mb char
		return $str;
	}

	/**
	 * str_replace for multibyte strings
	 */
	function mbStrReplace(
		string $search,
		string $replace,
		string $subject,
		int &$count = 0
	): string
	{
		$parts = mb_split(preg_quote($search), $subject);
		$count += count($parts) - 1;
		return implode($replace, $parts);
 }

	/**
	 * Word-wrap (US-ASCII)
	 */
	public static function wordWrap(
		string $str,
		int $width = 75,
		string $break = "\n",
		bool $cut = false,
		int &$indent = 0,
		array $sepChars = [' ', "\n", "\t", ',', '-']
	): string
	{
		$totalLen = strlen($str);
		if ($totalLen + $indent <= $width) {
			$indent += $totalLen;
			return $str;
		}
		$initialIndent = $indent;
		$seps = [];
		$chunks = [];
		$chunk = '';
		$pointer = 0;
		$len = 0;
		for ($ii = 0; $ii < $totalLen; $ii++) {
			$char = $str[$ii];
			$chunk .= $char;
			$len++;
			if (in_array($char, $sepChars, true) || ($cut && $len >= $width)) {
				$chunks[] = [$len, $chunk];
				$len = 0;
				$chunk = '';
			}
		}
		if ($chunk) {
			$chunks[] = [$len, $chunk];
		}
		$line = '';
		$lines = [];
		foreach ($chunks as [$len, $chunk]) {
			if ($indent + $len > $width) {
				if ($line || (!$lines && $initialIndent)) {
					$lines[] = $line;
					$indent = 0;
					$line = '';
				}
			}
			$line .= $chunk;
			$indent += $len;
		}
		if ($line) {
			$lines[] = $line;
		}

		// print_r(compact('str', 'initialIndent', 'indent', 'lines',));
		return implode($break, $lines);
	}

	/**
	 * Word wrap UTF-8
	 * @link https://www.fileformat.info/info/unicode/category/Ps/list.htm
	 * etc
	 */
	public static function wordWrapUtf8(
		string $str,
		int $width = 75,
		string $break = "\n",
		bool $cut = false,
		int &$indent = 0,
		array $seps = [' ', "\n", "\t", '，', '-']
	): string
	{
		$chunks = [];
		$chunk = '';
		$len = 0;
		$pointer = 0;
		while (!is_null($char = self::nextCharUtf8($str, $pointer))) {
			$chunk .= $char;
			$len++;
			if (in_array($char, $seps, true) || ($cut && $len >= $width)) {
				$chunks[] = [$len, $chunk];
				$len = 0;
				$chunk = '';
			}
		}
		if ($chunk) {
			$chunks[] = [$len, $chunk];
		}
		$line = '';
		$lines = [];
		foreach ($chunks as [$len, $chunk]) {
			if ($indent + $len > $width) {
				if ($line) {
					$lines[] = $line;
					$indent = 0;
					$line = '';
				}
			}
			$line .= $chunk;
			$indent += $len;
		}
		if ($line) {
			$lines[] = $line;
		}
		return implode($break, $lines);
	}

}
