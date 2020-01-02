<?php

namespace Virtu\Mime\Contract;

use Virtu\Mime\Body\FileInterface;
use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\MessageMaster;
use Virtu\Mime\Element\ElementInterface;
use Virtu\Mime\Element\CommentInterface;
use Virtu\Mime\Element\DateTimeInterface;
use Virtu\Mime\Element\GroupInterface;
use Virtu\Mime\Element\KeywordInterface;
use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\MediaTypeInterface;
use Virtu\Mime\Element\MessageIdInterface;
use Virtu\Mime\Element\VersionInterface;
use Virtu\Mime\Textual\Lexeme;

use Generator;
use RuntimeException;

/*

RFC 2046 § 4.1.1 data which includes too long segments without CRLF sequences
must be encoded with a suitable content-transfer-encoding.

*/

/**
 * - Check for existence of Mime-Version header
 * - Check for valid  nesting structure of body parts
 * - Check that the content-transfer-encoding makes sense for the document:
 * 	- labeled as 7-bit SHOULD NOT include 8-bit octets
 * 	- labeled as 8-bit SHOULD include 8-bit octets
 * 	- binary files SHOULD be transmitted as binary or base64
 * 	- Non-SMTPUTF8 messages SHOULD use QP or B64 for message bodies with UTF8
 * - Check that the content-type matches:
 * 	- UTF-8 header values must encapsulate with a message/global outer body part
 * - Check characters contained within domain, local-part, etc (isQuotable)
 * - Check if a message requires SMTPUTF8 but utf8 => true was not specified
 * - Checks File to make sure it references a readable file
 * - Checks for known-invalid charset of headers (e.g. us-ascii with 8bit)
 * - Check that headers contain correct children and number of children
 * - Check that headers appear the correct number of times per part
 *
 * @link https://tools.ietf.org/tools/msglint/
 *
 * We do not need to do everything msglint does because we will lint an AST,
 * not a string document.
 *
 * - Syntax errors in headers, including RFC 822 headers, MIME headers,
 *    Delivery Status Notification headers (RFC 1891), Message Disposition
 *    Notification headers (RFC 2298) and several other standards-track header
 *    fields.
 * - Use of RFC 822 comments or whitespace in places likely to cause problems.
 * - Other RFC 822 syntax which is obsolete, deprecated or likely to cause problems.
 * - Use of non-standard header fields.
 * - Use of restricted-use header fields (e.g. RFC 2156 headers).
 * - Use of unregistered tokens in header fields.
 * - Invalid domain name or MIME-boundary characters
 * - Duplicate Headers
 * - Mandatory Headers
 * - Inconsistant use of content-transfer-encoding, charsets and body text with
 *    respect to 8-bit characters.
 * - Incorrect use of quoted-printable, including warnings for unnecessary use
 *    and line-length restrictions.
 * - Incorrect use of base64, including line-length restrictions.
 * - Validates Content-MD5 header values (RFC 1864)
 * - Unfamiliar character set names
 * - Verifies text parts only use legal characters if the character set is
 *    us-ascii, ISO-8859-*, or UTF-8.
 * - Header or text/plain lines which are too long
 * - Missing end boundaries on multipart objects
 * - Misspellings of application/octet-stream
 * - Mismatch between multipart/report "report-type" param and inner report type.
 * - Some news header support (RFC 1036)
 */
class Validator
{
	public static $defaultRules = [
		'mime-version' => 1,
		'nesting' => 1,
		'content-transfer-encoding' => 1,
		// 'utf-8' => 1,
		// 'mailbox' => 1,
		'file-exists' => 1,
		'file-readable' => 1,
		// 'header-charset' => 1,
		'header-value' => 1,
		'header-count' => 1,
	];

	public static $headerValueRequirements = [
		'bcc' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
		'cc' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
		'content-id' => [1, 1, [MessageIdInterface::class]],
		'content-transfer-encoding' => [1, 1, ['string']],
		'content-type' => [1, 1, [MediaTypeInterface::class]],
		'date' => [1, 1, [DateTimeInterface::class]],
		'from' => [1, 0, [MailboxInterface::class]],
		'in-reply-to' => [1, 0, [MessageIdInterface::class]],
		'keywords' => [1, 0, [KeywordInterface::class]],
		'message-id' => [1, 1, [MessageIdInterface::class]],
		'mime-version' => [1, 1, [VersionInterface::class]],
		'references' => [1, 0, [MessageIdInterface::class]],
		'reply-to' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
		'resent-bcc' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
		'resent-cc' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
		'resent-date' => [1, 1, [DateTimeInterface::class]],
		'resent-message-id' => [1, 1, [MessageIdInterface::class]],
		'resent-sender' => [1, 1, [MailboxInterface::class]],
		'resent-to' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
		'resent-to' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
		'sender' => [1, 1, [MailboxInterface::class]],
		'to' => [1, 0, [MailboxInterface::class, GroupInterface::class]],
	];

	private $rules;
	private $utf8 = false;

	public function __construct(array $rules = null)
	{
		$rules = is_null($rules) ? self::$defaultRules : $rules;
		foreach ($rules as $name => $config) {
			$this->rules[$name] = $this->formatRule($name, $config);
		}
	}

	public function validateMessage(PartInterface $part): array
	{
		$message = new MessageMaster($part);
		$results = [];

		$this->utf8 = $message->isMessageGlobal();

		foreach ($this->rules as $name => $rule) {
			if ($rule->getLevel() === Rule::NONE) {
				continue; // TODO TEST
			}
			$fn = implode('', array_map('ucfirst', explode('-', $name)));
			if (!method_exists($this, $fn)) {
				throw new RuntimeException( // TODO TEST
					'Unknown validator rule: ' . $fn
				);
			}
			$ruleResults = iterator_to_array($this->{$fn}($message, $rule));
			if ($ruleResults) {
				$results[$name] = $ruleResults; // TODO TEST
			}
		}
		return $results;
	}


	// ----------------------------------------------------------------------


	private function formatRule(string $name, $config): Rule
	{
		$level = Rule::NONE;
		if (is_int($config)) {
			$level = $config;
			$config = [];
		} elseif (is_array($config)) { // TODO TEST
			$level = array_shift($config);
			$config = array_shift($config);
			if (!is_array($config)) {
				throw new RuntimeException(
					'Invalid validator rule config for ' . $name
				);
			}
		}
		return new Rule($name, $level, $config);
	}

	private function getClassBasename(string $class)
	{
		return basename(str_replace('\\', '//', $class)); // TODO TEST
	}


	// ----------------------------------------------------------------------


	private function contentTransferEncoding(MessageMaster $message, Rule $rule)
	{
		$cte = $message->getContentTransferEncoding();
		$ct = $message->getContentType();
		$enc = $cte->getEncoding();

		if ($message->isMultipart()) {
			if (!in_array($enc, [
				ContentTransferEncoding::ENCODING_7BIT,
				ContentTransferEncoding::ENCODING_8BIT,
				ContentTransferEncoding::ENCODING_BINARY,
			])) {
				yield 'Multipart part must use identity content-transfer-encoding';
			}
			foreach ($message->getSubparts() as $subpart) {
				yield from $this->contentTransferEncoding($subpart, $rule);
			}
			return;
		}

		if ($enc === ContentTransferEncoding::ENCODING_7BIT) {
			$highBit = false;
			$bodies = $message->getBodies();
			foreach ($bodies as $body) {
				foreach ($body as $str) {
					if (!Lexeme::is7Bit($str)) {
						yield '8-bit characters found in body with 7-bit encoding declared';
						break 2;
					}
				}
			}
		}

		switch (strtolower($ct->getType())) {
			// case 'text':
			// 	if ($enc === ContentTransferEncoding::ENCODING_BASE64) {
			// 		yield 'Using base64 encoding on text content type';
			// 	}
			// 	break;
			case 'application':
			case 'audio':
			case 'image':
			case 'video':
				switch ($enc) {
					case ContentTransferEncoding::ENCODING_BASE64:
					case ContentTransferEncoding::ENCODING_BINARY:
					case ContentTransferEncoding::ENCODING_8BIT:
						break;
					default:
						yield
							'Expected binary or base64 content-transfer-encoding for '
							. $ct->getType() . ' content-type'
						;
				}
				break;
		}
	}

	private function fileExists(MessageMaster $message): Generator
	{
		$bodies = $message->getBodiesRecursive();
		foreach ($bodies as $body) {
			if ($body instanceof FileInterface) {
				$path = $body->getPath();
				if (!file_exists($path)) {
					yield 'File does not exist: ' . $path;
				}
			}
		}
	}

	private function fileReadable(MessageMaster $message): Generator
	{
		$bodies = $message->getBodiesRecursive();
		foreach ($bodies as $body) {
			if ($body instanceof FileInterface) {
				$path = $body->getPath();
				if (!is_readable($path)) {
					yield 'Cannot read file: ' . $path;
				}
			}
		}
	}

	private function headerCount(MessageMaster $message): Generator
	{
		$headerRequirements = [
			'date' => [1, 1],
			'from' => [1, 1],
			'sender' => [0, 1],
			'reply-to' => [0, 1],
			'to' => [0, 1],
			'cc' => [0, 1],
			'bcc' => [0, 1],
			'message-id' => [0, 1],
			'in-reply-to' => [0, 1],
			'references' => [0, 1],
			'subject' => [0, 1],
		];

		foreach ($headerRequirements as $name => [$min, $max]) {
			$headers = $message->getHeaders($name);
			$found = count($headers);
			if ($min && $found < $min) {
				yield 'Must have at least ' . $min . ' ' . $name . ' header';
			} elseif ($max && $found > $max) {
				yield "Too many {$name} headers (max: {$max}, found: {$found})";
			}
		}

		// TODO: sender presence with multi-from or make a new rule
	}

	// TODO: this should be broken down into separate rules:
	// 	- header-charset-name
	// 	- header-charset-value?
	// private function headerCharset(MessageMaster $message): Generator
	// {
	// 	$global = $message->isMessageGlobal();
	// 	$headers = $message->getAllHeadersRecursive();
	// 	if ($this->utf8) {
	// 		return;
	// 	}
	// 	foreach ($headers as $header) {
	// 		if (!$this->is7bit($header->getName())) {
	// 			yield 'Header names must contain only 7-bit ascii';
	// 		}
	// 	}
	//
	// 	// Check for ascii headers
	// 	if (!$global) {
	// 		foreach ($headers as $header) {
	// 			// TODO:
	// 			// Should all headers implement Traversable & getValue(): iterable?
	// 			if ($header instanceof Traversable && method_exists($header, 'getCharset')) {
	// 				if (strtolower($header->getCharset()) !== 'us-ascii') {
	// 					continue;
	// 				}
	// 				foreach ($header as $ele) {
	// 					if (is_string($ele)) {
	// 						if (!Lexeme::is7Bit($ele)) {
	// 							yield $header->getName() . ' header contains non-ascii characters';
	// 							break 2;
	// 						}
	// 					}
	// 				}
	// 			}
	// 		}
	// 	}
	// }

	private function headerValue(MessageMaster $message): Generator
	{
		$valueRequirements = self::$headerValueRequirements;

		foreach ($valueRequirements as $name => [$min, $max, $classes]) {
			foreach ($message->getHeaders($name) as $header) {
				$count = 0;
				$hasForeign = false;
				$hasElement = false;
				foreach ($header as $ele) {
					$count++;
					$correct = false;
					if ($ele instanceof ElementInterface) {
						$hasElement = true;
					} else {
						$hasForeign = true;
					}
					foreach ($classes as $class) {
						if ($class === 'string') {
							$correct = $correct || is_string($ele);
						} else {
							$correct = $correct || is_a($ele, $class);
						}
					}
					if (!$correct) {
						yield ucfirst($name) . ' header must contain only ' . implode(
							' or ', array_map([$this, 'getClassBasename'], $classes)
						);
					}
				}
				if ($hasForeign && $hasElement) {
					yield ucfirst($name) . ' header must not contain both ' .
						'ElementInterface and other types (e.g. string)'
					;
					break;
				}
				if ($min && $count < $min) {
					yield ucfirst($name) . ' header must contain at least one ' . implode(
						' or ', array_map([$this, 'getClassBasename'], $classes)
					);
				}
				if ($max && $count > $max) {
					yield ucfirst($name) . ' header contains too many elements';
				}
			}
		}
	}

	private function mimeVersion(MessageMaster $message, Rule $rule)
	{
		$header = $message->getHeader('mime-version');
		if (!$header) {
			yield 'Missing MIME-Version header';
			return;
		}
		$version = $header->getValue()[0];
		if (!$version || ([$version->getMajor(), $version->getMinor()] !== [1,0])) {
			yield 'Unrecognized MIME-Version';
		}
	}

	private function nesting(MessageMaster $message, Rule $rule): Generator
	{
		if (!$message->isMultipart()) {
			return;
		}
		if (!$message->getContentType()->isGenericMultipart()) {
			yield 'Multipart body part must have a multipart content-type';
		}
		foreach ($message->getSubparts() as $subpart) {
			yield from $this->nesting($subpart, $rule);
		}
	}
}
