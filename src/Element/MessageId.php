<?php

namespace Virtu\Mime\Element;

class MessageId implements MessageIdInterface
{
	private $left;
	private $right;

	public function __construct(string $left, string $right)
	{
		$this->left = $left;
		$this->right = $right;
	}

	public function getIdLeft(): string
	{
		return $this->left;
	}

	public function getIdRight(): string
	{
		return $this->right;
	}

}