<?php

namespace Virtu\Mime\Header;

class ContentTransferEncoding extends Header
{
	const ENCODING_7BIT = '7bit';
	const ENCODING_8BIT = '8bit';
	const ENCODING_BINARY = 'binary';
	const ENCODING_BASE64 = 'base64';
	const ENCODING_QP = 'quoted-printable';

	private $encoding = self::ENCODING_7BIT;

	public function __construct(?string $encoding = null)
	{
		$this->setName('Content-Transfer-Encoding');
		$this->setValue([$encoding ?: '7bit']);
	}

	public function getEncoding(): string
	{
		return $this->getValue()[0];
	}
}
