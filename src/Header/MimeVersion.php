<?php

namespace Virtu\Mime\Header;

use Virtu\Mime\Element\Version;

class MimeVersion extends Header
{
	const DEFAULT_NAME = 'MIME-Version';

	public function __construct(
		int $major = Version::DEFAULT_MAJOR,
		int $minor = Version::DEFAULT_MINOR
	) {
		$this->setName(self::DEFAULT_NAME);
		$this->setValue([new Version($major, $minor)]);
	}

	public function getVersion(): Version
	{
		return $this->getValue()[0];
	}

	public function getMajor(): int
	{
		return $this->getVersion()->getMajor();
	}

	public function getMinor(): int
	{
		return $this->getVersion()->getMinor();
	}
}