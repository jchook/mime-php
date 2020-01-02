<?php

namespace Virtu\Mime\Contract;

class Rule
{
	const ERROR = 2;
	const WARN = 1;
	const NONE = 0;

	private $name;
	private $level;
	private $config = [];

	public function __construct(string $name, int $level, array $config = [])
	{
		$this->name = $name;
		$this->level = $level;
		$this->config = $config;
	}

	public function get($key)
	{
		return $this->config[$key] ?? null;
	}

	public function getName()
	{
		return $this->name;
	}

	public function getLevel()
	{
		return $this->level;
	}
}