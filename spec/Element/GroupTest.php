<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\ElementInterface;
use Virtu\Mime\Element\Group;
use Virtu\Mime\Element\Mailbox;

use TypeError;
use Traversable;

/**
 * @covers Virtu\Mime\Element\Group
 */
class GroupTest extends TestCase
{
	public function testInterface()
	{
		$ele = new Group('test');
		$this->assertInstanceOf(ElementInterface::class, $ele);
		$this->assertInstanceOf(Traversable::class, $ele);
	}

	public function testName()
	{
		$name = 'group-name';
		$ele = new Group($name);
		$this->assertSame($name, $ele->getName());
	}

	public function testMailboxes()
	{
		$name = 'group-name';
		$mailboxes = [new Mailbox('Santa Claus', 'santa', 'north.pole')];
		$ele = new Group($name, $mailboxes);
		$this->assertSame($mailboxes, $ele->getMailboxes());

		// Iterable
		foreach ($ele as $subEle) {
			$final[] = $subEle;
		}
		$this->assertEquals($mailboxes, $final);
	}

	public function testInvalidInputName()
	{
		$this->expectException(TypeError::class);
		new Group();
	}

	public function testInvalidInputMailboxes()
	{
		$this->expectException(TypeError::class);
		new Group('name', 'oops');
	}
}
