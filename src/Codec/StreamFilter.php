<?php

namespace Virtu\Mime\Codec;

use RuntimeException;
use TypeError;

use const STREAM_FILTER_READ;

abstract class StreamFilter
{
	protected $bufferSize = 8192;
	protected $lineLength = 76;

	abstract protected function getEncodeFilterName(): string;
	abstract protected function getDecodeFilterName(): string;

	/**
	 * Encode an entire stream of data.
	 *
	 * Each iteration will decode $bufferSize bytes of encoded data. The buffers
	 * emitted from this function will likely fall short of $bufferSize as base64
	 * inflates data during the encoding process.
	 */
	public function decodeStream($input): iterable
	{
		$filterName = $this->getDecodeFilterName();
		$filter = $this->filterAppend($input, $filterName);
		$rewind = stream_get_meta_data($input)['seekable'] ?? false;
		$rewind && rewind($input);
		while (!feof($input)) {
			yield fread($input, $this->bufferSize);
		}
		$this->filterRemove($filter);
		$rewind && rewind($input);
	}

	/**
	 * Encode an entire stream of data.
	 *
	 * Each iteration encodes $bufferSize bytes of data, but may (will?) emit
	 * chunks larger than $bufferSize as base64 inflates the encoded data size.
	 */
	public function encodeStream($input): iterable
	{
		$filterName = $this->getEncodeFilterName();
		$filter = $this->filterAppend($input, $filterName);
		$rewind = stream_get_meta_data($input)['seekable'] ?? false;
		$rewind && rewind($input);
		while (!feof($input)) {
			yield fread($input, $this->bufferSize);
		}
		$this->filterRemove($filter);
		$rewind && rewind($input);
	}

	/**
	 * Safely append a filter to a stream
	 */
	private function filterAppend($input, string $filterName)
	{
		if (!is_resource($input)) {
			throw new TypeError(
				__METHOD__ . ' expected a resource but received ' . gettype($input)
			);
		}

		$filter = stream_filter_append(
			$input, $filterName, STREAM_FILTER_READ, [
				'line-length' => $this->lineLength,
				'line-break-chars' => "\r\n",
			]
		);

		// @codeCoverageIgnoreStart
		if (!is_resource($filter)) {
			throw new RuntimeException(
				'Unable to add a base64 filter to the input stream.'
			);
		}
		// @codeCoverageIgnoreEnd

		return $filter;
	}

	/**
	 * Remove a filter from a stream
	 */
	private function filterRemove($filter): bool
	{
		return stream_filter_remove($filter);
	}
}
