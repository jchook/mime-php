<?php

namespace Virtu\Mime\Spec\Textual;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Body\Part;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Element\Group;
use Virtu\Mime\Element\Keyword;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Textual\Parser;
use Virtu\Mime\Message;

use InvalidArgumentException;
use RuntimeException;
use OverflowException;

/**
 * @covers Virtu\Mime\Textual\Parser
 */
class ParserTest extends TestCase
{
	private function loadSample(int $num = 0)
	{
		return fopen(__DIR__ . '/samples/' . sprintf('%03d', $num) . '.eml', 'r');
	}

	public function testParseSamples()
	{
		$parser = new Parser();

		for ($ii = 0; $ii < 2; $ii++) {
			$source = $this->loadSample($ii);
			$message = $parser->parseMessage($source);
			$this->assertInstanceOf(Message::class, $message);
			// Make sure we read the entire message
			$this->assertTrue(feof($source));
		}
	}

	public function testParseEmptyString()
	{
		$parser = new Parser();
		$message = $parser->parseMessageString('');
		$this->assertEquals(new Message(), $message);
	}

	public function testInvalidConfiguration()
	{
		$errors = [
			[
				'bufMaxSize vs bufPeakSize length',
				['bufMaxSize' => 16, 'bufPeekSize' => 32],
			],
			[
				'bufMaxSize vs bufReadSize length',
				['bufMaxSize' => 16, 'bufReadSize' => 32],
			],
			[
				'invalid key',
				['madeUp' => true],
			],
		];
		foreach ($errors as [$desc, $args]) {
			try {
				$parser = new Parser($args);
			} catch (InvalidArgumentException $e) {
			} finally {
				$this->assertTrue(isset($e));
				unset($e);
			}
		}
	}

	public function testBufferOverflow()
	{
		$parser = new Parser([
			'bufMaxSize' => 6,
			'bufReadSize' => 1,
			'bufPeekSize' => 1,
		]);
		try {
			$parser->parseMessageString(implode("\r\n", [
				'To: (some comment longer than bufMaxSize)',
			]));
		} catch (OverflowException $e) {
		} finally {
			$this->assertTrue(isset($e));
		}
	}

	public function testCloseDelimiter()
	{
		$source = implode("\r\n", [
			'Content-Type: multipart/alternative; boundary=test',
			'',
			'--test',
			'',
			'thing',
			'--test--',
		]);
		$parser = new Parser();
		$message = $parser->parseMessageString($source);
		$this->assertEquals(new Message([
			new ContentType('multipart', 'alternative', ['boundary' => 'test']),
			new Part([
				new Text([
					'thing',
				]),
			]),
		]), $message);
	}

	public function testSyntaxErrors()
	{
		$errors = [
			[
				'empty header value',
				'From',
				':',
			],
			[
				'version',
				"MIME-Version: a.b\r\n",
				'version',
			],
			[
				'version dot',
				"MIME-Version: 1\r\n",
				'.',
			],
			[
				'version minor',
				"MIME-Version: 1.b\r\n",
				'number',
			],
			[
				'dotAtom cannot begin with dot',
				"Message-ID: <.test@.some.domain.com>\r\n",
				'localPart',
			],
			[
				'dotAtom cannot end with dot',
				"Message-ID: <test.@some.domain.com>\r\n",
				'atext',
			],
			[
				'non-global quoted pair must include vchar or wsp',
				implode("\r\n", [
					'Content-Type: multipart/mixed; boundary=boundary',
					'',
					'--boundary',
					"Message-ID: <\"\\ t\\est\\😀.\"@some.domain.com>\r\n",
					'',
					'--boundary--',
				]),
				'wsp, vchar',
			],
			[
				'missing preamble',
				"Content-Type: multipart/alternative; boundary=test\r\n\r\n",
				'dashBoundary',
			],
			[
				'control chars not allowed in quoted pairs',
				"Content-Type: multipart/alternative; something=\"\\" . chr(127) . "\"\r\n\r\n",
				'wsp, vchar',
			],
			[
				'missing closeDelimiter',
				implode("\r\n", [
					'Content-Type: multipart/alternative; boundary=test',
					'',
					'--test',
					'',
					'thing',
					''
				]),
				'closeDelimiter',
			],
			[
				'missing value',
				"Content-Type: multipart/alternative; boundary=\r\n\r\n",
				'value',
			],
			[
				'missing addrSpec',
				"From: Wes Roberts <>\r\n\r\n",
				'addrSpec',
			],
			[
				'missing angleAddr',
				"From: Wes Roberts\r\n\r\n",
				'angleAddr',
			],
			[
				'missing domain',
				"From: Wes Roberts <wes@>\r\n\r\n",
				'domain',
			],
			[
				'missing mediaType',
				"Content-Type: \r\n\r\n",
				'mediaType',
			],
			[
				'missing keywordList',
				"Keywords: \r\n\r\n",
				'keywordList',
			],
		];
		$parser = new Parser();
		foreach ($errors as $error) {
			$desc = $error[0] ?? '';
			$source = $error[1] ?? '';
			$expected = $error[2] ?? null;
			try {
				$message = $parser->parseMessageString($source);
				echo "\n\n>$desc\n";
				print_r($message);
			} catch (RuntimeException $e) {
			} finally {
				$this->assertTrue(isset($e), $desc);
				if (!is_null($expected)) {
					$this->assertStringStartsWith(
						'Expected ' . $expected,
						$e->getMessage(),
						$desc
					);
				} else {
					echo "\n> " . $desc . "\n" . $e->getMessage() . "\n";
				}
				unset($e);
			}
		}
	}

	public function testSyntaxErrorEmpty()
	{
		$source = implode("\r\n", [
			'From',
		]);
		$parser = new Parser();
		try {
			$message = $parser->parseMessageString($source);
			$this->assertInstanceOf(Message::class, $message);
		} catch (RuntimeException $e) {
		} finally {
			$this->assertTrue(isset($e));
		}
		// print_r($message);
	}

	public function testRfc6532()
	{
		$source = implode("\r\n",[
			'To: 😀@😀.😀',
			'Content-Type: message/global; boundary=global',
			'',
			'--global',
			'',
			'Hello 🌈',
			'--global--',
		]);

		$parser = new Parser();
		$message = $parser->parseMessageString($source);
		$expected = new Message([
			new Header('To', [
				new Mailbox('', '😀', '😀.😀'),
			]),
			new ContentType('message', 'global', ['boundary' => 'global']),
			new Part([
				new Text(['Hello 🌈']),
			]),
		]);
		$this->assertEquals($expected, $message);
	}

	public function testRfc2045()
	{
		$date = date('r');
		$source = implode("\r\n", [
			'From: "Wes Roberts" <wes@wzap.org>',
			'To: , ',
			' <hrosson@warren-wilson.edu>, ',
			' Sam Scoville <sam@wzap.org> ,',
			' "Evan \"The Rock\" Wantland" <ewantland@[127.0.0.1]> (\(Senior Badass\))',
			' ,friends: (my best friends)',
			' ;, family:maggie@wzap.org, Ben Chamberlin <bec@chamberlindesignbuild.com',
			' >; coworkers:; scientists:  ,,, ,  , "Bert " <albert@einstein.com>, ;',
			'Subject: Etymology and',
				"\t other stuff",
			'Keywords: "Big If True", "Any!"thing,',
				"\t another keyword, 11111",
				" (these keywords",
				" rock)",
			'MIME-Version: 1.0',
			'X-Unstrucured: (non-closed comment',
			'Date: ' . $date,
			'Content-Type: multipart/mixed; boundary="=_mixed"',
			'Content-Description: Some random description here',
			'',
			'preamble here',
			'with more than one line',
			'',
			'--=_mixed',
			'Content-Type: multipart/alternative; boundary=boundary',
			'Content-Transfer-Encoding: 8bit',
			'',
			'This is some preamble',
			'Please ignore this shit',
			'--boundary  ' . "\t", // transport padding
			'Content-Type: text/plain; charset=us-ascii',
			'',
			'Some text here',
			'lalalalala',
			'--boundary',
			'Content-Type: text/html; charset=us-ascii',
			'',
			'<h1>Hey</h1>',
			'--boundary--',
			'some epilogue here please ignore',
			'la ti data',
			'--=_mixed',
			'Content-Type: application/octet-stream',
			'Content-Transfer-Encoding: base64',
			'',
			'dGVzdA==',
			'--=_mixed',
			'Content-Type: application/octet-stream',
			'Content-Transfer-Encoding: base64',
			'',
			'dGVzdA==',
			'--=_mixed--',
			'',
			'another epilogue',
			'',
		]);
		$parser = new Parser([
			'bufReadSize' => 1,
			'bufPeekSize' => 1,
			'bufMaxSize' => 32,
		]);
		$message = $parser->parseMessageString($source);
		$this->assertInstanceOf(Message::class, $message);
		$expected = new Message([
			new Header('From', new Mailbox('Wes Roberts', 'wes', 'wzap.org')),
			new Header('To', [
				new Mailbox('', 'hrosson', 'warren-wilson.edu'),
				new Mailbox('Sam Scoville', 'sam', 'wzap.org'),
				new Mailbox('Evan "The Rock" Wantland', 'ewantland', '127.0.0.1'),
				new Group('friends', []),
				new Group('family', [
					new Mailbox('', 'maggie', 'wzap.org'),
					new Mailbox('Ben Chamberlin', 'bec', 'chamberlindesignbuild.com'),
				]),
				new Group('coworkers', []),
				new Group('scientists', [
					new Mailbox('Bert ', 'albert', 'einstein.com'),
				])
			]),
			new Header('Subject', 'Etymology and other stuff'),
			new Header('Keywords', [
				new Keyword('Big If True'),
				new Keyword('Any!thing'),
				new Keyword('another keyword'),
				new Keyword('11111'),
			]),
			new MimeVersion(1, 0),
			new Header('X-Unstrucured', '(non-closed comment'),
			new Header('Date', new DateTimeImmutable($date)),
			new ContentType('multipart', 'mixed', [
				'boundary' => '=_mixed',
			]),
			new Header('Content-Description', 'Some random description here'),
			new Part([
				new ContentType('multipart', 'alternative', [
					'boundary' => 'boundary',
				]),
				new ContentTransferEncoding('8bit'),
				new Part([
					new ContentType('text', 'plain', [
						'charset' => 'us-ascii',
					]),
					new Text(implode("\r\n", [
						'Some text here',
						'lalalalala',
					])),
				]),
				new Part([
					new ContentType('text', 'html', [
						'charset' => 'us-ascii',
					]),
					new Text(implode("\r\n", [
						'<h1>Hey</h1>',
					])),
				]),
			]),
			new Part([
				new ContentType('application', 'octet-stream'),
				new ContentTransferEncoding('base64'),
				new Text(['test']),
			]),
			new Part([
				new ContentType('application', 'octet-stream'),
				new ContentTransferEncoding('base64'),
				new Text(['test']),
			]),
		]);

		$this->assertEquals($expected, $message);
	}

	public function testRfc822()
	{
		$source = implode("\r\n", [
			'From: "Wes Roberts" <wes@wzap.org>',
			'To: , ',
			' Sam Scoville <sam@wzap.org> ,',
			' ewantland@wzap.org',
			'Message-ID: <xyz@123>',
			'Subject: Etymology and ',
				"\t other stuff",
			'MIME-Version: 1.0',
			'Date: ' . date('r'),
			'',
			'Hey Sam!',
			'',
		]);
		$parser = new Parser([
			'bufReadSize' => 8,
			'bufPeekSize' => 4,
		]);
		$message = $parser->parseMessageString($source);
		$this->assertInstanceOf(Message::class, $message);
	}
}
