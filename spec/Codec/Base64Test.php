<?php

namespace Virtu\Mime\Spec\Codec;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Codec\Base64;

use TypeError;

class Base64Test extends TestCase
{
	public function testEncodeString()
	{
		$codec = new Base64();
		$str = random_bytes(1024);
		$enc = $codec->encodeString($str);
		$max = max(array_map('strlen', explode("\r\n", $enc)));
		$this->assertLessThanOrEqual(76, $max);
		$this->assertEquals(
			$str,
			base64_decode($enc)
		);
	}

	public function testDecodeWithoutNewLine()
	{
		$str = 'test';
		$enc = base64_encode($str);
		$temp = fopen('php://temp', 'rw');
		fwrite($temp, $enc);
		rewind($temp);
		$codec = new Base64();
		$dec = $codec->decodeStream($temp);
		$final = '';
		foreach ($dec as $decPart) {
			$final .= $decPart;
		}
		$this->assertEquals($str, $final);
	}

	public function testDecodeStream()
	{
		$codec = new Base64();

		// Prep arbitrary binary data
		$dataString = '';
		$dataStream = fopen('php://temp', 'rw');
		$bufLength = 8192;
		$iters = 5;
		for ($ii = 0; $ii < $iters; $ii++) {
			$dataString .= random_bytes($bufLength);
		}
		fwrite($dataStream, $dataString);
		rewind($dataStream);

		// Encode data
		$enc = $codec->encodeStream($dataStream);
		$encStream = fopen('php://temp', 'rw');
		foreach ($enc as $chunk) {
			fwrite($encStream, $chunk);
		}

		// Decode data
		$dec = $codec->decodeStream($encStream);
		$decString = '';
		foreach ($dec as $chunk) {
			$decString .= $chunk;
		}

		$this->assertEquals($dataString, $decString);
	}

	public function testEncodeStream()
	{
		// Test the B64 codec
		$codec = new Base64();

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
		$dec = base64_decode($enc);
		$this->assertEquals($dec, $str, 'properly encoded');

		// Check that the correct number of line-endings has been inserted
		$len = strlen(base64_encode($str));
		$nls = floor($len / 76);
		$this->assertEquals($len + ($nls * 2), strlen($enc));

		// Check the max line length (including \r\n)
		$max = max(array_map('strlen', explode("\r\n", $enc)));
		$this->assertEquals(76, $max);
	}

	public function testInvalidStringInput()
	{
		$this->expectException(TypeError::class);
		$codec = new Base64();
		$codec->encodeString();
	}

	public function testInvalidStreamInput()
	{
		$codec = new Base64();
		$this->expectException(TypeError::class);
		iterator_to_array($codec->encodeStream('test'));
	}
}
