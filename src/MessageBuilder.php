<?php

namespace Virtu\Mime;

use Virtu\Mime\Body\File;
use Virtu\Mime\Body\Part;
use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Element\DateTimeInterface;
use Virtu\Mime\Element\ElementInterface;
use Virtu\Mime\Element\Keyword;
use Virtu\Mime\Element\KeywordInterface;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\MediaType;
use Virtu\Mime\Element\MessageId;
use Virtu\Mime\Element\MessageIdInterface;
use Virtu\Mime\Header\AddressListHeader;
use Virtu\Mime\Header\ContentDisposition;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Header\IdentifierListHeader;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Textual\Renderer;

use BadMethodCallException;
use DateTimeInterface as RealDateTimeInterface;
use TypeError;

class MessageBuilder
{
	private $headers = [];
	private $attach = [];
	private $boundaries = [];
	private $subparts = [];

	private static $listHeaders = [
		IdentifierListHeader::class,
		AddressListHeader::class,
		Header::class,
	];

	private function formatHeaderValue($ele, callable $parser): array
	{
		$final = [];
		$eles = is_iterable($ele) ? $ele : [$ele];
		foreach ($eles as $ele) {
			$final[] = ($ele instanceof ElementInterface) ? $ele : $parser($ele);
		}
		return $final;
	}

  public function __call($fn, $args)
	{
		$headerClass = Header::class;
		$headerName = $this->fnToHeaderName($fn);
		$headerValue = array_shift($args);

		switch (strtolower($headerName)) {

			// Date
			case 'date':
			case 'resent-date':
				$headerValue = $this->formatHeaderValue(
					$headerValue,
					[$this, 'parseDateTime']
				);
				break;

			// Address
			case 'to':
			case 'resent-to':
			case 'cc':
			case 'resent-cc':
			case 'bcc':
			case 'resent-bcc':
			case 'from':
			case 'resent-from':
			case 'sender':
			case 'resent-sender':
			case 'reply-to':
			case 'resent-reply-to':
				if (is_string($headerValue ?? '') && is_string($args[0] ?? null)) {
					$headerValue = [new Mailbox(
						$headerValue, ...Mailbox::split(array_shift($args))
					)];
				} else {
					$headerValue = $this->formatHeaderValue(
						$headerValue,
						[$this, 'parseMailbox']
					);
				}
				break;

			// Message ID
			case 'message-id':
			case 'resent-message-id':
			case 'in-reply-to':
			case 'references':
				$headerValue = $this->formatHeaderValue(
					$headerValue,
					[$this, 'parseMessageId']
				);
				break;

			// Keywords
			case 'keywords':
				$headerValue = $this->formatHeaderValue(
					$headerValue,
					[$this, 'parseKeyword']
				);
				break;

			// Unstructured
			case 'subject':
			case 'comments':
				break;

			// X-Extension headers
			default:
				if (strtolower($headerName[0]) !== 'x') {
					throw new BadMethodCallException(
						'Method does not exist: ' . __CLASS__ . '::' . $fn . '() ' . $headerName
					);
				}
				break;
		}

		$this->headers[] = [$headerClass, array_merge(
			[$headerName, $headerValue],
			$args
		)];

		return $this;
  }

	public function boundary(...$boundaries): self
	{
		$this->boundaries = array_merge($this->boundaries, $boundaries);
		return $this;
	}

	public function header(
		string $name,
		string $value,
		?string $charset = null
	): self
	{
		$this->headers[] = [Header::class, [$name, $value, $charset]];
		return $this;
	}

	public function attach(string $path, ?string $contentType = null): self
	{
		// We guess the type here but not in File itself on purpose.
		// MessageBuilder does "nice" stuff; the AST does "predictable" stuff
		if (!$contentType) {
			$contentType = MediaType::guessType($path);
		}
		[$type, $subtype] = explode('/', $contentType, 2);
		$this->attach[] = [
			$path,
			$type,
			$subtype,
		];
		return $this;
	}

	public function html(string $text, string $charset = 'utf-8'): self
	{
		$this->subparts[] = [
			['text', 'html', compact('charset')],
			['quoted-printable'],
			[$text]
		];
		return $this;
	}

	public function text(string $text, string $charset = 'utf-8'): self
	{
		$this->subparts[] = [
			['text', 'plain', compact('charset')],
			['quoted-printable'],
			[$text]
		];
		return $this;
	}

	public function getMessage(): PartInterface
	{
		$files = $html = $text = null;
		$message = [];

		foreach ($this->subparts as $part) {
			$message[] = [
				new ContentType(...$part[0]),
				new ContentTransferEncoding(...$part[1]),
				new Text(...$part[2]),
			];
		}

		if (count($message) === 1) {
			$message = $message[0];
		} elseif (count($message) > 1) {
			$parts = array_map(
				function($part) { return new Part($part); },
				$message
			);
			$message = array_merge(
				[
					new ContentType('multipart', 'alternative', [
						'boundary' => $this->getBoundary($this->attach ? 1 : 0),
					])
				],
				$parts
			);
		}

		if (!$message) {
			$message = [];
		}

		if ($this->attach) {
			$parts = array_map(
				function($args) {
					return [
						new ContentType($args[1], $args[2]),
						new ContentTransferEncoding(
							$args[1] === 'text' ? 'quoted-printable' : 'base64'
						),
						new ContentDisposition('attachment', [
							'filename' => basename($args[0]),
						]),
						new File($args[0]),
					];
				}
			, $this->attach);

			// Smart about nesting?
			if ($message || count($parts) > 1) {
				$parts = array_map(function($part){ return new Part($part); } , $parts);
				$message = array_merge(
					[
						new ContentType('multipart', 'mixed', [
							'boundary' => $this->getBoundary(0),
						]),
					],
					$message ? [new Part($message)] : [],
					$parts
				);
			} elseif (count($parts) === 1) {
				$message = $parts[0];
			}
		}

		$message = array_merge(
			array_map(function($args) {
				$headerClass = $args[0];
				return new $headerClass(...$args[1]);
			}, $this->headers),
			[new MimeVersion],
			$message
		);

		return new Message($message);
	}

	public function getMessageStream()
	{
		return (new Renderer())->renderMessageStream($this->getMessage());
	}

	private function getBoundary(int $idx = 0): string
	{
		return $this->boundaries[$idx] ?? '=_' . bin2hex(random_bytes(16));
	}

	/**
	 * Converts 'replyTo' => 'Reply-To'
	 */
	private function fnToHeaderName($input): string
	{
		$buf = '';
		$parts = [];
		for ($ii = 0, $len = strlen($input); $ii < $len; $ii++) {
			if ($input[$ii] >= 'A' && $input[$ii] <= 'Z') {
				if ($buf) {
					$parts[] = ucfirst($buf);
				}
				$buf = $input[$ii];
			} else {
				$buf .= $input[$ii];
			}
		}
		if ($buf) {
			$parts[] = ucfirst($buf);
		}
		return implode('-', $parts);
	}

	private function parseKeyword(string $keyword): KeywordInterface
	{
		return new Keyword($keyword);
	}

	/**
	 * @param null|string|RealDateTimeInterface $dateTime
	 */
	private function parseDateTime($dateTime): DateTimeInterface
	{
		if (is_string($dateTime) || is_null($dateTime)) {
			return new DateTimeImmutable($dateTime ?? 'now');
		} elseif ($dateTime instanceof RealDateTimeInterface) {
			return new DateTimeImmutable($dateTime->format('r'));
		} else {
			throw new TypeError(
				__METHOD__ . ' expected string or DateTimeInterface but received ' .
					(is_object($dateTime) ? get_class($dateTime) : gettype($dateTime))
			);
		}
	}

	private function parseMessageId(string $messageId): MessageIdInterface
	{
		$lastAt = strrpos($messageId, '@');
		$idLeft = substr($messageId, 0, $lastAt);
		$idRight = substr($messageId, $lastAt + 1);
		return new MessageId($idLeft, $idRight);
	}

	private function parseMailbox(string $mailbox, ?string $name = null): MailboxInterface
	{
		$lastAt = strrpos($mailbox, '@');
		$localPart = substr($mailbox, 0, $lastAt);
		$domain = substr($mailbox, $lastAt + 1);
		return new Mailbox($name ?? '', $localPart, $domain);
	}
}
