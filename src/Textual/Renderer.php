<?php

namespace Virtu\Mime\Textual;

use Virtu\Mime\Body\BodyInterface;
use Virtu\Mime\Body\BodyStreamInterface;
use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Codec\Base64;
use Virtu\Mime\Codec\CodecInterface;
use Virtu\Mime\Codec\Identity;
use Virtu\Mime\Codec\QuotedPrintable;
use Virtu\Mime\Element\CommentInterface;
use Virtu\Mime\Element\DateTimeInterface;
use Virtu\Mime\Element\GroupInterface;
use Virtu\Mime\Element\KeywordInterface;
use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\MediaTypeInterface;
use Virtu\Mime\Element\VersionInterface;
use Virtu\Mime\Header\HeaderInterface;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\ParameterizedHeaderInterface;
use Virtu\Mime\Message;

use Generator;
use TypeError;
use InvalidArgumentException;

class Renderer
{
	/**
	 * Charset of current rendering context
	 * @var ?string
	 */
	private $charset;

	/**
	 * Total message/global parents of current rendering context
	 * @var int
	 */
	private $global = 0;

	/**
	 * Since we render everything in-line (like a stream), we can keep track of
	 * our x position in the current line here.
	 *
	 * @var int
	 */
	private $indent = 0;

	/**
	 * @var CodecInterface[]
	 */
	private $encoders = [];

	/**
	 * Render the message progressively (as an iterable) to use less RAM.
	 */
	public function renderMessage(PartInterface $message): Generator
	{
		yield from $this->renderPart($message);
	}

	/**
	 * @param PartInterface $message
	 * @param ?resource $stream
	 * @return resource
	 */
	public function renderMessageStream(PartInterface $message, $receiver = null)
	{
		$render = $this->renderMessage($message);
		$stream = is_resource($receiver) ? $receiver : fopen('php://temp', 'rw');
		foreach ($render as $chunk) {
			fwrite($stream, $chunk);
		}
		if (!$receiver || stream_get_meta_data($stream)['seekable']) {
			rewind($stream);
		}
		return $stream;
	}

	/**
	 * Render the entire message as a string.
	 * NB. this may use a lot of RAM if your message has large attachments.
	 */
	public function renderMessageString(PartInterface $message): string
	{
		return implode('',
			iterator_to_array($this->renderMessage($message), false)
		);
	}

	private function getCodec(string $encoding): CodecInterface
	{
		if (isset($this->encoders[$encoding])) {
			return $this->encoders[$encoding];
		}
		switch ($encoding) {
			default:
				return $this->encoders[$encoding] = new Identity();
			case ContentTransferEncoding::ENCODING_BASE64:
				return $this->encoders[$encoding] = new Base64();
			case ContentTransferEncoding::ENCODING_QP:
				return $this->encoders[$encoding] = new QuotedPrintable();
		}
	}

	/**
	 * Should we comma-separate this element?
	 *
	 * RFC 5322 § 3.4
	 * mailbox-list = mailbox *("," mailbox)
	 * address-list = address *("," address)
	 *
	 * RFC 5322 § 3.6.5
	 * keywords = "Keywords:" phrase *("," phrase) CRLF
	 *
	 * @param ElementInterface|string $ele
	 */
	private function isCommaSeparated($ele): bool
	{
		return ($ele instanceof MailboxInterface)
			|| ($ele instanceof GroupInterface)
			|| ($ele instanceof KeywordInterface)
		;
	}

	/**
	 * Determine if the given part has Content-Type: message/global
	 */
	private function isMessageGlobal(PartInterface $part): bool
	{
		foreach ($part as $child) {
			if ($child instanceof HeaderInterface) {
				if ($child->hasName('Content-Type')) {
					foreach ($child as $value) {
						if ($value instanceof MediaTypeInterface) {
							if (
								strtolower($value->getType()) === 'message' &&
								strtolower($value->getSubtype()) === 'global'
							) {
								return true;
							}
						}
					}
					break;
				}
			} else {
				break;
			}
		}
		return false;
	}

	/**
	 * Render a MIME "body part"
	 * @throws TypeError
	 */
	private function renderPart(
		PartInterface $part,
		string $parentEncoding = ContentTransferEncoding::ENCODING_7BIT
	): Generator
	{
		$encoding = $parentEncoding;
		$boundary = '=_' . bin2hex(random_bytes(16));
		$bodyStarted = false;
		$multipart = false;
		$thisIsGlobal = false;

		// Determine if this is a message/global
		// This applies to all children parts too
		if (!$this->global) {
			if ($this->isMessageGlobal($part)) {
				$this->global++;
				$thisIsGlobal = true;
			}
		}

		foreach ($part as $child) {

			// Render headers
			if ($child instanceof HeaderInterface) {
				if ($child->hasName('Content-Type')) {
					if ($child instanceof ParameterizedHeaderInterface) {
						$boundary = $child->getParam('boundary') ?: $boundary;
					}
				} elseif ($child->hasName('Content-Transfer-Encoding')) {
					$encoding = $child->getValue()[0];
				}
				yield from $this->renderHeader($child);
				continue;
			}

			// MULTIPART
			if ($child instanceof PartInterface) {
				$bodyStarted = true;
				$multipart = true;
				yield "\r\n--${boundary}\r\n";
				$part = $this->renderPart($child, $encoding);
				foreach ($part as $subpart) {
					yield $subpart;
				}
			}

			// BODY
			elseif ($child instanceof BodyInterface) {
				if (!$bodyStarted) {
					$bodyStarted = true;
					yield "\r\n";
				}
				$body = $this->renderBody($child, $encoding);
				foreach ($body as $piece) {
					yield $piece;
				}
			}

			// VERBATIM
			elseif (is_string($child)) {
				if (!$bodyStarted) {
					$bodyStarted = true;
					yield "\r\n";
				}
				yield $child;
			}

			// ???
			else {
				throw new TypeError(
					'Unexpected mime body part. Expected string, BodyInterface, '
					 . 'HeaderInterface, or PartInterface, but received '
					 . gettype($child)
				);
			}
		}

		// You must render CRLF even with no header fields
		if (!$bodyStarted) {
			yield "\r\n";
		}

		// close-boundary
		if ($multipart) {
			yield "\r\n--${boundary}--\r\n";
		}

		// Reset "global"
		if ($thisIsGlobal) {
			$this->global--;
		}
	}

	/**
	 * Render a non-multipart MIME body
	 */
	private function renderBody(
		BodyInterface $body,
		string $encoding = ContentTransferEncoding::ENCODING_7BIT
	): Generator
	{
		$encoder = $this->getCodec($encoding);
		if ($body instanceof BodyStreamInterface) {
			yield from $encoder->encodeStream($body->getResource());
		} else {
			$final = [];
			foreach ($body as $piece) {
				$final[] = $piece;
			}

			yield $encoder->encodeString(implode('', $final));
		}
	}

	/**
	 * Render a single header
	 *
	 * RFC 5322 § 2.2
	 * "An unfolded header field has no length restriction and therefore may be
	 * indeterminately long."
	 */
	private function renderHeader(HeaderInterface $header): Generator
	{
		$this->indent = 0;
		$this->charset = $header->getCharset();

		yield $this->renderString($header->getName() . ': ');
		yield from $this->renderHeaderValue($header->getValue());
		if ($header instanceof ParameterizedHeaderInterface) {
			yield from $this->renderHeaderParams($header->getParams());
		}
		yield "\r\n";

		$this->indent = 0;
		$this->charset = null;


		// RFC 2047 § 5 - header field encoding allowed:
		//
		// 1. RFC 822 `text` tokens in:
		// 	- Anywhere: Subject or Comments fields
		// 	- Message headers: X-* fields
		// 	- Body part headers: any defined as `*text`
		//
		// 2. Within any `comment`
		//
		// 3. As a replacement for a `word` within a `phrase`
		// 	- `display-name` part of `mailbox`
		// 	- keywords in the "Keywords" header (comma-delimited)
		//
		// Remember:
		//  phrase = 1*word
		//	word = atom / quoted-string
		//	atom = [CFWS] 1*atext [CFWS]
		//	unstructured = (*([FWS] VCHAR) *WSP)

		// Internationalized Headers
		// if (!$this->is7Bit($value)) {
		// 	if (method_exists($header, 'getCharset')) {
		// 		return $this->encodeHeader(
		// 			$header->getName(), $value, $header->getCharset()
		// 		) . "\r\n";
		// 	} else {
		// 		throw new InvalidArgumentException(
		// 			'Invalid non-7bit ASCII header value. Please use a HeaderInterface'
		// 				. ' class that implements getCharset()'
		// 		);
		// 	}
		// }

		// TODO: This should probably go in Contract\Validator
		// Encode header if it's too long
		// if (max(array_map('strlen', explode("\r\n", $final))) > 78) {
		// 	$final = $this->encodeHeader($header->getName(), $value, 'us-ascii');
		// }
	}

	/**
	 * Parameterized header
	 * TODO: implement RFC 2231
	 */
	private function renderHeaderParams(
		iterable $params,
		?string $charset = null
	): Generator
	{
		$final = [];
		if ($params) {
			foreach ($params as $var => $val) {
				$final[] = '; ' . $var . '=' . $this->stringifyValue($val);
			}
		}
		yield $this->renderString(implode('', $final));
	}

	/**
	 * Render a single header value
	 */
	private function renderHeaderValue(
		iterable $value,
		?string $charset = null,
		int &$indent = 0
	): Generator
	{
		$final = [];
		$previous = null;

		// Iterable everything!
		foreach ($value as $current) {

			// Comma delimited mailboxes / groups
			if ($this->isCommaSeparated($current) && $this->isCommaSeparated($previous)) {
				yield $this->renderString(', ');
			}

			// Mailbox groups
			// RFC 5322 § 3.4.1
			// display-name = phrase
			// group = display-name ":" [group-list] ";" [CFWS]
			// group-list = mailbox-list
			if ($current instanceof GroupInterface) {
				yield from $this->renderGroup($current);
			}

			// Mailbox
			// RFC 5322 § 3.4.1
			elseif ($current instanceof MailboxInterface) {
				yield from $this->renderMailbox($current);
			}

			// DateTime
			// RFC 5322 § 3.3
			elseif ($current instanceof DateTimeInterface) {
				yield $this->renderString($current->format('r')); // D, d M Y H:i:s O
			}

			// Comment
			// RFC 5322 § 3.2.2
			elseif ($current instanceof CommentInterface) {
				yield $this->renderString(
					$this->stringifyComment($current->getComment())
				);
			}

			// Keyword
			// RFC 5322 § 3.6.5
			// keywords = "Keywords:" phrase *("," phrase) CRLF
			elseif ($current instanceof KeywordInterface) {
				yield $this->renderPhrase($current->getKeyword());
			}

			// MIME-Version
			// RFC 2045 § 4
			// version = "MIME-Version" ":" 1*DIGIT "." 1*DIGIT
			elseif ($current instanceof VersionInterface) {
				yield $this->renderString(
					$current->getMajor() . '.' . $current->getMinor()
				);
			}

			// Content-Type
			// RFC 2045 § 5.1
			// content = "Content-Type" ":" type "/" subtype
			// type = discrete-type / composite-type
			// discrete-type = "text" / "image" / "audio" / "video" / "application" /
			//   extension-token
			// composite-type = "message" / "multipart" / extension-token
			// extension-token = ietf-token / x-token
			// subtype = extension-token / iana-token
			else if ($current instanceof MediaTypeInterface) {
				yield $this->renderString(
					$current->getType() . '/' . $current->getSubtype()
				);
			}

			// Unstructured value
			// RFC 5322 § 3.2.5
			elseif (is_string($current)) {
				yield $this->renderUnstructured(
					$current,
					$charset
				);
			}

			// Keep track of the previous item
			$previous = $current;
		}
	}

	private function renderGroup(GroupInterface $group): Generator
	{
		yield $this->renderPhrase($group->getName());
		yield $this->renderString(':');
		yield from $this->renderMailboxList($group->getMailboxes());
		yield $this->renderString(';');
	}

	/**
	 * RFC 5322 § 3.4.1
	 * mailbox = name-addr / addr-spec
	 * name-addr = [display-name] angle-addr
	 * angle-addr = [CFWS] "<" addr-spec ">" [CFWS]
	 * addr-spec = local-part "@" domain
	 */
	private function renderMailbox(MailboxInterface $mailbox): Generator
	{
		$str =
			$this->stringifyLocalPart($mailbox->getLocalPart()) . '@' .
			$this->stringifyDomain($mailbox->getDomain())
		;
		if ($mailbox->getName()) {
			yield $this->renderPhrase($mailbox->getName());
			yield $this->renderString(' <' . $str . '>');
		} else {
			yield $this->renderString($str);
		}
	}

	/**
	 * RFC 5322 § 3.4
	 * mailbox-list = mailbox *("," mailbox)
	 */
	private function renderMailboxList(array $mailboxes): Generator
	{
		for ($ii = 0, $len = count($mailboxes); $ii < $len; $ii++) {
			if ($ii > 0) {
				yield ', ';
			}
			yield from $this->renderMailbox($mailboxes[$ii]);
		}
	}

	/**
	 * RFC 5322 § 3.2.5
	 * RFC 2047 § 5 #3
	 * phrase = 1*(encoded-word / word)
	 * word = atom / quoted-string
	 */
	private function renderPhrase(
		string $phrase,
		?string $charset = null
	): string
	{
		if (Lexeme::isAText($phrase)) {
			return $this->renderString($phrase);
		} elseif (Lexeme::isQuotable($phrase, $this->global)) {
			return $this->renderString($this->stringifyQuotedString($phrase));
		} else {
			return $this->renderEncodedWord($phrase, $charset);
		}
	}

	/**
	 * RFC 5234 § Appendix B.1
	 * VCHAR = %x21-7E
	 *
	 * RFC 6532 § 3.2
	 * VCHAR =/ UTF8-non-ascii
	 *
	 * RFC 5322 § 2.2.1, 3.2.5
	 * unstructured = *([FWS] VCHAR) *WSP
	 *
	 * RFC 2047 § 5 #1
	 * Can replace `text` (RFC 822) with `encoded-word`
	 *
	 * RFC 822 § 3.3
	 * text = <any CHAR, including bare CR & bare LF, but NOT including CRLF>
	 */
	private function renderUnstructured(
		string $str,
		?string $charset = null
	): string
	{
		if ($this->global || Lexeme::is7bit($str)) {
			return $this->renderString($str);
		}
		return $this->renderEncodedWord($str, $charset, 'B');
	}

	/**
	 * RFC 2047 § 5
	 * Encoded words can appear:
	 * - instead of a `text` token
	 * - within a `comment`
	 * - instead of a `word` within a `phrase`
	 */
	private function renderEncodedWord(
		string $str,
		?string $charset = null,
		?string $transfer = null
	): string
	{
		$indent = &$this->indent;

		$old = mb_internal_encoding();
		mb_internal_encoding('utf-8');
		$final = mb_encode_mimeheader(
			$str, $charset ?: 'utf-8', $transfer ?: 'B', "\r\n", $indent
		);
		mb_internal_encoding($old);

		// Calculate the next "indent"
		$lastBreak = strrpos($final, "\r\n");
		$indent = $lastBreak !== false
			? strlen($final) - $lastBreak - 2
		 	: strlen($final)
		;

		// Keep consistency here with the line breaks
		return implode("\r\n\t", explode("\r\n ", $final));
	}

	/**
	 * Wrap header value
	 */
	private function renderString(string $str): string
	{
		$indent = &$this->indent;
		if ($this->global) {
			$str = Chars::wordWrapUtf8($str, 75, "\r\n\t", false, $indent);
		} else {
			$str = Chars::wordWrap($str, 75, "\r\n\t", false, $indent);
		}
		return $str;
	}

	/**
	 * RFC 5322 § 3.2.2
	 * comment = "(" *([FWS] ccontent) [FWS] ")"
	 * ccontent = ctext / quoted-pair / comment
	 * ctext = %d33-39 / %d42-91 / %d39-126
	 * 	; printable ASCII not including "(", ")", or "\"
	 */
	private function stringifyComment(string $comment): string
	{
		// Well-formed UTF-8 has unique bytes that cannot be mistaken for ascii.
		// https://web.archive.org/web/20140105125012/http://www.phpwact.org:80/php/i18n/utf-8#str_replace
		$comment = str_replace('\\', '\\\\', $comment);
		$comment = str_replace('(', '\\', $comment);
		$comment = str_replace(')', '\\', $comment);
		return '(' . $comment . ')';
	}

	/**
	 * domain = dot-atom / domain-literal
	 */
	private function stringifyDomain(string $domain): string
	{
		if ($this->global) {
			return $domain;
		}
		return idn_to_ascii(
			$domain,
			IDNA_DEFAULT,
			INTL_IDNA_VARIANT_UTS46
		);
	}

	/**
	 * RFC 5322 § 3.4.1
	 * local-part = dot-atom / quoted-string
	 */
	private function stringifyLocalPart(string $localPart): string
	{
		return Lexeme::isDotAtomText($localPart)
			? $localPart
			: $this->stringifyQuotedString($localPart, 'mailbox local part');
	}

	/**
	 * RFC 2045 § 5.1 parameter value
	 * value = token / quoted-string
	 */
	private function stringifyValue(string $value): string
	{
		return Lexeme::isToken($value)
			? $value
			: $this->stringifyQuotedString($value);
	}

	/**
	 * RFC 6532 § 3.2
	 * RFC 5322 § 3.2.4
	 * RFC 2822 § 3.2.5
	 * RFC 822 § 3.3
	 * qcontent = qtext / quoted-pair
	 * quoted-string = [CFWS] DQUOTE *([FWS] qcontent) [FWS] DQUOTE [CFWS]
	 * qtext = %d33 / %d35-91 / %d93-126 / UTF8-non-ascii
	 *   ; not "\" not DQUOTE
	 */
	private function stringifyQuotedString(string $str): string
	{
		if (Lexeme::is7bit($str)) {
			$str = str_replace('\\', '\\\\', $str);
			$str = str_replace('"', '\\"', $str);
		} else {
			$str = Chars::mbStrReplace('\\', '\\\\', $str);
			$str = Chars::mbStrReplace('"', '\\"', $str);
		}
		return '"' . $str . '"';
	}

}