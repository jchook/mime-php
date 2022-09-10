<?php

namespace Virtu\Mime\Element;

use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\GroupInterface;

use IteratorAggregate;
use Traversable;

class Group implements IteratorAggregate, GroupInterface
{
	private $name;
	private $mailboxes;

	/**
	 * @param string $name
	 * @param MailboxInterface[] $mailboxes
	 */
	public function __construct(string $name, iterable $mailboxes = [])
	{
		$this->name = $name;
		$this->mailboxes = $mailboxes;
	}

	/**
	 * @return MailboxInterface[]
	 */
	public function getMailboxes(): iterable
	{
		return $this->mailboxes;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getIterator(): Traversable
	{
		yield from $this->mailboxes;
	}
}
