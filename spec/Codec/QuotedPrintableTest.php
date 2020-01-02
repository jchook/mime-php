<?php

namespace Virtu\Mime\Spec\Codec;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Codec\QuotedPrintable;

use TypeError;

class QuotedPrintableTest extends TestCase
{
	public function testDecodeStream()
	{
		$codec = new QuotedPrintable();

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


	public function testEncodeString()
	{
		$codec = new QuotedPrintable();
		$str = random_bytes(2046);
		$enc = $codec->encodeString($str);
		$max = max(array_map('strlen', explode("\r\n", $enc)));

		// RFC 2045 § 6.7 #3
		$lines = explode("\r\n", $enc);
		foreach ($lines as $line) {
			$this->assertFalse(
				in_array(substr($line, -1), ["\t", ' ']),
				'lines may not end in SPACE or HT'
			);
		}

		// DEBUG
		// Ran into this with quoted_printable_encode()
		// Unable to find a bugs.php.net report about the issue.
		if ($max > 76) {
			foreach ($lines as $line) {
				$len = strlen($line);
				if ($len > 76) {
					echo "\n\nLINE {$len} CHARS:\n{$line}\n\n";
				}
			}
			file_put_contents('/tmp/quoted-printable-broken.txt', $str);
			file_put_contents('/tmp/quoted-printable-broken.txt.enc', $enc);
		}

		$this->assertLessThanOrEqual(76, $max);
		$this->assertEquals(
			$str,
			quoted_printable_decode($enc)
		);
	}

	public function testEncodeStream()
	{
		// Test the B64 codec
		$codec = new QuotedPrintable();

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
		$dec = quoted_printable_decode($enc);
		$this->assertEquals($dec, $str, 'properly encoded');

		// Check the max line length (including \r\n)
		$max = max(array_map('strlen', explode("\r\n", $enc)));
		$this->assertEquals(76, $max);
	}

	public function testInvalidStringInput()
	{
		$this->expectException(TypeError::class);
		$codec = new QuotedPrintable();
		$codec->encodeString(null);
	}

	public function testInvalidStreamInput()
	{
		$this->expectException(TypeError::class);
		$codec = new QuotedPrintable();
		iterator_to_array($codec->encodeStream(null));
	}
}
