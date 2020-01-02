<?php

namespace Virtu\Mime\Spec\Body;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Body\Part;
use Traversable;

class PartTest extends TestCase
{
	public function testIterable()
	{
		$iter = [ 'x' => 42, 'y' => 11, 'z' => new Part() ];

		// Traversable?
		$part = new Part($iter);
		$this->assertInstanceOf(Traversable::class, $part);

		// Preserves keys?
		foreach ($part as $key => $val) {
			$this->assertEquals($iter[$key], $val);
		}
	}

	public function testChildren()
	{
		$iter = [1 => 0, 2 => 'x', 3 => new Part()];
		$part = new Part($iter);
		$this->assertSame($iter, $part->getChildren());
	}
}
