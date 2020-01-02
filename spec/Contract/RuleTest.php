<?php

namespace Virtu\Mime\Spec\Contract;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Contract\Rule;

use TypeError;

/**
 * @covers Virtu\Mime\Contract\Rule
 */
class RuleTest extends TestCase
{
	public function testGetters()
	{
		$name = 'test';
		$level = Rule::ERROR;
		$config = ['test' => 'ing'];
		$rule = new Rule($name, $level, $config);

		$this->assertSame($name, $rule->getName());
		$this->assertSame($level, $rule->getLevel());
		$this->assertSame($config['test'], $rule->get('test'));
	}
}
