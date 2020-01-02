<?php

namespace Virtu\Mime\Spec\Textual;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Textual\Lexeme;

use RuntimeException;

/**
 * @covers Virtu\Mime\Textual\Lexeme
 */
class LexemeTest extends TestCase
{
	public function test7Bit()
	{
		$s = '';
		for ($i = 0; $i <= 127; $i++) $s .= chr($i);
		$this->assertTrue(Lexeme::is7Bit($s));
		$s = chr(128);
		$this->assertFalse(Lexeme::is7Bit($s));
	}

	public function testAText()
	{
		$allowed = [
			'a', '5', "!", "#", "$", "%", "&", "'", "*", "+",
	 	  "-", "/", "=", "?", "^", "_", "`", "{", "|", "}", "~"
		];
		$this->assertTrue(Lexeme::isAText(implode('', $allowed)));
		$disallowed = [
			"(", ")", "<", ">", "[", "]", ":", ";", "@", "\\", ",", ".", ' ',
		];
		foreach ($disallowed as $chr) {
			$this->assertFalse(Lexeme::isAText($chr), "$chr not allowed in atext");
		}

		$allowed = implode('', [
			'☁️_⛅️_☁️_☁️_☁️_☁️_☁️_☁️',
			'',
			'____🎈',
			'',
			'_________🏃💨',
		]);
		$this->assertTrue(Lexeme::isAText($allowed, true), 'utf-8 allowed in atext');
		$this->assertFalse(Lexeme::isAText($allowed, false), 'utf-8 not allowed in atext');
	}

	public function testDotAtomText()
	{
		$this->assertFalse(Lexeme::isDotAtomText('.test.thing'));
		$this->assertFalse(Lexeme::isDotAtomText('test.thing.'));
		$this->assertTrue(Lexeme::isDotAtomText('test.thing'));
		$this->assertTrue(Lexeme::isDotAtomText('👯.👯', true));
		$this->assertFalse(Lexeme::isDotAtomText('👯.👯', false));
	}

	public function testQuotable()
	{
		$allowed = [];
		for ($i = 33; $i < 127; $i++) {
			$allowed[] = chr($i);
		}
		$this->assertTrue(Lexeme::isQuotable(implode('', $allowed)));
		$this->assertFalse(Lexeme::isQuotable('🥓'), 'emoji in qtext');
		$this->assertTrue(Lexeme::isQuotable('🥓', true), 'emoji in global qtext');
	}

	public function testToken()
	{
		$disallowed = [
			"(", ")", "<", ">", "@", ",", ";", ":", "\\", '"', "/", "[", "]", "?", "=",
			' ', "\t"
		];
		for ($i = 32; $i < 127; $i++) {
			$chr = chr($i);
			if (!in_array($chr, $disallowed)) {
				$this->assertTrue(Lexeme::isToken($chr), $chr . ' allowed in token');
			}
		}

		foreach ($disallowed as $chr) {
			$this->assertFalse(Lexeme::isToken($chr), $chr . ' disallowed in token');
		}
	}
}