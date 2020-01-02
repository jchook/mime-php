<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\Version;
use Virtu\Mime\Element\VersionInterface;
use Virtu\Mime\Element\ElementInterface;

use TypeError;

/**
 * @covers Virtu\Mime\Element\Version
 */
class VersionTest extends TestCase
{
	public function testInterface()
	{
		$ele = new Version();
		$this->assertInstanceOf(VersionInterface::class, $ele);
		$this->assertInstanceOf(ElementInterface::class, $ele);
	}

	public function testGetters()
	{
		$major = 420;
		$minor = 69;
		$ele = new Version($major, $minor);
		$this->assertSame($major, $ele->getMajor());
		$this->assertSame($minor, $ele->getMinor());
	}

	public function testInvalidInput()
	{
		$this->expectException(TypeError::class);
		new Version('x', 'y');
	}
}
