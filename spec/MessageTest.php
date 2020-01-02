<?php

namespace Virtu\Mime\Spec;

use Virtu\Mime\Message;
use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Body\BodyInterface;

/**
 * @covers Virtu\Mime\Message
 */
class MessageTest extends TestCase
{
	public function testInstanceOfPartInterface()
	{
		$message = new Message();
		$this->assertInstanceOf(BodyInterface::class, $message);
		$this->assertInstanceOf(PartInterface::class, $message);
	}
}