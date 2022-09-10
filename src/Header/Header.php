<?php

namespace Virtu\Mime\Header;

use Virtu\Mime\Element\ElementInterface;

use TypeError;
use IteratorAggregate;
use Traversable;

class Header implements IteratorAggregate, HeaderInterface
{
	/**
	 * Can be null because the correct default charset depends on context:
	 *   1. Does this header live within a message/global? utf-8.
	 *   2. Otherwise, us-ascii.
	 * @var ?string
	 */
	private $charset;

	/**
	 * @var iterable<string|ElementInterface>
	 */
	private $value;

	/**
	 * @var string
	 */
	private $name;

	public function __construct(string $name, $value, ?string $charset = null)
	{
		$this->setName($name);
		$this->setValue($value);
		if ($charset) {
			$this->setCharset($charset);
		}
	}

	public function getCharset(): ?string
	{
		return $this->charset;
	}

	public function getIterator(): Traversable
	{
		yield from $this->value;
	}

	public function getValue(): iterable
	{
		return $this->value;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function hasName(string $name): bool
	{
		return strcasecmp($this->getName(), $name) === 0;
	}

	public function setCharset(string $charset): void
	{
		$this->charset = $charset;
	}

	public function setValue($value): void
	{
		if (is_string($value) || ($value instanceof ElementInterface)) {
			$this->value = [$value];
		} elseif (is_iterable($value)) {
			$this->value = $value;
		} else {
			throw new TypeError(
				'Expected either iterable, string, or ElementInterface but received '
					. (is_object($value) ? get_class($value) : gettype($value))
			);
		}
	}
}
