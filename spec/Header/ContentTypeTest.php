<?php

namespace Virtu\Mime\Spec\Header;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\MediaType;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\HeaderInterface;
use Virtu\Mime\Header\ParameterizedHeaderInterface;

use TypeError;
use InvalidArgumentException;

class ContentTypeTest extends TestCase
{
	public function testInterface()
	{
		$header = new ContentType();
		$this->assertInstanceOf(HeaderInterface::class, $header);
		$this->assertInstanceOf(ParameterizedHeaderInterface::class, $header);
	}

	public function testGetters()
	{
		$header = new ContentType();
		$this->assertSame(MediaType::DEFAULT_TYPE, $header->getType());
		$this->assertSame(MediaType::DEFAULT_SUBTYPE, $header->getSubtype());
		$this->assertSame(ContentType::DEFAULT_NAME, $header->getName());
		$this->assertEmpty($header->getCharset());

		$type = 'text';
		$subtype = 'plain';
		$charset = 'utf-8';
		$params = ['name' => 'test.txt'];
		$header = new ContentType($type, $subtype, $params, $charset);
		$this->assertSame($type, $header->getType());
		$this->assertSame($subtype, $header->getSubtype());
		$this->assertSame($params, $header->getParams());
		$this->assertSame($charset, $header->getCharset());
	}

	public function testIsMessageGlobal()
	{
		$header = new ContentType();
		$this->assertFalse($header->isMessageGlobal());

		$header = new ContentType('multipart', 'mixed');
		$this->assertFalse($header->isMessageGlobal());

		$header = new ContentType('message', 'global');
		$this->assertTrue($header->isMessageGlobal());
	}

	public function testIsGenericMultipart()
	{
		$header = new ContentType();
		$this->assertFalse($header->isGenericMultipart());

		$header = new ContentType('multipart', 'mixed');
		$this->assertTrue($header->isGenericMultipart());

		$header = new ContentType('message', 'global');
		$this->assertTrue($header->isGenericMultipart());
	}

	public function testMissingSubtype()
	{
		$this->expectException(InvalidArgumentException::class);
		new ContentType('text');
	}
}
