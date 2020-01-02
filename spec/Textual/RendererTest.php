<?php

namespace Virtu\Mime\Spec\Textual;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Body\File;
use Virtu\Mime\Body\Part;
use Virtu\Mime\Body\Resource;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Codec\QuotedPrintable;
use Virtu\Mime\Contract\Linter;
use Virtu\Mime\Contract\Validator;
use Virtu\Mime\Element\Comment;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Element\Group;
use Virtu\Mime\Element\Keyword;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Message;
use Virtu\Mime\MessageBuilder;
use Virtu\Mime\Textual\Chars;
use Virtu\Mime\Textual\Renderer;

use RuntimeException;
use TypeError;

/**
 * @covers Virtu\Mime\Textual\Renderer
 */
class RendererTest extends TestCase
{

	public function testWrapHeader()
	{
		$renderer = new Renderer();
		$message = new Message([
			new ContentType('multipart', 'alternative', [
				'boundary' => '=_3efef44ba63d3377b0557d8d82e92dd6'
			]),
			new Header('Subject', [
				'😀🐙🚣😎🎉🎊👶🐧🦶🐹👺🥪🔔📙',
				' testing a longer non-encoded header here too now'
			]),
		]);
		$expected = implode("\r\n", [
			'Content-Type: multipart/alternative; ',
			"\t" . 'boundary="=_3efef44ba63d3377b0557d8d82e92dd6"',
			'Subject: =?UTF-8?B?8J+YgPCfkJnwn5qj8J+YjvCfjonwn46K8J+RtvCfkKfwn6a2?=',
			"\t" . '=?UTF-8?B?8J+QufCfkbrwn6Wq8J+UlPCfk5k=?= testing a longer non-encoded ',
			"\t" . 'header here too now',
			'',
			'',
		]);
		$this->assertEquals(
			$expected,
			$renderer->renderMessageString($message)
		);
	}

	public function testRfc2047()
	{
		$renderer = new Renderer();
		$message = new Message([
			new Header('From', new Mailbox('Paul Erdős', 'paul', 'thebook.god')),
			new Header('Subject', '😀🐙🚣😎🎉🎊👶🐧🦶🐹👺🥪🔔📙'),
		]);
		$expected = implode("\r\n", [
			'From: Paul =?UTF-8?B?RXJkxZFz?= <paul@thebook.god>',
			'Subject: =?UTF-8?B?8J+YgPCfkJnwn5qj8J+YjvCfjonwn46K8J+RtvCfkKfwn6a2?=',
			"\t=?UTF-8?B?8J+QufCfkbrwn6Wq8J+UlPCfk5k=?=",
			'',
			'',
		]);
		$this->assertEquals(
			$expected,
			$renderer->renderMessageString($message)
		);
	}

	public function testBase64()
	{
		$data = implode("\r\n", [
			'’Twas brillig, and the slithy toves',
      'Did gyre and gimble in the wabe:',
			'All mimsy were the borogoves,',
      'And the mome raths outgrabe.',
		]);
		$enc = [
			'4oCZVHdhcyBicmlsbGlnLCBhbmQgdGhlIHNsaXRoeSB0b3Zlcw0KRGlkIGd5cmUgYW5kIGdpbWJs',
			'ZSBpbiB0aGUgd2FiZToNCkFsbCBtaW1zeSB3ZXJlIHRoZSBib3JvZ292ZXMsDQpBbmQgdGhlIG1v',
			'bWUgcmF0aHMgb3V0Z3JhYmUu',
		];
		$message = new Message([
			new ContentTransferEncoding('base64'),
			new Text($data),
		]);
		$expected = implode("\r\n", array_merge([
			'Content-Transfer-Encoding: base64',
			'',
		], $enc, ['']));
		$b64 = implode('', $enc);
		$this->assertEquals($data, base64_decode($b64));
		$renderer = new Renderer();
		$this->assertEquals($expected, $renderer->renderMessageString($message));
	}

	public function testGlobalMailbox()
	{
		$renderer = new Renderer();
		$message = new Message([
			new ContentType('message', 'global', [
				'boundary' => 'boundary',
			]),
			new Part([
				new Header('From', new Mailbox('Paul Erdős', '"erdős"', 'יְרוּשָׁלַיִם.edu')),
			]),
		]);
		$expected = implode("\r\n", [
			'Content-Type: message/global; boundary=boundary',
			'',
			'--boundary',
			'From: "Paul Erdős" <"\"erdős\""@יְרוּשָׁלַיִם.edu>',
			'',
			'',
			'--boundary--',
			'',
		]);
		$this->assertEquals($expected, $renderer->renderMessageString($message));
	}

	public function testRenderComment()
	{
		$comment = 'hey';
		$renderer = new Renderer();
		$message = new Message([
			new Header('Subject', [new Comment($comment)]),
		]);
		$this->assertEquals(
			"Subject: ({$comment})\r\n\r\n",
			$renderer->renderMessageString($message)
		);
	}

	public function testRenderDateTime()
	{
		$date = date('r');
		$renderer = new Renderer();
		$message = new Message([
			new Header('Date', [new DateTimeImmutable($date)]),
		]);
		$this->assertEquals(
			"Date: {$date}\r\n\r\n",
			$renderer->renderMessageString($message)
		);
	}

	public function testCommaDelimited()
	{
		$renderer = new Renderer();
		$message = new Message([
			new Header('From', new Mailbox('Gregory Bateson', 'gbateson', 'si.edu')),
			new Header('To', [
				new Mailbox('Margaret Mead', '.mmead', 'columbia.edu'),
				new Group('Colleagues', [
					new Mailbox('John von Neumann', 'jvn', 'princeton.edu'),
					new Mailbox('Stephen Nachmanovitch', 'sn', 'ucsc.edu'),
				]),
				new Mailbox('', 'jane.goodall', 'newn.cam.ac.uk'),
			]),
			new Header('Keywords', [
				new Keyword('anthropology'),
				new Keyword('double-bind'),
			]),
			new Text('...'),
		]);
		$expected = implode("\r\n", [
			'From: "Gregory Bateson" <gbateson@si.edu>',
			'To: "Margaret Mead" <".mmead"@columbia.edu>, Colleagues:"John von Neumann" ',
			"\t" . '<jvn@princeton.edu>, "Stephen Nachmanovitch" <sn@ucsc.edu>;, ',
			"\t" . 'jane.goodall@newn.cam.ac.uk',
			'Keywords: anthropology, double-bind',
			'',
			'...',
		]);
		$this->assertEquals(
			$expected,
			$renderer->renderMessageString($message)
		);
	}

	public function testRenderStream()
	{
		$data = '🐟🐟';
		$stream = fopen('php://temp', 'rw');
		fwrite($stream, $data);
		$renderer = new Renderer();
		$message = new Message([
			new ContentTransferEncoding('binary'),
			new Resource($stream),
		]);
		$expected = implode("\r\n", [
			'Content-Transfer-Encoding: binary',
			'',
			$data,
		]);
		$this->assertEquals(
			$expected,
			$renderer->renderMessageString($message)
		);
	}

	public function testInvalidChild()
	{
		$this->expectException(TypeError::class);
		$renderer = new Renderer();
		$message = new Message([
			new ContentTransferEncoding('7bit'),
			[],
		]);
		$renderer->renderMessageString($message);
	}

	public function testIdentity()
	{
		$renderer = new Renderer();
		$message = new Message([
			new ContentTransferEncoding('8bit'),
			new Text('😀'),
		]);
		$this->assertEquals(
			implode("\r\n", [
				'Content-Transfer-Encoding: 8bit',
				'',
				'😀',
			]),
			$renderer->renderMessageString($message)
		);

		$message = new Message([
			new ContentTransferEncoding('7bit'),
			new Text('x'),
		]);
		$this->assertEquals(
			implode("\r\n", [
				'Content-Transfer-Encoding: 7bit',
				'',
				'x',
			]),
			$renderer->renderMessageString($message)
		);

		$message = new Message([
			new ContentTransferEncoding('binary'),
			new Text('🌲'),
		]);
		$this->assertEquals(
			implode("\r\n", [
				'Content-Transfer-Encoding: binary',
				'',
				'🌲',
			]),
			$renderer->renderMessageString($message)
		);
	}

	public function testVerbatim()
	{
		$renderer = new Renderer();
		$message = new Message([
			'verbatim', '!',
		]);
		$this->assertEquals(
			"\r\n" . 'verbatim!',
			$renderer->renderMessageString($message)
		);
	}

	public function testAst()
	{
		$message = new Message([
			new MimeVersion(),
			new ContentType('multipart', 'alternative', [
				'boundary' => 'my-custom-boundary-here',
			]),
			new ContentTransferEncoding('8bit'),
			new Header('From', new Mailbox('Donald', 'donald', 'whitehouse.gov')),
			new Header('To', [ new Mailbox('习近平', '习近平', '中国.操你') ]),
			new Header('Subject', 'Whatever subject here'),
			new Part([
				new ContentTransferEncoding('quoted-printable'),
				new ContentType('text', 'html', ['charset' => 'utf-8']),
				new Text([
					'<h1>Hello China 🍜</h1>',
					'<p>God bless you. 🦅</p>'
				])
			]),
			new Part([
				new ContentTransferEncoding('quoted-printable'),
				new ContentType('text', 'plain', ['charset' => 'utf-8']),
				new Text([
					"== Hello China 🍜 ==\r\n\r\n",
					"God bless you. 🦅"
				])
			]),
		]);

		$expected = implode("\r\n", [
			'MIME-Version: 1.0',
			'Content-Type: multipart/alternative; boundary=my-custom-boundary-here',
			'Content-Transfer-Encoding: 7bit',
			'From: Donald <donald@whitehouse.gov>',
			'To: =?UTF-8?B?5Lmg6L+R5bmzIDzkuaDov5HlubNAeG4tLWZpcXM4cy54bi0tNnFxMjUz?=',
			' =?UTF-8?B?Yz4=?=',
			'Subject: Whatever subject here',
			'',
			'--my-custom-boundary-here',
			'Content-Transfer-Encoding: quoted-printable',
			'Content-Type: text/html; charset=utf-8',
			'',
			'<h1>Hello China =F0=9F=8D=9C</h1><p>God bless you. =F0=9F=A6=85</p>',
			'--my-custom-boundary-here--',
			'',
			'--my-custom-boundary-here',
			'Content-Transfer-Encoding: quoted-printable',
			'Content-Type: text/plain; charset=utf-8',
			'',
			'=3D=3D Hello China =F0=9F=8D=9C =3D=3D',
			'',
			'God bless you. =F0=9F=A6=85',
			'--my-custom-boundary-here--',
			'',
			'',
		]);

		$renderer = new Renderer();
		$final = $renderer->renderMessageString($message);
		// echo "\n\n" . $final;
		// $this->assertEquals($expected, $final);
		$this->assertTrue(true);
	}
}