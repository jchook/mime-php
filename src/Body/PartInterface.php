<?php

namespace Virtu\Mime\Body;

/**
 * All parts are bodies but not all bodies are parts.
 */
interface PartInterface extends BodyInterface
{
	public function getChildren(): iterable;
}
