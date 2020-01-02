<?php

namespace Virtu\Mime\Spec\Contract;

use Virtu\Mime\Spec\TestCase;
use Virtu\Mime\Contract\Linter;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Textual\Renderer;
use Virtu\Mime\MessageBuilder;

use RuntimeException;
use TypeError;

/**
 * @covers Virtu\Mime\Contract\Linter
 */
class LinterTest extends TestCase
{
	public function testLintMessageString()
	{
		$message = implode("\r\n", [
			'To: ready@example.com',
			'From: set@sample.com',
			'Subject: Go',
			'Date: ' . date('r'),
			'MIME-Version: 1.0',
			'',
			'Hello world.'
		]);
		$linter = new Linter();
		$lint = $linter->lintMessageString($message);
		// var_dump($lint);
		$this->assertEmpty($lint);
	}

	public function testLintMissingCommand()
	{
		try {
			$_SERVER['MSGLINT_PATH'] = '/tmp/non-existant';
			$linter = new Linter();
			$lint = $linter->lintMessageString('');
		} catch (RuntimeException $e) {
		} finally {
			unset($_SERVER['MSGLINT_PATH']);
			$this->assertTrue(isset($e));
			$this->assertEquals(
				'Command \'/tmp/non-existant\' failed with exit code 127',
				$e->getMessage()
			);
		}
	}
	public function testLintMessage()
	{
		$file = '/tmp/attachment.bin';
		file_put_contents($file, random_bytes(256));
		$now = new DateTimeImmutable();

		$message = (new MessageBuilder())
			->to('ready@example.com')
			->from('set@sample.com')
			->replyTo('go@sample.com')
			->subject('Test subject')
			->header('Custom-Thing', 'test')
			->header('Very-Long-Custom-Header-To-Throw-A-Long-Warning', 'test')
			->date($now)
			->text('Hello world')
			->html('<h1>Hello world</h1>')
			->attach($file)
			->getMessage()
		;

		$linter = new Linter();
		$lint = $linter->lintMessage($message);
		// print_r($lint);
		$this->assertEmpty(
			// $lint
			array_filter($lint, function($ll) {
				return $ll[0] === Linter::ERROR;
			})
		);

		unlink($file);
	}

	public function testLintUnexpectedWhitespace()
	{
		try {
			$_SERVER['MSGLINT_PATH'] = 'echo " test"';
			$linter = new Linter();
			$lint = $linter->lintMessageString('');
		} catch (RuntimeException $e) {
		} finally {
			unset($_SERVER['MSGLINT_PATH']);
			$this->assertTrue(isset($e), 'unexpected whitespace throws exception');
			$this->assertEquals(
				'Unexpected initial whitespace from msglint',
				$e->getMessage()
			);
		}
	}

	public function testLintUnexpectedOutput()
	{
		try {
			$_SERVER['MSGLINT_PATH'] = 'echo "ERROR"';
			$linter = new Linter();
			$lint = $linter->lintMessageString('');
		} catch (RuntimeException $e) {
		} finally {
			unset($_SERVER['MSGLINT_PATH']);
			$this->assertTrue(isset($e), 'unexpected output line throws exception');
			$this->assertEquals(
				'Unexpected output line from msglint',
				$e->getMessage()
			);
		}
	}

	public function testLintStreamTypeError()
	{
		$this->expectException(TypeError::class);
		$linter = new Linter();
		$lint = $linter->lintMessageStream(null);
	}

}
