<?php

namespace Virtu\Mime\Element;

use Virtu\Mime\MailboxInterface;
use Traversable;

interface GroupInterface extends Traversable, ElementInterface
{
	/**
	 * @return MailboxInterface[]
	 */
	public function getMailboxes(): iterable;
	public function getName(): string;

}