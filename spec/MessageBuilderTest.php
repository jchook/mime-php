<?php

namespace Virtu\Mime\Spec;

use Virtu\Mime\Message;
use Virtu\Mime\MessageBuilder;
use Virtu\Mime\MessageMaster;
use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Body\BodyInterface;
use Virtu\Mime\Body\File;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Element\DateTimeInterface;
use Virtu\Mime\Element\Group;
use Virtu\Mime\Element\Keyword;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Element\MessageId;
use Virtu\Mime\Header\Header;

use BadMethodCallException;
use DateTime as RealDateTime;
use DateTimeInterface as RealDateTimeInterface;
use TypeError;

/**
 * @covers Virtu\Mime\MessageBuilder
 */
class MessageBuilderTest extends TestCase
{
	private function toFn(string $headerName): string
	{
		return lcfirst(implode('', array_map('ucfirst', explode('-', $headerName))));
	}

	public function testInvalid()
	{
		$this->expectException(BadMethodCallException::class);
		(new MessageBuilder)->weirdHeader();
	}

	public function testUnstructuredHeader()
	{
		// Default
		try {
			(new MessageBuilder)->header()->getMessage();
		} catch (TypeError $e) {
		} finally {
			$this->assertTrue(isset($e));
			unset($e);
		}

		// String
		$name = 'X-My-Header';
		$ele = 'test';
		$message = new MessageMaster((new MessageBuilder)->header($name, $ele)->getMessage());
		$this->assertEquals(
			new Header($name, $ele),
			$message->getHeader($name)
		);
	}

	public function testDate()
	{
		$names = [
			'date',
			'resent-date'
		];

		foreach($names as $name) {
			$fn = $this->toFn($name);

			// Default
			$message = new MessageMaster((new MessageBuilder)->$fn()->getMessage());
			$this->assertInstanceOf(
				DateTimeInterface::class,
				$message->getHeader($name)->getValue()[0]
			);

			// Custom DateTime
			$then = new RealDateTime('-10 minutes');
			$this->assertInstanceOf(RealDateTimeInterface::class, $then);
			$message = new MessageMaster((new MessageBuilder)
				->$fn($then)
				->getMessage()
			);
			$this->assertInstanceOf(
				DateTimeInterface::class,
				$message->getHeader($name)->getValue()[0]
			);
			$this->assertEquals(
				$then->format('r'),
				$message->getHeader($name)->getValue()[0]->format('r')
			);

			// Invalid arg
			try {
				(new MessageBuilder)->$fn((object)[])->getMessage();
			} catch (TypeError $e) {
			} finally {
				$this->assertTrue(isset($e));
				unset($e);
			}
		}
	}

	public function testAddress()
	{
		$headerNames = [
			'to',
			'resent-to',
			'cc',
			'resent-cc',
			'bcc',
			'resent-bcc',
			'from',
			'resent-from',
			'sender',
			'resent-sender',
			'reply-to',
			'resent-reply-to',
		];

		foreach($headerNames as $name) {
			$fn = $this->toFn($name);

			// Default
			try {
				(new MessageBuilder)->$fn()->getMessage();
			} catch (TypeError $e) {
			} finally {
				$this->assertTrue(isset($e));
				unset($e);
			}

			// Single mailbox
			$mailbox = new Mailbox('Albert Einstein', 'einstein', 'uzh.ch');
			$message = new MessageMaster((new MessageBuilder)->$fn($mailbox)->getMessage());
			$this->assertSame($mailbox, $message->getHeader($name)->getValue()[0]);

			// Single name-addr
			$mailname = 'Albert Einstein';
			$mailbox = 'x@einstein@uzh.ch';
			$message = new MessageMaster((new MessageBuilder)->$fn($mailname, $mailbox)->getMessage());
			$this->assertEquals(
				new Mailbox('Albert Einstein', 'x@einstein', 'uzh.ch'),
				$message->getHeader($name)->getValue()[0]
			);

			// String
			$mailbox = 'einstein@uzh.ch';
			$message = new MessageMaster((new MessageBuilder)->$fn($mailbox)->getMessage());
			$this->assertEquals(
				new Mailbox('', 'einstein', 'uzh.ch'),
				$message->getHeader($name)->getValue()[0]
			);

			// Multi-mailbox + groups
			$address = [
				new Mailbox('Albert Einstein', 'einstein', 'uzh.ch'),
				new Group('Nerds', [
					new Mailbox('Paul Ehrenfest', 'paul', 'universiteitleiden.nl'),
					new Mailbox('Marcel Grossmann', 'marcel', 'ethz.ch'),
				]),
			];
			$message = new MessageMaster((new MessageBuilder)->$fn($address)->getMessage());
			$this->assertEquals($address, $message->getHeader($name)->getValue());
		}
	}

	public function testIds()
	{
		$names = [
			'message-id',
			'resent-message-id',
			'in-reply-to',
			'references',
		];

		foreach($names as $name) {
			$fn = $this->toFn($name);

			// Default
			try {
				(new MessageBuilder)->$fn()->getMessage();
			} catch (TypeError $e) {
			} finally {
				$this->assertTrue(isset($e));
				unset($e);
			}

			// Single Id
			$ele = new MessageId('E=MC^2', 'uzh.ch');
			$message = new MessageMaster((new MessageBuilder)->$fn($ele)->getMessage());
			$this->assertSame($ele, $message->getHeader($name)->getValue()[0]);

			// Single keyword string
			$ele = 'E=MC^2@uzh.ch';
			$message = new MessageMaster((new MessageBuilder)->$fn($ele)->getMessage());
			$this->assertEquals(
				new MessageId('E=MC^2', 'uzh.ch'),
				$message->getHeader($name)->getValue()[0]
			);

			// Multiple Ids
			$eles = [
				new MessageId('E=MC^2', 'uzh.ch'),
				new MessageId('non-euclidean-geometry', 'ethz.ch'),
			];
			$message = new MessageMaster((new MessageBuilder)->$fn($eles)->getMessage());
			$this->assertEquals($eles, $message->getHeader($name)->getValue());
		}
	}

	public function testKeyword()
	{
		$names = [
			'keywords',
		];

		foreach($names as $name) {
			$fn = $this->toFn($name);

			// Default
			try {
				(new MessageBuilder)->$fn()->getMessage();
			} catch (TypeError $e) {
			} finally {
				$this->assertTrue(isset($e));
				unset($e);
			}

			// Single Keyword
			$ele = new Keyword('energy');
			$message = new MessageMaster((new MessageBuilder)->$fn($ele)->getMessage());
			$this->assertSame($ele, $message->getHeader($name)->getValue()[0]);

			// Single keyword string
			$ele = 'energy';
			$message = new MessageMaster((new MessageBuilder)->$fn($ele)->getMessage());
			$this->assertEquals(
				new Keyword($ele),
				$message->getHeader($name)->getValue()[0]
			);

			// Multiple Keywords
			$eles = [
				new Keyword('geometry'),
				new Keyword('non-euclidean-geometry')
			];
			$message = new MessageMaster((new MessageBuilder)->$fn($eles)->getMessage());
			$this->assertEquals($eles, $message->getHeader($name)->getValue());
		}
	}

	public function testUnstructured()
	{
		$names = [
			'subject',
			'comments',
			'x-signature',
		];

		foreach($names as $name) {
			$fn = $this->toFn($name);

			// Default
			try {
				(new MessageBuilder)->$fn()->getMessage();
			} catch (TypeError $e) {
			} finally {
				$this->assertTrue(isset($e));
				unset($e);
			}

			// Single
			$ele = 'Eureka!';
			$message = new MessageMaster((new MessageBuilder)->$fn($ele)->getMessage());
			$this->assertSame($ele, $message->getHeader($name)->getValue()[0]);

			// Multiple
			$eles = [
				'Eureka!',
				'Relativity',
			];
			$message = new MessageMaster((new MessageBuilder)->$fn($eles)->getMessage());
			$this->assertEquals($eles, $message->getHeader($name)->getValue());
		}
	}

	public function testHtml()
	{
		$html = '<h1>42</h1>';
		$raw = (new MessageBuilder)
			->html($html)
			->getMessage()
		;

		$message = new MessageMaster($raw);
		$this->assertEquals(
			new Text($html),
			$message->getBodies()[0]
		);
	}

	public function testAttach()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test-');
		file_put_contents($path, '---');

		$raw = (new MessageBuilder)
			->attach($path)
			->getMessage()
		;

		$message = new MessageMaster($raw);

		$this->assertEquals(
			new File($path),
			$message->getBodies()[0],
		);

		unlink($path);
	}

	public function testBoundaries()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test-');
		file_put_contents($path, '---');

		$boundaries = [
			'123',
			'456',
		];

		$raw = (new MessageBuilder)
			->text('42')
			->html('<h1>42</h1>')
			->attach($path)
			->boundary(...$boundaries)
			->getMessage()
		;

		$message = new MessageMaster($raw);

		$parts = [
			$message,
			$message->getSubparts()[0],
		];

		foreach ($parts as $id => $part) {
			$this->assertEquals(
				$boundaries[$id],
				$part->getContentType()->getParam('boundary'),
				"Boundary $id should equal {$boundaries[$id]}"
			);
		}

		unlink($path);
	}
}