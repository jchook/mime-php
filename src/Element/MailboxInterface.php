<?php

namespace Virtu\Mime\Element;

interface MailboxInterface extends ElementInterface
{
	public function getName(): string;
	public function getLocalPart(): string;
	public function getDomain(): string;
}
