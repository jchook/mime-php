<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Element\DateTimeInterface;
use Virtu\Mime\Element\ElementInterface;

use TypeError;
use DateTimeInterface as RealDateTimeInterface;

/**
 * @covers Virtu\Mime\Element\DateTimeImmutable
 */
class DateTimeImmutableTest extends TestCase
{
	public function testInterface()
	{
		$ele = new DateTimeImmutable();
		$this->assertInstanceOf(RealDateTimeInterface::class, $ele);
		$this->assertInstanceOf(DateTimeInterface::class, $ele);
		$this->assertInstanceOf(ElementInterface::class, $ele);
	}
}
