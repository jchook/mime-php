<?php

namespace Virtu\Mime\Codec;

interface CodecInterface
{
	/**
	 * @param resource $input
	 */
	public function decodeStream($input): iterable;

	/**
	 * @param resource $input
	 */
	public function encodeStream($input): iterable;

	/**
	 * @param resource $input
	 */
	public function encodeString(string $input): string;
}
