<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\Comment;
use Virtu\Mime\Element\CommentInterface;
use Virtu\Mime\Element\ElementInterface;

use TypeError;

/**
 * @covers Virtu\Mime\Element\Comment
 */
class CommentTest extends TestCase
{
	public function testInterface()
	{
		$ele = new Comment('test');
		$this->assertInstanceOf(CommentInterface::class, $ele);
		$this->assertInstanceOf(ElementInterface::class, $ele);
	}

	public function testGetter()
	{
		$str = 'test';
		$ele = new Comment($str);
		$this->assertSame($str, $ele->getComment());
	}

	public function testInvalidInput()
	{
		$this->expectException(TypeError::class);
		new Comment();
	}
}
