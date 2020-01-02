<?php

namespace Virtu\Mime\Element;

use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\GroupInterface;

use ArrayIterator;
use IteratorAggregate;

class Group implements IteratorAggregate, GroupInterface
{
	private $name;
	private $mailboxes;

	/**
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

	public function getIterator()
	{
		yield from $this->mailboxes;
	}
}
