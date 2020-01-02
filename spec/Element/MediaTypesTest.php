<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\MediaType;

class MediaTypesTest extends TestCase
{
	public function testExtensionTypes()
	{
		$path = MediaType::getFileExtensionTypesPath();
		$this->assertFileExists($path);
		$types = include($path);
		$this->assertNotEmpty($types);
	}
}