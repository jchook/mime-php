<?php

namespace Virtu\Mime\Header;

class ParameterizedHeader extends Header implements ParameterizedHeaderInterface
{
	private $params = [];

	public function __construct(
		string $name,
		$value,
		array $params = [],
		?string $charset = null
	) {
		parent::__construct($name, $value, $charset);
		$this->setParams($params);
	}

	public function getParam($key)
	{
		return $this->params[$key] ?? null;
	}

	public function getParams(): array
	{
		return $this->params;
	}

	public function setParam($key, $value): void
	{
		$this->params[$key] = $value;
	}

	public function setParams(array $params): void
	{
		$this->params = $params;
	}
}