<?php

namespace Virtu\Mime\Element;

class Mailbox implements MailboxInterface
{
	private $name;
	private $localPart;
	private $domain;

	public function __construct(string $name, string $localPart, string $domain)
	{
		$this->name = $name;
		$this->localPart = $localPart;
		$this->domain = $domain;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getLocalPart(): string
	{
		return $this->localPart;
	}

	public function getDomain(): string
	{
		return $this->domain;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function setLocalPart(string $localPart): void
	{
		$this->localPart = $localPart;
	}

	public function setDomain(string $domain): void
	{
		$this->domain = $domain;
	}

	public static function split(string $mailbox): array
	{
		$lastAt = strrpos($mailbox, '@');
		return [substr($mailbox, 0, $lastAt), substr($mailbox, $lastAt + 1)];
	}

}