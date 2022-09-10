<?php

namespace Virtu\Mime\Spec\Element;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Element\MediaType;
use Virtu\Mime\Element\MediaTypeInterface;
use Virtu\Mime\Element\ElementInterface;

use TypeError;
use RuntimeException;
use InvalidArgumentException;

/**
 * @covers Virtu\Mime\Element\MediaType
 */
class MediaTypeTest extends TestCase
{
	public function testInterface()
	{
		$ele = new MediaType();
		$this->assertInstanceOf(MediaTypeInterface::class, $ele);
		$this->assertInstanceOf(ElementInterface::class, $ele);
	}

	public function testGetter()
	{
		$type = 'application';
		$subtype = 'octet-stream';
		$ele = new MediaType($type, $subtype);
		$this->assertSame($type, $ele->getType());
		$this->assertSame($subtype, $ele->getSubtype());
	}

	public function testGetFileExtensionTypesFail()
	{
		MediaType::clearFileExtensionTypes();
		$path = MediaType::getFileExtensionTypesPath();
		$newPath = sys_get_temp_dir() . '/file-extension-types.php';
		try {
			$this->assertFileExists($path);
			rename($path, $newPath);
			$this->assertFileDoesNotExist($path);
			MediaType::getFileExtensionTypes();
		} catch (RuntimeException $e) {
		} finally {
			rename($newPath, $path);
			$this->assertFileExists($path);
			$this->assertTrue(isset($e), 'throws exception');
			$this->assertInstanceOf(RuntimeException::class, $e);
		}
	}

	public function testGetFileExtensionTypes()
	{
		$this->assertEmpty(MediaType::clearFileExtensionTypes());
		$this->assertNotEmpty(MediaType::getFileExtensionTypes());
	}

	public function testGuessTypeByExt()
	{
		$this->assertEquals('text/plain', MediaType::guessType('test.txt'));
	}

	public function testMissingFileNoExtNonExistant()
	{
		$this->expectException(RuntimeException::class);
		$this->assertEquals('text/plain', MediaType::guessType('test-nonexistant'));
	}

	public function testMissingFileNoExtNonReadable()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test-');
		$data = random_bytes(64);
		file_put_contents($path, $data);
		chmod($path, 0077);

		try {
			$this->assertEquals('text/plain', MediaType::guessType($path));
		} catch (RuntimeException $e) {
		} finally {
			$this->assertInstanceOf(RuntimeException::class, $e);
			unlink($path);
		}
	}

	public function testGuessTypeByMagicNumber()
	{
		// 1x1 PNG
		$data = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAA' .
			'XRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII='
		);
		$path = tempnam(sys_get_temp_dir(), 'mime-test-');
		file_put_contents($path, $data);
		$this->assertEquals('image/png', MediaType::guessType($path));
		unlink($path);
	}

	public function testGuessTypeByMagicNumberDefault()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test-');
		// 1x1 PNG
		$data = base64_decode(
			'ABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAA' .
			'XRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII='
		);
		file_put_contents($path, $data);
		$this->assertEquals('application/octet-stream', MediaType::guessType($path));
		unlink($path);
	}

	public function testGuessTypeDefault()
	{
		$path = tempnam(sys_get_temp_dir(), 'mime-test-');
		$data = 'hello world';
		file_put_contents($path, $data);
		$this->assertEquals('application/octet-stream', MediaType::guessType($path));
		unlink($path);
	}

	public function testInvalidInput()
	{
		$this->expectException(TypeError::class);
		new MediaType([], []);
	}

	public function testMissingSubtype()
	{
		$this->expectException(InvalidArgumentException::class);
		new MediaType('text');
	}
}
