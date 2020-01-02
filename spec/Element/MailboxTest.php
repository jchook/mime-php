<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\ElementInterface;

use TypeError;

/**
 * @covers Virtu\Mime\Element\Mailbox
 */
class MailboxTest extends TestCase
{
	public function testInterface()
	{
		$ele = new Mailbox('Bertrand Russell', 'brussell', 'trin.cam.ac.uk');
		$this->assertInstanceOf(MailboxInterface::class, $ele);
		$this->assertInstanceOf(ElementInterface::class, $ele);
	}

	public function testGetterSetter()
	{
		$name = 'Bertrand Russell';
		$localPart = 'brussell';
		$domain = 'trin.cam.ac.uk';
		$ele = new Mailbox($name, $localPart, $domain);
		$this->assertSame($name, $ele->getName());
		$this->assertSame($localPart, $ele->getLocalPart());
		$this->assertSame($domain, $ele->getDomain());

		$name = 'Alfred North Whitehead';
		$localPart = 'awhitehead';
		$domain = 'harvard.edu';

		$this->assertEmpty($ele->setName($name));
		$this->assertEmpty($ele->setLocalPart($localPart));
		$this->assertEmpty($ele->setDomain($domain));
		$this->assertSame($name, $ele->getName());
		$this->assertSame($localPart, $ele->getLocalPart());
		$this->assertSame($domain, $ele->getDomain());
	}

	public function testInvalidInput()
	{
		$this->expectException(TypeError::class);
		new Mailbox();
	}
}
