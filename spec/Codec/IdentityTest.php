<?php

namespace Virtu\Mime\Spec\Codec;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Codec\Identity;

use TypeError;

class IdentityTest extends TestCase
{
	public function testEncodeString()
	{
		$codec = new Identity();
		$str = random_bytes(1024);
		$enc = $codec->encodeString($str);
		$this->assertEquals($str, $enc, 'identity encoding');
	}

	public function testDecodeStream()
	{
		// Test the B64 codec
		$codec = new Identity();

		// Prep the stream
		$stream = fopen('php://temp', 'rw');
		$bufLength = 8192;
		$iters = 5;
		for ($ii = 0; $ii < $iters; $ii++) {
			fwrite($stream, random_bytes($bufLength));
		}

		// NO rewind here, as the decoder *should* do that and we test that here.

		// Do the encoding and make sure it succeeded in some way
		$dec = iterator_to_array($codec->decodeStream($stream));
		$this->assertNotEmpty($dec, 'decoding worked');

		// Convert the buffers to a single string for testing purposes
		$dec = implode('', $dec);

		// Get the actual source text
		rewind($stream);
		$str = stream_get_contents($stream);

		// Check that the string was properly decoded
		$this->assertEquals($str, $dec, 'properly decoded');
	}

	public function testEncodeStream()
	{
		// Test the B64 codec
		$codec = new Identity();

		// Prep the stream
		$stream = fopen('php://temp', 'rw');
		$bufLength = 8192;
		$iters = 5;
		for ($ii = 0; $ii < $iters; $ii++) {
			fwrite($stream, random_bytes($bufLength));
		}

		// NO rewind here, as the encoder *should* do that and we test that here.

		// Do the encoding and make sure it succeeded in some way
		$enc = iterator_to_array($codec->encodeStream($stream));
		$this->assertNotEmpty($enc, 'encoding worked');

		// Convert the buffers to a single string for testing purposes
		$enc = implode('', $enc);

		// Get the actual source text
		rewind($stream);
		$str = stream_get_contents($stream);

		// Check that the string was properly encoded
		$this->assertEquals($str, $enc, 'properly encoded');
	}

	public function testInvalidStringInput()
	{
		$this->expectException(TypeError::class);
		$codec = new Identity();
		$codec->encodeString();
	}

	public function testInvalidStreamInput()
	{
		$this->expectException(TypeError::class);
		$codec = new Identity();
		iterator_to_array($codec->encodeStream(null));
	}
}
