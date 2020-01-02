<?php

namespace Virtu\Mime\Spec\Header;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\HeaderInterface;

use TypeError;

class ContentTransferEncodingTest extends TestCase
{
	public function testInterface()
	{
		$header = new ContentTransferEncoding();
		$this->assertInstanceOf(HeaderInterface::class, $header);
	}

	public function testGetters()
	{
		$enc = ContentTransferEncoding::ENCODING_7BIT;
		$header = new ContentTransferEncoding();
		$this->assertSame($enc, $header->getEncoding());
		$this->assertSame('Content-Transfer-Encoding', $header->getName());

		$enc = ContentTransferEncoding::ENCODING_BASE64;
		$header = new ContentTransferEncoding($enc);
		$this->assertSame($enc, $header->getEncoding());
	}
}
