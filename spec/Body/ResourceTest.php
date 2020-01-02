<?php

namespace Virtu\Mime\Spec\Body;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Body\Resource;
use TypeError;

class ResourceTest extends TestCase
{
	public function testResource()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test');
		$data = random_bytes(64);
		file_put_contents($path, $data);
		$resource = fopen($path, 'r');

		$body = new Resource($resource);
		$this->assertIsResource($body->getResource());
		$this->assertSame($resource, $body->getResource());

		unlink($path);
	}

	public function testIterable()
	{
		$resource = fopen('php://temp', 'rw');
		$bufLength = 1;
		$iters = 5;

		$data = '12345';
		fwrite($resource, $data);

		$body = new Resource($resource);
		$this->assertEmpty($body->setBufferLength($bufLength));
		$this->assertEquals($bufLength, $body->getBufferLength());

		$result = '';
		$count = 0;
		foreach ($body as $key => $chunk) {
			$count++;
			$result .= $chunk;
		}

		$this->assertEquals($iters, $count);
		$this->assertEquals($data, $result);

		$this->assertEmpty($body->rewind());
		for ($i = 0; $i < $iters; $i++) {
			$this->assertTrue($body->valid());
			$this->assertNotEmpty($body->current());
			$this->assertEquals($i, $body->key());
			$this->assertEmpty($body->next());
		}
		$this->assertFalse($body->valid());
		$this->assertEmpty($body->rewind());

		fclose($resource);
	}

	public function testClose()
	{
		$resource = fopen('php://temp', 'rw');
		$data = random_bytes(64);
		fwrite($resource, $data);

		$body = new Resource($resource);
		$this->assertIsResource($body->getResource());
		$this->assertSame($resource, $body->getResource());
		$this->assertEmpty($body->close());
		$this->assertFalse(is_resource($body->getResource()));
		$this->assertEmpty($body->close(), 'can close multiple times');
		$this->assertEmpty($body->rewind(), 'can rewind a closed resource');
	}

	public function testInvalidResource()
	{
		$this->expectException(TypeError::class);
		new Resource();
	}

	public function testInvalidResourceAgain()
	{
		$this->expectException(TypeError::class);
		new Resource('test');
	}
}
