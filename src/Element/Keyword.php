<?php

namespace Virtu\Mime\Element;

class Keyword implements KeywordInterface
{
	private $keyword;

	public function __construct(string $keyword)
	{
		$this->keyword = $keyword;
	}

	public function getKeyword(): string
	{
		return $this->keyword;
	}
}
