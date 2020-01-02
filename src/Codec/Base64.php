<?php

namespace Virtu\Mime\Codec;

use RuntimeException;
use STREAM_FILTER_READ;
use TypeError;

class Base64 extends StreamFilter implements CodecInterface
{
	protected function getEncodeFilterName(): string
	{
		return 'convert.base64-encode';
	}

	protected function getDecodeFilterName(): string
	{
		return 'convert.base64-decode';
	}

	/**
	 * Encode a single stream contained in RAM
	 */
	public function encodeString(string $input): string
	{
		return chunk_split(base64_encode($input), $this->lineLength, "\r\n");
	}
}
