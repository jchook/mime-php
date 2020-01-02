<?php

namespace Virtu\Mime\Codec;

use RuntimeException;
use STREAM_FILTER_READ;
use TypeError;

class QuotedPrintable extends StreamFilter implements CodecInterface
{
	protected function getEncodeFilterName(): string
	{
		return 'convert.quoted-printable-encode';
	}

	protected function getDecodeFilterName(): string
	{
		return 'convert.quoted-printable-decode';
	}

	/**
	 * Encode a string with QP
	 */
	public function encodeString(string $input): string
	{
		// For some reason the stream encoder has better standards compliance.
		$stream = fopen('php://temp', 'rw');
		fwrite($stream, $input);
		$enc = implode('', iterator_to_array($this->encodeStream($stream)));
		fclose($stream);
		return $enc;

		// PHP Bug:
		// This returns lines that are too long sometimes
		// return quoted_printable_encode($input);
	}
}
