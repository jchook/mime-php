<?php

namespace Virtu\Mime\Body;

use IteratorAggregate;
use Traversable;

class Part implements IteratorAggregate, PartInterface
{
	/**
	 * @param iterable<HeaderInterface|PartInterface|BodyInterface>
	 */
	private $children;

	/**
	 * @throws RuntimeException
	 */
	public function __construct(iterable $children = [])
	{
		$this->children = $children;
	}

	public function getChildren(): iterable
	{
		return $this->children;
	}

	public function getIterator(): Traversable
	{
		yield from $this->children;
	}
}
