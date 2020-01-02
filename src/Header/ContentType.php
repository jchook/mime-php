<?php declare(strict_types=1);

namespace Virtu\Mime\Header;

use Virtu\Mime\Element\MediaType;

use InvalidArgumentException;

class ContentType extends ParameterizedHeader
{
	const DEFAULT_NAME = 'Content-Type';

	public function __construct(
		string $type = null,
		string $subtype = null,
		array $params = [],
		?string $charset = null
	) {
		$this->setName(self::DEFAULT_NAME);
		$this->setValue([new MediaType($type, $subtype)]);
		$this->setParams($params);
		if ($charset) {
			$this->setCharset($charset);
		}
	}

	public function isMessageGlobal(): bool
	{
		return
			strtolower($this->getType()) === 'message' &&
			strtolower($this->getSubtype()) === 'global'
		;
	}

	/**
	 * Not a replacement for isMultipart(), but can save some CPU cycles.
	 */
	public function isGenericMultipart(): bool
	{
		switch (strtolower($this->getType())) {
			case 'message':
			case 'multipart':
				return true;
		}
		return false;
	}

	public function getMediaType(): MediaType
	{
		return $this->getValue()[0];
	}

	public function getType(): string
	{
		return $this->getMediaType()->getType();
	}

	public function getSubtype(): string
	{
		return $this->getMediaType()->getSubtype();
	}
}
