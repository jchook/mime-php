<?php declare(strict_types=1);

namespace Virtu\Mime\Element;

class Version implements VersionInterface
{
	private $major;
	private $minor;

	const DEFAULT_MAJOR = 1;
	const DEFAULT_MINOR = 0;

	public function __construct(
		int $major = self::DEFAULT_MAJOR,
		int $minor = self::DEFAULT_MINOR
	) {
		$this->major = $major;
		$this->minor = $minor;
	}

	public function getMajor(): int
	{
		return $this->major;
	}

	public function getMinor(): int
	{
		return $this->minor;
	}

}