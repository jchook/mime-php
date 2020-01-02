<?php

namespace Virtu\Mime\Contract;

use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Textual\Renderer;

use TypeError;
use RuntimeException;

/**
 *
 * Use `msglint` to lint a MIME document.
 *
 * Note you must have `msglint` installed. See link below for details.
 *
 * @link https://tools.ietf.org/tools/msglint/
 *
 */

class Linter
{
	const ERROR = 'ERROR';
	const FATAL = 'FATAL';
	const WARNING = 'WARNING';

	public function lintMessage(PartInterface $message): array
	{
		// echo "\nLINT MESSAGE\n";
		$temp = fopen('php://temp', 'rw');
		$renderer = new Renderer();
		$rendered = $renderer->renderMessage($message);
		foreach ($rendered as $chunk) {
			// echo $chunk;
			fwrite($temp, $chunk);
		}
		rewind($temp);
		$result = $this->lintMessageStream($temp);
		fclose($temp);
		return $result;
	}

	public function lintMessageString(string $message): array
	{
		$temp = fopen('php://temp', 'rw');
		fwrite($temp, $message);
		rewind($temp);
		$result = $this->lintMessageStream($temp);
		fclose($temp);
		return $result;
	}

	/**
	 * @param resource $message
	 * @return array ['rule-name' => ['feedback', ...]]
	 */
	public function lintMessageStream($message): array
	{
		if (!is_resource($message)) {
			throw new TypeError(
				__METHOD__ . ' expects a valid MIME message resource'
			);
		}

		$command = !empty($_SERVER['MSGLINT_PATH'])
			? $_SERVER['MSGLINT_PATH']
			: 'msglint'
		;
		$spec = [
			['pipe', 'r'],
			['pipe', 'w'],
			['pipe', 'w'],
		];

		$proc = proc_open($command, $spec, $pipes);

		// The PHP manaul says this can return false but I haven't figured out how.
		// @codeCoverageIgnoreStart
		if (!is_resource($proc)) {
			throw new RuntimeException(
				'Unable to execute msglint. Perhaps you don\'t have it installed?'
			);
		}
		// @codeCoverageIgnoreEnd

		stream_copy_to_stream($message, $pipes[0]);
		fclose($pipes[0]);

		$out = stream_get_contents($pipes[1]);
		$err = stream_get_contents($pipes[2]);

		$exitcode = proc_close($proc);

		if ($exitcode !== 0 && $exitcode !== 255) {
			throw new RuntimeException(
				"Command '$command' failed with exit code $exitcode"
			);
		}

		return array_filter(
			$this->parseLines($out),
			function($result) {
				return
					$result[0] !== 'OK' &&
					$result[0] !== 'UNKNOWN' &&
					(strpos($result[1] ?? '', 'mandatory header \'return-path\'') === false)
				;
			}
		);
	}

	/**
	 * msglint output has FWS semantics familiar to readers of 822 et al.
	 */
	public function parseLines(string $out): array
	{
		$ii = -1;
		$lines = [];
		$rawLines = explode("\n", $out);
		foreach ($rawLines as $line) {
			if (!trim($line)) {
				continue;
			}
			if ($line[0] === ' ') {
				if ($ii === -1) {
					throw new RuntimeException('Unexpected initial whitespace from msglint');
				}
				$lines[$ii][1] .= ' ' . trim($line);
			} else {
				$lines[++$ii] = array_map('trim', explode(': ', $line, 2));
				if (count($lines[$ii]) !== 2) {
					throw new RuntimeException('Unexpected output line from msglint');
				}
			}
		}

		return $lines;
	}
}