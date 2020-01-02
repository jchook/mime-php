<?php

namespace Virtu\Mime\Header;

interface ParameterizedHeaderInterface extends HeaderInterface
{
	public function getParams(): array;
	public function getParam($key);
	public function setParams(array $params): void;
	public function setParam($key, $value): void;
}