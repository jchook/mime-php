<?php

namespace Virtu\Mime\Element;

interface MediaTypeInterface extends ElementInterface
{
	public function getType(): string;
	public function getSubtype(): string;
}
