<?php

namespace Virtu\Mime\Element;

interface MessageIdInterface extends ElementInterface
{
	public function getIdLeft(): string;
	public function getIdRight(): string;
}