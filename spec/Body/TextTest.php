<?php

namespace Virtu\Mime\Spec\Body;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Body\Text;
use Traversable;
use TypeError;

class TextTest extends TestCase
{
	public function testIterable()
	{
		$iter = [ 'x' => 'ex', 'y' => 'why' ];

		// Traversable?
		$text = new Text($iter);
		$this->assertInstanceOf(Traversable::class, $text);

		// Preserves keys?
		foreach ($text as $key => $val) {
			$this->assertEquals($iter[$key], $val);
		}

		// Direct iterable access
		$count = 0;
		$iterable = $text->getIterator();
		foreach ($iterable as $key => $val) {
			$count++;
			$this->assertEquals($iter[$key], $val);
		}
		$this->assertEquals(count($iter), $count);
	}

	public function testStringConstruct()
	{
		$str = 'test';
		$text = new Text($str);
		foreach ($text as $test) {
			$this->assertEquals($str, $test);
		}
	}

	public function testInvalidInput()
	{
		$this->expectException(TypeError::class);
		$text = new Text(5);
	}
}
