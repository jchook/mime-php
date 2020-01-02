<?php

namespace Virtu\Mime\Element;

class Comment implements CommentInterface
{
	private $comment;

	public function __construct(string $comment)
	{
		$this->comment = $comment;
	}

	public function getComment()
	{
		return $this->comment;
	}
}
