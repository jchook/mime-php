<?php

namespace Virtu\Mime\Spec\Textual;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Textual\Chars;

use RuntimeException;

/**
 * @covers Virtu\Mime\Textual\Chars
 */
class CharsTest extends TestCase
{
	public function testMbStrReplace()
	{
		$pre = '机不可"失"👩‍👧‍👧';
		$str = Chars::mbStrReplace('"', '\\"', $pre);
		$this->assertEquals('机不可\\"失\\"👩‍👧‍👧', $str);
	}

	public function testChars()
	{
		$charset = 'utf-8';
		$pre = '机不可失👩‍👧‍👧';
		$str = new Chars($pre, $charset);

		// Basics
		$this->assertEquals($charset, $str->getCharset());
		$this->assertEquals($pre, $str->getString());

		// Foreachy
		$post = '';
		$count = 0;
		$this->assertTrue($str->valid());
		$this->assertEquals('机', $str->current());
		foreach($str as $char) {
			$count++;
			$post .= $char;
		}
		$this->assertNotEquals(mb_strlen($pre), strlen($pre));
		$this->assertEquals(mb_strlen($pre), $count);
		$this->assertEquals($pre, $post);

		// Manual iteration
		$post = '';
		$len = mb_strlen($pre);
		$this->assertEmpty($str->rewind());
		for ($ii = 0; $ii < $len; $ii++) {
			$post .= $str->current();
			$this->assertTrue($str->valid());
			$this->assertEquals($ii, $str->key());
			$this->assertEquals(mb_substr($pre, $ii, 1), $str->current());
			$this->assertEmpty($str->next());
		}
		$this->assertEquals(strlen($pre), $str->getPointer());
		$this->assertFalse($str->valid());
		$this->assertEquals($pre, $post);
	}

	public function testInvalidUtf8()
	{
		$this->expectException(RuntimeException::class);
		$pre = "\xFF\x08\x20\x80";
		$str = new Chars($pre);
		foreach ($str as $chr);
	}

	public function testSetPointerInvalid()
	{
		$pre = '机不可失';
		$str = new Chars($pre);
		$str->setPointer(2);
		$this->assertEquals(2, $str->getPointer());
		$str->next();
		$this->assertTrue($str->valid());
		$this->assertFalse(mb_check_encoding($str->current(), 'UTF-8'));
	}

	public function testSetString()
	{
		$pre = '机不可失';

		$str = new Chars($pre);
		$str->next();
		$str->next();
		$this->assertEquals(2, $str->key());
		$this->assertEquals(mb_substr($pre, $str->key(), 1), $str->current());
		$str->setString('test');
		$this->assertEquals(0, $str->key());
		$this->assertEquals('t', $str->current());
	}

	public function testUsAscii()
	{
		$pre = 'test string no multibyte';
		$str = new Chars($pre);
		$this->assertEquals('utf-8', $str->getCharset());
		$str->setCharset('us-ascii');

		$count = 0;
		$post = '';
		foreach($str as $char) {
			$count++;
			$post .= $char;
		}
		$this->assertEquals(strlen($pre), $count);
		$this->assertEquals($pre, $post);
	}

	public function testCharsetInvalid()
	{
		$this->expectException(RuntimeException::class);
		new Chars('', 'big5');
	}

	public function testWordWrap()
	{
		$str = 'test, thing. yes, please. whatever.';
		$wrap = Chars::wordWrap($str, 12);
		$this->assertEquals(
			"test, \nthing. yes, \nplease. \nwhatever.",
			$wrap
		);

		$str = 'test, thing. yes, please. whatever.';
		$wrap = Chars::wordWrap($str, 1, "\n", true);
		$this->assertEquals(
			implode("\n", str_split($str, 1)),
			$wrap
		);

		$str = 'test';
		$indent = 1;
		$this->assertEquals("\ntest", Chars::wordWrap($str, 1, "\n", false, $indent));

		$str = 'test';
		$indent = 50;
		$this->assertEquals($str, Chars::wordWrap($str));
	}

	public function testWordWrapUtf8()
	{
		$str = '机不可失，时不再来. 不怕慢, 就怕停. 授人以鱼不如授人以渔.';
		$wrap = Chars::wordWrapUtf8($str, 12);
		$this->assertEquals(
			"机不可失，时不再来. \n不怕慢, 就怕停. \n授人以鱼不如授人以渔.",
			$wrap
		);
	}

	public function testWordWrapUtf8Cut()
	{
		$str = '机不可失，时不再来. 不怕慢, 就怕停. 授人以鱼不如授人以渔.';
		$wrap = Chars::wordWrapUtf8($str, 1);
		$this->assertEquals(
			"机不可失，\n时不再来. \n不怕慢, \n就怕停. \n授人以鱼不如授人以渔.",
			$wrap
		);
		$wrap = Chars::wordWrapUtf8($str, 1, "\n", true);
		$this->assertEquals(
			"机\n不\n可\n失\n，\n时\n不\n再\n来\n.\n \n不\n怕\n慢\n,\n \n就\n怕\n停\n.\n \n授\n人\n以\n鱼\n不\n如\n授\n人\n以\n渔\n.",
			$wrap
		);
	}

}
