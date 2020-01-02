<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\Keyword;
use Virtu\Mime\Element\KeywordInterface;
use Virtu\Mime\Element\ElementInterface;

use TypeError;

/**
 * @covers Virtu\Mime\Element\Keyword
 */
class KeywordTest extends TestCase
{
	public function testInterface()
	{
		$ele = new Keyword('test');
		$this->assertInstanceOf(KeywordInterface::class, $ele);
		$this->assertInstanceOf(ElementInterface::class, $ele);
	}

	public function testGetter()
	{
		$str = 'test';
		$ele = new Keyword($str);
		$this->assertSame($str, $ele->getKeyword());
	}

	public function testInvalidInput()
	{
		$this->expectException(TypeError::class);
		new Keyword();
	}
}
