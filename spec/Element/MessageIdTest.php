<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\MessageId;
use Virtu\Mime\Element\MessageIdInterface;
use Virtu\Mime\Element\ElementInterface;

use TypeError;

/**
 * @covers Virtu\Mime\Element\MessageId
 */
class MessageIdTest extends TestCase
{
	public function testInterface()
	{
		$ele = new MessageId('Bertrand Russell', 'brussell', 'trin.cam.ac.uk');
		$this->assertInstanceOf(MessageIdInterface::class, $ele);
		$this->assertInstanceOf(ElementInterface::class, $ele);
	}

	public function testGetters()
	{
		$idLeft = 'anything';
		$idRight = 'trin.cam.ac.uk';
		$ele = new MessageId($idLeft, $idRight);
		$this->assertSame($idLeft, $ele->getIdLeft());
		$this->assertSame($idRight, $ele->getIdRight());
	}

	public function testInvalidInput()
	{
		$this->expectException(TypeError::class);
		new MessageId();
	}
}
