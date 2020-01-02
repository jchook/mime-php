<?php

namespace Virtu\Mime\Spec\Body;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Body\File;

use RuntimeException;

class FileTest extends TestCase
{
	public function testResource()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test-');
		$data = random_bytes(64);
		file_put_contents($path, $data);

		$file = new File($path);
		$resource = $file->getResource();
		$this->assertIsResource($resource);
		$result = stream_get_contents($resource);

		$this->assertEquals($data, $result);
		$this->assertEquals($path, $file->getPath());
		$this->assertEquals(basename($path), $file->getName());

		unlink($path);
	}

	public function testIterable()
	{
		$path = '/tmp/php-mime-test.txt';
		$bufLength = 1024;
		$iters = 3;

		$data = random_bytes($bufLength * $iters);
		file_put_contents($path, $data);

		$file = new File($path);
		$this->assertEmpty($file->setBufferLength($bufLength));
		$this->assertEquals($bufLength, $file->getBufferLength());

		$result = '';
		$count = 0;
		foreach ($file as $chunk) {
			$count++;
			$result .= $chunk;
		}

		$this->assertEmpty($file->rewind());
		$this->assertTrue($file->valid());
		for ($i = 0; $i < $iters; $i++) {
			$this->assertEquals($i, $file->key());
			$this->assertTrue(is_string($file->current()));
			$this->assertEmpty($file->next());
		}
		$this->assertFalse($file->valid());
		$this->assertEmpty($file->close());
		$this->assertEquals($iters, $count);
		$this->assertEquals($data, $result);
	}

	public function testInvalidFile()
	{
		$path = sys_get_temp_dir() . '/mime-test-nonexistant.txt';
		$file = new File($path);
		$this->expectException(RuntimeException::class);
		$file->valid();
	}
}
