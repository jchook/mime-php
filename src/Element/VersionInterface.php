<?php

namespace Virtu\Mime\Element;

interface VersionInterface extends ElementInterface
{
	public function getMajor(): int;
	public function getMinor(): int;
}