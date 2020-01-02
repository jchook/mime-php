<?php

namespace Virtu\Mime\Spec;

use Virtu\Mime\Body\Part;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Element\MediaType;
use Virtu\Mime\Element\MediaTypeInterface;
use Virtu\Mime\Element\VersionInterface;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Message;
use Virtu\Mime\MessageMaster;

/**
 * @covers Virtu\Mime\MessageMaster
 */
class MessageMasterTest extends TestCase
{
	public function testDefault()
	{
		$message = new MessageMaster(new Message());
		$this->assertInstanceOf(MessageMaster::class, $message);
		$this->assertFalse($message->isMultipart());
		$this->assertEmpty($message->getVersion());
	}

	public function testGetVersionEmpty()
	{
		$message = new MessageMaster(new Message());
		$this->assertEmpty($message->getVersion());
	}

	public function testGetVersion()
	{
		$major = 16;
		$minor = 125;
		$message = new MessageMaster(new Message([
			new MimeVersion($major, $minor),
		]));
		$this->assertEquals($major, $message->getVersion()->getMajor());
		$this->assertEquals($minor, $message->getVersion()->getMinor());
	}

	public function testGetMediaTypeEmpty()
	{
		$message = new MessageMaster(new Message());
		$mt = $message->getMediaType();
		$this->assertEquals(MediaType::DEFAULT_TYPE, $mt->getType());
		$this->assertEquals(MediaType::DEFAULT_SUBTYPE, $mt->getSubtype());
	}

	public function testGetMediaType()
	{
		$type = 'multipart';
		$subtype = 'mixed';
		$message = new MessageMaster(new Message([
			new ContentType($type, $subtype),
		]));
		$mt = $message->getMediaType();
		$this->assertEquals($type, $mt->getType());
		$this->assertEquals($subtype, $mt->getSubtype());
	}

	public function testGetContentTypeEmpty()
	{
		$message = new MessageMaster(new Message());
		$ct = $message->getContentType();
		$this->assertEquals(MediaType::DEFAULT_TYPE, $ct->getType());
		$this->assertEquals(MediaType::DEFAULT_SUBTYPE, $ct->getSubtype());
	}

	public function testGetContentType()
	{
		$type = 'multipart';
		$subtype = 'mixed';
		$message = new MessageMaster(new Message([
			$ct = new ContentType($type, $subtype),
		]));
		$mt = $message->getMediaType();
		$this->assertSame($ct, $message->getContentType());
	}

	public function testGetContentTransferEncoding()
	{
		// Direct
		$type = 'multipart';
		$subtype = 'mixed';
		$message = new MessageMaster(new Message([
			$cte = new ContentTransferEncoding(
				ContentTransferEncoding::ENCODING_BINARY
			),
		]));
		$this->assertSame($cte, $message->getContentTransferEncoding());

		// From parent
		$message = new MessageMaster(new Message([
			$cte = new ContentTransferEncoding(
				ContentTransferEncoding::ENCODING_BINARY
			),
			new Part(),
		]));
		[$sub] = $message->getSubparts();
		$this->assertEquals($cte, $sub->getContentTransferEncoding());

		// Default
		$message = new MessageMaster(new Message([
			new Part(),
		]));
		[$sub] = $message->getSubparts();
		$this->assertEquals(
			new ContentTransferEncoding(),
			$sub->getContentTransferEncoding()
		);
	}

	public function testGetBodies()
	{

		$message = new MessageMaster(new Message([
			new MimeVersion(),
			new ContentType(),
			new ContentTransferEncoding(),
			'X-Verbatim-Header: Guess we cannot detect this',
			$t1 = new Text('Thing 1'),
			$t2 = new Text('Thing 2'),
			$vo = new Text('Voom'),
		]));
		$this->assertEquals([$t1, $t2, $vo], $message->getBodies());

		$message = new MessageMaster(new Message());
		$this->assertEquals([], $message->getBodies());
	}

	public function testGetHeaders()
	{
		$message = new MessageMaster(new Message());
		$this->assertEquals([], $message->getAllHeaders(), 'empty headers');

		$message = new MessageMaster(new Message([
			$mv = new MimeVersion(),
			$ct = new ContentType(),
			$s1 = new Header('X-Signature', 'abcd123'),
			$s2 = new Header('X-Signature', 'abcd123'),
			'X-Verbatim-Header: Guess we cannot detect this',
			new Text('Thing 1'),
			new Text('Thing 2'),
			new Text('Voom'),
		]));
		$this->assertEquals([$mv, $ct, $s1, $s2], $message->getAllHeaders());

		$this->assertEquals($ct, $message->getHeader('content-type'));
		$this->assertEquals([$ct], $message->getHeaders('content-type'));
		$this->assertEquals($s1, $message->getHeader('X-SiGnATuRE'));
		$this->assertEquals([$s1, $s2], $message->getHeaders('X-SIGNATURE'));
	}

	public function testGetHeadersRecursive()
	{
		$message = new MessageMaster(new Message());
		$this->assertEquals([], $message->getAllHeadersRecursive());

		$message = new MessageMaster(new Message([
			$mv = new MimeVersion(),
			$c1 = new ContentType('message', 'global'),
			new Part([
				$c2 = new ContentType('multipart', 'alternative'),
				$e2 = new ContentTransferEncoding('multipart', 'alternative'),
				new Part([
					$c4 = new ContentType('text', 'plain'),
					$s4 = new Header('X-Signature', '3'),
					new Text('Thing 1'),
					new Text('Thing 2'),
				]),
				new Part([
					$c5 = new ContentType('text', 'html'),
					$s5 = new Header('X-Signature', '4'),
					new Text('Voom'),
				]),
			]),
			new Part([
				$c3 = new ContentType('multipart', 'alternative'),
				$e3 = new ContentTransferEncoding('multipart', 'alternative'),
				new Part([
					$c6 = new ContentType('text', 'plain'),
					$s6 = new Header('X-Signature', '3'),
					new Text('Thing 1'),
					new Text('Thing 2'),
				]),
				new Part([
					$c7 = new ContentType('text', 'html'),
					$s7 = new Header('X-Signature', '4'),
					new Text('Voom'),
				]),
			])
		]));
		$this->assertEquals(
			[$mv, $c1, $c2, $e2, $c3, $e3, $c4, $s4, $c5, $s5, $c6, $s6, $c7 , $s7],
			$message->getAllHeadersRecursive()
		);
		$this->assertEquals(
			[$c1, $c2, $c3, $c4, $c5, $c6, $c7],
			$message->getHeadersRecursive('cOnTeNt-TyPe')
		);
	}

	public function testGetBodiesRecursive()
	{
		$message = new MessageMaster(new Message());
		$this->assertEquals([], $message->getBodiesRecursive());

		$message = new MessageMaster(new Message([
			new MimeVersion(),
			new ContentType('message', 'global'),
			new Part([
				new ContentType('multipart', 'alternative'),
				new ContentTransferEncoding('multipart', 'alternative'),
				new Part([
					new ContentType('text', 'plain'),
					new Header('X-Signature', '3'),
					$b1 = new Text('Thing 1'),
					$b2 = new Text('Thing 2'),
				]),
				new Part([
					new ContentType('text', 'html'),
					new Header('X-Signature', '4'),
					$b3 = new Text('Voom'),
				]),
			]),
			new Part([
				new ContentType('multipart', 'alternative'),
				new ContentTransferEncoding('multipart', 'alternative'),
				new Part([
					new ContentType('text', 'plain'),
					new Header('X-Signature', '3'),
					$b4 = new Text('Thing 1'),
					$b5 = new Text('Thing 2'),
				]),
				new Part([
					new ContentType('text', 'html'),
					new Header('X-Signature', '4'),
					$b6 = new Text('Voom'),
				]),
			])
		]));
		$this->assertEquals(
			[$b1, $b2, $b3, $b4, $b5, $b6],
			$message->getBodiesRecursive()
		);
	}

	public function testTraversal()
	{
		$message = new MessageMaster(new Message([
			new MimeVersion(),
			new ContentType('message', 'global'),
			$c1 = new Part([
				new ContentType('multipart', 'alternative'),
				new ContentTransferEncoding('multipart', 'alternative'),
				$c3 = new Part([
					new ContentType('text', 'plain'),
					new Header('X-Signature', '3'),
					new Text('Thing 1'),
					new Text('Thing 2'),
				]),
				$c4 = new Part([
					new ContentType('text', 'html'),
					new Header('X-Signature', '4'),
					new Text('Voom'),
				]),
			]),
			$c2 = new Part([
				new ContentType('multipart', 'alternative'),
				new ContentTransferEncoding('multipart', 'alternative'),
				$c5 = new Part([
					new ContentType('text', 'plain'),
					new Header('X-Signature', '3'),
					new Text('Thing 1'),
					new Text('Thing 2'),
				]),
				$c6 = new Part([
					new ContentType('text', 'html'),
					new Header('X-Signature', '4'),
					new Text('Voom'),
				]),
			])
		]));

		[$sc1, $sc2] = $message->getSubparts();
		$this->assertEquals($c1, $sc1->getPart());
		$this->assertEquals($c2, $sc2->getPart());

		[$sc3, $sc4] = $sc1->getSubparts();
		$this->assertEquals($c3, $sc3->getPart());
		$this->assertEquals($c4, $sc4->getPart());
		$this->assertEquals($sc1, $sc3->getParent());
		$this->assertEquals($sc1, $sc4->getParent());
		$this->assertEquals($message, $sc1->getParent());

		[$sc5, $sc6] = $sc2->getSubparts();
		$this->assertEquals($c5, $sc5->getPart());
		$this->assertEquals($c6, $sc6->getPart());
		$this->assertEquals($sc2, $sc5->getParent());
		$this->assertEquals($sc2, $sc6->getParent());
		$this->assertEquals($message, $sc2->getParent());
	}

	public function testMultipart()
	{
		$this->assertFalse((new MessageMaster(new Message()))->isMultipart());
		$this->assertTrue(
			(new MessageMaster(new Message([new Part()])))->isMultipart()
		);
	}

	public function testMessageGlobal()
	{
		$this->assertFalse((new MessageMaster(new Message()))->isMessageGlobal());
		$this->assertTrue(
			(new MessageMaster(new Message([
				new ContentType('message', 'global'),
			])))->isMessageGlobal()
		);
	}
}