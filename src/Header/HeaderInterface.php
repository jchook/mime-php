<?php

namespace Virtu\Mime\Header;

use Traversable;

interface HeaderInterface extends Traversable
{
	public function getCharset(): ?string;
	public function getName(): string;
	public function hasName(string $name): bool;
	public function getValue(): iterable;
	public function setName(string $name): void;
	public function setValue($value): void;
}
