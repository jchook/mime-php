<?php

namespace Virtu\Mime\Body;

use Iterator;
use TypeError;

interface FileInterface extends BodyStreamInterface
{
	public function getPath(): string;
	public function getName(): string;
}