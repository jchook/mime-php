<?php

namespace Virtu\Mime\Spec\Contract;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Body\File;
use Virtu\Mime\Body\Part;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Contract\Rule;
use Virtu\Mime\Contract\Validator;
use Virtu\Mime\Element\DateTimeInterface;
use Virtu\Mime\Element\GroupInterface;
use Virtu\Mime\Element\KeywordInterface;
use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\MediaTypeInterface;
use Virtu\Mime\Element\MessageIdInterface;
use Virtu\Mime\Element\VersionInterface;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Element\Group;
use Virtu\Mime\Element\Keyword;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Element\MediaType;
use Virtu\Mime\Element\MessageId;
use Virtu\Mime\Element\Version;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\MessageBuilder;
use Virtu\Mime\Message;

use RuntimeException;
use TypeError;

/**
 * @coversDefaultClass Virtu\Mime\Contract\Validator
 */
class ValidatorTest extends TestCase
{

	public function testContentTransferEncodingMultipart()
	{
		$validator = new Validator([
			'content-transfer-encoding' => Rule::ERROR,
		]);
		$message = new Message([
			new ContentTransferEncoding('quoted-printable'),
			new Part(),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'content-transfer-encoding' => [
				'Multipart part must use identity content-transfer-encoding',
			],
		], $result);
	}

	public function testContentTransferEncoding7Bit()
	{
		$validator = new Validator([
			'content-transfer-encoding' => Rule::ERROR,
		]);
		$message = new Message([
			new ContentTransferEncoding('7bit'),
			new Text('😀'),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'content-transfer-encoding' => [
				'8-bit characters found in body with 7-bit encoding declared',
			],
		], $result);
	}

	public function testContentTransferEncodingBase64()
	{
		$validator = new Validator([
			'content-transfer-encoding' => Rule::ERROR,
		]);
		$message = new Message([
			new ContentTransferEncoding('quoted-printable'),
			new ContentType('application', 'octet-stream'),
			new Text('😀'),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'content-transfer-encoding' => [
				'Expected binary or base64 content-transfer-encoding for application content-type',
			],
		], $result);
	}

	public function testFileExists()
	{
		$validator = new Validator([
			'file-exists' => Rule::ERROR,
		]);
		$message = new Message([
			new ContentTransferEncoding('base64'),
			new ContentType('application', 'octet-stream'),
			new File('/tmp/non-existant'),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'file-exists' => [
				'File does not exist: /tmp/non-existant',
			],
		], $result);
	}

	public function testFileReadable()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test');
		file_put_contents($path, random_bytes(64));
		chmod($path, 0077);
		$validator = new Validator([
			'file-readable' => Rule::ERROR,
		]);
		$message = new Message([
			new ContentTransferEncoding('base64'),
			new ContentType('application', 'octet-stream'),
			new File($path),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'file-readable' => [
				'Cannot read file: ' . $path,
			],
		], $result);
		unlink($path);
	}

	public function testHeaderCount()
	{
		$validator = new Validator([
			'header-count' => Rule::ERROR,
		]);
		$message = new Message([
			new Header('sender', []),
			new Header('sender', []),
			new Header('reply-to', []),
			new Header('reply-to', []),
			new Header('to', []),
			new Header('to', []),
			new Header('cc', []),
			new Header('cc', []),
			new Header('bcc', []),
			new Header('bcc', []),
			new Header('message-id', []),
			new Header('message-id', []),
			new Header('in-reply-to', []),
			new Header('in-reply-to', []),
			new Header('references', []),
			new Header('references', []),
			new Header('subject', []),
			new Header('subject', []),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'header-count' => [
				'Must have at least 1 date header',
			  'Must have at least 1 from header',
			  'Too many sender headers (max: 1, found: 2)',
			  'Too many reply-to headers (max: 1, found: 2)',
			  'Too many to headers (max: 1, found: 2)',
			  'Too many cc headers (max: 1, found: 2)',
			  'Too many bcc headers (max: 1, found: 2)',
			  'Too many message-id headers (max: 1, found: 2)',
			  'Too many in-reply-to headers (max: 1, found: 2)',
			  'Too many references headers (max: 1, found: 2)',
			  'Too many subject headers (max: 1, found: 2)',
			],
		], $result);
	}

	public function testInvalidRuleFormat()
	{
		$this->expectException(RuntimeException::class);
		$validator = new Validator([
			'mime-version' => [Rule::ERROR],
		]);
	}

	public function testInvalidRuleName()
	{
		$this->expectException(RuntimeException::class);
		$validator = new Validator([
			'mime-version' => Rule::NONE,
			'bogus-rule' => Rule::ERROR,
		]);
		$validator->validateMessage(new Message());
	}

	public function testMimeVersionMissing()
	{
		$validator = new Validator([
			'mime-version' => Rule::ERROR,
		]);

		$message = new Message([
			new MimeVersion(),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([], $result);

		$message = new Message();
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'mime-version' => ['Missing MIME-Version header'],
		], $result);

		$message = new Message([
			new MimeVersion(5, 0),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'mime-version' => ['Unrecognized MIME-Version'],
		], $result);
	}

	public function testNesting()
	{
		$validator = new Validator([
			'nesting' => [Rule::ERROR, []],
		]);
		$message = new Message([
			new ContentType('application', 'octet-stream'),
			new Part(),
		]);
		$result = $validator->validateMessage($message);
		$this->assertEquals([
			'nesting' => ['Multipart body part must have a multipart content-type'],
		], $result);
	}

	public function testValue()
	{
		$validator = new Validator([
			'header-value' => Rule::ERROR,
		]);
		$correct = [];
		$incorrect1 = [];
		$incorrect2 = [];
		$incorrect3 = [
			new Header('Content-Id', [new Version(1, 0)]),
		];
		$incorrect4 = [
			new Header('From', [new Mailbox('', '', ''), 'string too']),
		];

		$reqs = Validator::$headerValueRequirements;
		foreach ($reqs as $name => [$min, $max, $classes]) {

			if ($max === 0) {
				$max = 4;
			}

			$instances = [];
			for ($i = 0; $i < $max; $i++) {
				$class = $classes[$i % count($classes)];
				$instances[] = $this->instantiateHeaderElement($class);
			}

			// put the juuust right amount into
			$correct[] = new Header($name, $instances);

			// Put one too many into incorrect2
			$instances[] = $this->instantiateHeaderElement(
				$classes[array_rand($classes)]
			);
			$incorrect2[] = new Header($name, $instances);


			// put too few in incorrect1
			if ($min) {
				$incorrect1[] = new Header($name, []);
			}
		}
		$result = $validator->validateMessage(new Message($correct));
		$this->assertEmpty($result);

		$this->assertNotEmpty($validator->validateMessage(new Message($incorrect1)));
		$this->assertNotEmpty($validator->validateMessage(new Message($incorrect2)));
		$this->assertNotEmpty($validator->validateMessage(new Message($incorrect3)));
		$this->assertNotEmpty($validator->validateMessage(new Message($incorrect4)));
	}

	private function instantiateHeaderElement($class)
	{
		switch ($class) {
			case 'string': return '';
			case DateTimeInterface::class: return new DateTimeImmutable();
			case GroupInterface::class: return new Group('', []);
			case KeywordInterface::class: return new Keyword('');
			case MailboxInterface::class: return new Mailbox('', '', '');
			case MediaTypeInterface::class: return new MediaType('', '');
			case MessageIdInterface::class: return new MessageId('', '');
			case VersionInterface::class: return new Version(1, 0);
			default: throw new RuntimeException('Unrecognized interface: ' . $class);
		}
	}

	public function testValidate()
	{
		$file = '/tmp/attachment.bin';
		file_put_contents($file, random_bytes(256));
		$now = new DateTimeImmutable();

		$message = (new MessageBuilder())
			->to('ready@example.com')
			->from('set@sample.com')
			->replyTo('go@sample.com')
			->subject('Test subject')
			->header('Custom-Thing', 'test')
			->date($now)
			->text('Hello world')
			->html('<h1>Hello world</h1>')
			->attach($file)
			->getMessage()
		;

		$validator = new Validator();
		$result = $validator->validateMessage($message);
		$this->assertEmpty($result);
	}
}
