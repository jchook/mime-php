<?php

namespace Virtu\Mime\Spec\Header;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Header\HeaderInterface;
use Virtu\Mime\Header\ParameterizedHeaderInterface;

use TypeError;
use InvalidArgumentException;

class HeaderTest extends TestCase
{
	public function testInterface()
	{
		$header = new Header('X-My-Header', 'value');
		$this->assertInstanceOf(HeaderInterface::class, $header);
	}

	public function testGetters()
	{
		$name = 'X-My-Header';
		$value = 'xxx';
		$charset = 'us-ascii';
		$header = new Header($name, $value, $charset);
		$this->assertEquals([$value], $header->getValue());
		$this->assertEquals($name, $header->getName());
		$this->assertEquals($charset, $header->getCharset());
	}

	public function testGetCharset()
	{
		$charset = 'us-ascii';
		$header = new Header('X-My-Header', 'value', $charset);
		$this->assertEquals($charset, $header->getCharset());

		$header = new Header('X-My-Header', 'value');
		$this->assertEmpty($header->getCharset());
	}

	public function testGetIterator()
	{
		$value = 'value';
		$header = new Header('X-My-Header', $value, 'us-ascii');
		$this->assertTrue(is_iterable($header->getIterator()));
		$this->assertEquals([$value], iterator_to_array($header->getIterator()));
	}

	public function testHasName()
	{
		$name = 'X-My-Header';
		$header = new Header($name, 'value', 'us-ascii');
		$this->assertTrue($header->hasName($name));
		$this->assertTrue($header->hasName(strtolower($name)));
		$this->assertTrue($header->hasName(strtoupper($name)));
	}

	public function testSetCharset()
	{
		$prevCharset = 'us-ascii';
		$charset = 'utf-8';
		$header = new Header('X-My-Header', 'value', $prevCharset);
		$this->assertEquals($prevCharset, $header->getCharset());
		$header->setCharset($charset);
		$this->assertEquals($charset, $header->getCharset());
	}

	public function testSetters()
	{
		$name = 'x-next-name';
		$value = 'next';
		$charset = 'utf-8';
		$header = new Header('X-My-Header', 'value', 'us-ascii');
		$this->assertNotEquals($name, $header->getName());
		$this->assertNotEquals([$value], $header->getValue());
		$this->assertNotEquals($charset, $header->getCharset());
		$header->setName($name);
		$header->setValue($value);
		$header->setCharset($charset);
		$this->assertEquals($name, $header->getName());
		$this->assertEquals([$value], $header->getValue());
		$this->assertEquals($charset, $header->getCharset());
	}

	public function testSetInvalidValue()
	{
		$this->expectException(TypeError::class);
		new Header('X-Test', null);
	}

}
