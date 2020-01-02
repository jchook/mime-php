<?php

namespace Virtu\Mime\Spec\Header;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\Version;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Header\HeaderInterface;
use Virtu\Mime\Header\UnstructuredHeaderInterface;

use TypeError;
use InvalidArgumentException;

class MimeVersionTest extends TestCase
{
	public function testInterface()
	{
		$header = new MimeVersion();
		$this->assertInstanceOf(HeaderInterface::class, $header);
	}

	public function testGetters()
	{
		$header = new MimeVersion();
		$this->assertSame(Version::DEFAULT_MAJOR, $header->getMajor());
		$this->assertSame(Version::DEFAULT_MINOR, $header->getMinor());
		$this->assertSame(MimeVersion::DEFAULT_NAME, $header->getName());
	}

	public function testConstructor()
	{
		$header = new MimeVersion(2, 5);
		$header->setName('MIMEish-Version');
		$this->assertSame(2, $header->getMajor());
		$this->assertSame(5, $header->getMinor());
		$this->assertSame('MIMEish-Version', $header->getName());
	}
}
