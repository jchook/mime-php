<?php

namespace Virtu\Mime\Body;

use ArrayIterator;
use IteratorAggregate;
use TypeError;
use Traversable;

class Text implements IteratorAggregate, BodyInterface
{
	/**
	 * @var Traversable
	 */
	private $text;

	/**
	 * @param string|string[] $text
	 */
	public function __construct($text)
	{
		if (is_iterable($text)) {
			$this->text = $text;
		} elseif (is_string($text)) {
			$this->text = [$text];
		} else {
			throw new TypeError(
				__METHOD__ . ' expected string or iterable but received '
					. gettype($text)
			);
		}
	}

	function getIterator(): Traversable
	{
		yield from $this->text;
	}
}
