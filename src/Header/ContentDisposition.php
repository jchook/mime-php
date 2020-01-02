<?php

namespace Virtu\Mime\Header;

class ContentDisposition extends ParameterizedHeader
{
	public function __construct(string $type, array $params = [])
	{
		$this->setName('Content-Disposition');
		$this->setValue($type);
		$this->setParams($params);
	}
}