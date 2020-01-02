<?php

namespace Virtu\Mime\Codec;

use Generator;
use TypeError;

class Identity implements CodecInterface
{
	private $bufferLength = 8192;

	public function decodeStream($input): Generator
	{
		return $this->encodeStream($input);
	}

	/**
	 * @param resource $input
	 */
	public function encodeStream($input): Generator
	{
		if (!is_resource($input)) {
			throw new TypeError(
				__METHOD__ . ' expected a resource but received ' . gettype($input)
			);
		}

		$rewind = stream_get_meta_data($input)['seekable'] ?? false;
		$rewind && rewind($input);

		while (is_resource($input) && !feof($input)) {
			$buf = fread($input, $this->bufferLength);
			if ($buf === false) break;
			yield $buf;
		}
	}

	public function encodeString(string $input): string
	{
		return $input;
	}
}