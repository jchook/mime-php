<?php

namespace Virtu\Mime\Spec\Header;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Header\ParameterizedHeader;
use Virtu\Mime\Header\ParameterizedHeaderInterface;
use Virtu\Mime\Header\HeaderInterface;

use TypeError;
use InvalidArgumentException;

class ParameterizedHeaderTest extends TestCase
{
	public function testInterface()
	{
		$header = new ParameterizedHeader('name', 'value');
		$this->assertInstanceOf(HeaderInterface::class, $header);
		$this->assertInstanceOf(ParameterizedHeaderInterface::class, $header);
	}

	public function testGetters()
	{
		$name = 'X-My-Header';
		$value = 'value';
		$params = ['test' => 123];
		$header = new ParameterizedHeader($name, $value, $params);
		$this->assertSame($name, $header->getName());
		$this->assertSame([$value], $header->getValue());
		$this->assertSame($params, $header->getParams());
		$this->assertSame($params['test'], $header->getParam('test'));
		$this->assertEmpty($header->setParam('test', 'next'));
		$this->assertEquals('next', $header->getParam('test'));
		$this->assertEmpty($header->getParam('non-existant'));
	}
}
