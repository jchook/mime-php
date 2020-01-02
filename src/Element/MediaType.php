<?php declare(strict_types=1);

namespace Virtu\Mime\Element;

use InvalidArgumentException;
use RuntimeException;

class MediaType implements MediaTypeInterface
{
	const DEFAULT_TYPE = 'text';
	const DEFAULT_SUBTYPE = 'plain';

	private static $fileExtensionTypesPath = __DIR__ . '/includes/file-extension-types.php';
	private static $fileExtensionTypes = [];

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var string
	 */
	private $subtype;

	public function __construct(string $type = null, string $subtype = null)
	{
		if (!is_null($type) && is_null($subtype)) {
			throw new InvalidArgumentException(
				'Received a content type without subtype'
			);
		}
		$this->type = $type ?: self::DEFAULT_TYPE;
		$this->subtype = $subtype ?: self::DEFAULT_SUBTYPE;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getSubtype(): string
	{
		return $this->subtype;
	}

	private static function checkFile(string $path): void
	{
		if (!file_exists($path)) {
			throw new RuntimeException('File does not exist: ' . $path);
		}
		if (!is_readable($path)) {
			throw new RuntimeException('Cannot read file: ' . $path);
		}
	}

	public static function getFileExtensionTypesPath(): string
	{
		return self::$fileExtensionTypesPath;
	}

	public static function getFileExtensionTypes(): array
	{
		if (!self::$fileExtensionTypes) {
			$path = self::$fileExtensionTypesPath;
			self::$fileExtensionTypes = @include($path);
			if (!self::$fileExtensionTypes) {
				throw new RuntimeException('Unable to load file-extension-types.php');
			}
		}
		return self::$fileExtensionTypes;
	}

	public static function clearFileExtensionTypes(): void
	{
		self::$fileExtensionTypes = [];
	}

	public static function guessType(string $path): ?string
	{
		$name = basename($path);
		$lastDot = strrpos($path, '.');
		if ($lastDot !== false) {
			$ext = substr($name, -$lastDot + 1);
		} else {
			$ext = '';
		}

		// Speed before Magic
		$extGuess = $ext
			? self::getFileExtensionTypes()[$ext][0] ?? null
			: null
		;
		if ($extGuess) {
			return $extGuess;
		}

		// Magic number
		self::checkFile($path);
		$magicGuess = mime_content_type($path);
		if ($magicGuess !== 'text/plain') {
			return $magicGuess;
		}

		return 'application/octet-stream';
	}
}
