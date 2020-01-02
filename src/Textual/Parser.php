<?php

namespace Virtu\Mime\Textual;

use Virtu\Mime\Body\BodyInterface;
use Virtu\Mime\Body\Part;
use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Body\Resource;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Codec\Base64;
use Virtu\Mime\Codec\CodecInterface;
use Virtu\Mime\Codec\QuotedPrintable;
use Virtu\Mime\Codec\Identity;
use Virtu\Mime\Element\DateTimeImmutable;
use Virtu\Mime\Element\DateTimeInterface;
use Virtu\Mime\Element\ElementInterface;
use Virtu\Mime\Element\Group;
use Virtu\Mime\Element\GroupInterface;
use Virtu\Mime\Element\Keyword;
use Virtu\Mime\Element\KeywordInterface;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Element\MailboxInterface;
use Virtu\Mime\Element\MediaType;
use Virtu\Mime\Element\MediaTypeInterface;
use Virtu\Mime\Element\MessageId;
use Virtu\Mime\Element\MessageIdInterface;
use Virtu\Mime\Element\Version;
use Virtu\Mime\Element\VersionInterface;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Header\HeaderInterface;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Message;

use Throwable;
use RuntimeException;
use OverflowException;
use InvalidArgumentException;

/**
 *
 * Parser
 *
 * Parses MIME documents into ASTs.
 *
 */

class Parser
{
	const DEFAULT_TRANSFER_ENCODING = '7bit';
	const DEFAULT_CHARSET = 'us-ascii';

	/**
	 * @var resource
	 */
	private $source;

	/**
	 * Only used for future improvement
	 * @var string ('message')
	 */
	private $sourceType = 'message';

	/**
	 * Most recently consumed token
	 * @var ?string
	 */
	private $previous;

	/**
	 * @var ?string
	 */
	private $previousType;

	/**
	 * Current position within the source stream
	 * @var int
	 */
	private $pos = 0;

	/**
	 * Current buffer
	 * @var ?string
	 */
	private $buf;

	/**
	 * Length of the current buffer
	 * @var int
	 */
	private $bufLen = 0;

	/**
	 * Current position within the current buffer
	 * @var int
	 */
	private $bufPos = 0;

	/**
	 * Limit the size of each buffer
	 * @var int
	 */
	private $bufReadSize = 4096;

	/**
	 * How much to read onto the buffer when peeking past the end
	 * @var int
	 */
	private $bufPeekSize = 1024;

	/**
	 * 10MB by default. 0 = no limit.
	 * @var int
	 */
	private $bufMaxSize = 10485760;

	/**
	 * Keep track of how large the buffer got
	 * @var int
	 */
	private $bufMaxSizeReached = 0;

	/**
	 * Keep track of how many times we had to adjust the buffer to keep it small
	 * @var int
	 */
	private $bufMaxSizeAdjustments = 0;

	/**
	 * The current part boundary
	 * @var ?string
	 */
	private $boundary;

	/**
	 * @var array
	 */
	private $codecs = [];

	/**
	 * Current context
	 * @var array
	 */
	private $env = [];

	/**
	 * Which line-ending to use
	 * @var string
	 */
	private $crlf = "\r\n";

	/**
	 * @var string[]
	 */
	private $stack = [];

	/**
	 * Create a new parser with configuration
	 */
	public function __construct(array $config = [])
	{
		foreach ($config as $var => $val) {
			switch ($var) {
				case 'bufPeekSize':
				case 'bufReadSize':
				case 'bufMaxSize':
					$this->{$var} = $val;
					break;
				default:
					throw new InvalidArgumentException(
						'Invalid Parser configuration directive: ' . $var
					);
			}
		}

		// Check max size
		if ($this->bufMaxSize) {
			if (
				($this->bufMaxSize < $this->bufReadSize) ||
				($this->bufMaxSize < $this->bufPeekSize)
			) {
				throw new InvalidArgumentException(
					'bufMaxSize must exceed both bufReadSize and bufPeekSize'
				);
			}
		}
	}

	/**
	 * @param resource $source
	 */
	public function parseMessage($source): ?PartInterface
	{
		$this->setSource($source);
		$message = new Message($this->optional('part') ?: []);

		// Consider this check a "battle-tested safety net". I have no idea how to
		// trigger it anymore because I fixed all the bugs that cause it.
		// @codeCoverageIgnoreStart
		if (!$this->eof()) {
			return $this->error('end of document');
		}
		// @codeCoverageIgnoreEnd

		return $message;
	}

	/**
	 * Parse a message as a string contained entirely within RAM
	 */
	public function parseMessageString(string $str): ?PartInterface
	{
		$temp = fopen('php://temp', 'rw');
		fwrite($temp, $str);
		rewind($temp);
		try {
			return $this->parseMessage($temp);
		} finally {
			fclose($temp);
		}
	}


	// --------------------------------------------------
	//  BODY
	// --------------------------------------------------

	/**
	 * Works for both part + message
	 * message = (fields / obs-fields) [CRLF body]
	 */
	private function part(): ?array
	{
		// Headers
		$headers = $this->optional('headers');
		if (!$this->match('crlf')) {
			return $headers;
		}

		$body = [];

		// Extract env from headers
		$env = $this->extractEnv($headers ?: []);
		$oldEnv = $this->swapEnv($env);

		// Multipart?
		if ($env['boundary'] !== $oldEnv['boundary']) {
			$body = array_merge($body, $this->require('multipartBody'));
			$this->swapEnv($oldEnv);

			// Tricky epilogue b/c we have to reserve this CRLF if the body part
			// has no epilogue (i.e. the boundary appears directly after the CRLF).
			//
			// RFC 2045 § 5.1.1 ¶ 7
			// "The CRLF preceding the boundary delimiter line is conceptually
			// attached to the boundary so that it is possible to have a part that
			// does not end with a CRLF"
			//
			if ($this->env('boundary')) {
				$this->optional('bodyPartContent');
			}

			// Also if we have no further boundary, we should consume the remainder
			// of the message as the epilogue.
			//
			else {
				while ($this->match('octetLine', 'crlf'));
			}
		}

		// Body Part?
		else {
			if ($this->match('bodyPartContent')) {
				$body[] = $this->previous();
			}
			$this->swapEnv($oldEnv);
		}

		// Merge
		return array_merge($headers ?: [], $body ?: []) ?: null;
	}

	/**
	 * preamble = discard-text
	 */
	private function preamble(): ?BodyInterface
	{
		// No preamble
		if ($this->checkDashBoundary()) {
			return null;
		}

		// Empty but apparent preamble
		if ($this->checkDelimiter()) {
			return new Text(['']);
		}

		// Preamble content
		if ($this->match('bodyPartContent')) {
			return $this->previous();
		}

		// Nada
		return null;

		// Fixed this to avoid major memory usage
		// $final = null;
		// while (!$this->checkDelimiter() && !$this->eof()) {
		// 	if ($this->match('crlf')) {
		// 		$final .= $this->previous();
		// 		continue;
		// 	}
		// 	if ($this->match('octetLine')) {
		// 		$final .= $this->previous();
		// 	} else {
		// 		break;
		// 	}
		// return $final;
	}

	/**
	 * encapsulation := delimiter transport-padding CRLF body-part
	 *
	 * delimiter := CRLF dash-boundary
	 *
	 * close-delimiter := delimiter "--"
	 *
	 * transport-padding := *LWSP-char
	 *
	 * multipart-body := [preamble CRLF]
	 *  dash-boundary transport-padding CRLF
	 *  body-part *encapsulation
	 *  close-delimiter transport-padding
	 *  [CRLF epilogue]
	 */
	private function multipartBody(): ?array
	{
		$part = null;
		if ($this->match('preamble')) {
			$this->require('crlf');
		}
		$this->require('dashBoundary');
		$this->optional('wspRun');
		$this->require('crlf');
		$part[] = new Part($this->require('part'));
		while ($this->match('encapsulation')) {
			$part[] = new Part($this->previous());
		}
		$this->require('closeDelimiter');
		$this->match('transportPadding');
		return $part;
	}

	/**
	 * encapsulation := delimiter transport-padding CRLF body-part
	 */
	private function encapsulation(): ?array
	{
		$pos = $this->checkDelimiter();
		if (!$pos || $this->checkCloseDelimiterSuffix($pos)) {
			return null;
		}
		$this->advance($pos);
		$this->match('wspRun');
		$this->require('crlf');
		return $this->require('part');
	}

	/**
	 * RFC 2046
	 * body-part := MIME-part-headers [CRLF *OCTET]
	 * delimiter := CRLF dash-boundary
	 *
	 * RFC 2046 § 5.1.1 ¶ 6
	 * "The boundary delimiter MUST occur at the beginning of a line"
	 *
	 * RFC 2046 § 5.1.1 ¶ 6
	 * "The initial CRLF is considered to be attached to the boundary delimiter
	 * line rather than part of the preceding part"
	 *
	 * Note this loads the body content into a stream for memory efficiency.
	 * If the stream contents have fewer than $bufMaxSize bytes, this will return
	 * a Text, otherwise it will return a Resource.
	 */
	private function bodyPartContent(): ?BodyInterface
	{
		$len = 0;
		$codec = null;
		$final = null;
		$encStream = null;
		$boundary = $this->env('boundary');
		$encoding = $this->env('encoding');
		$delimiter = "\r\n--" . $boundary;
		while (!($boundary && $this->checkRange(0, $delimiter)) && !$this->eof()) {
			if (!$encStream) {
				$encStream = fopen('php://temp', 'rw');
			}
			if ($this->match('crlf')) {
				$len += fwrite($encStream, $this->previous());
				continue;
			}
			if ($this->match('octetLine')) {
				$len += fwrite($encStream, $this->previous());
			}
		}
		if ($len) {

			// Rewind receiver
			rewind($encStream);

			// Determine which codec to use
			if ($encoding) {
				$codec = $this->getCodec($encoding);
			}

			// Decode the stream
			if ($codec) {
				$dec = $codec->decodeStream($encStream);
				$decStream = fopen('php://temp', 'rw');
				foreach ($dec as $decPart) {
					fwrite($decStream, $decPart);
				}
				rewind($decStream);
			} else {
				$decStream = $encStream;
			}

			// Store in a stream?
			if ($len > $this->bufMaxSize) {
				$final = new Resource($decStream);
			} else {
				$final = new Text([ stream_get_contents($decStream) ]);
			}
		}
		if ($encStream) {
			fclose($encStream);
		}
		return $final;
	}

	/**
	 * Read up to a full line of octets (not including the CRLF)
	 */
	private function octetLine(): ?string
	{
		$pos = strpos($this->buf, "\r", $this->bufPos);
		if ($pos === false) {
			$len = $this->bufLen - $this->bufPos;
		} else {
			$len = $pos - $this->bufPos;
		}
		return $len ? $this->advance($len) : null;
	}

	/**
	 * '--' boundary
	 */
	private function dashBoundary(): ?string
	{
		$expected = '--' . $this->env('boundary');
		$len = strlen($expected);
		if ($this->peek(0, $len) === $expected) {
			return $this->advance($len);
		}
		return null;
	}

	/**
	 * close-delimiter = delimiter "--"
	 */
	private function closeDelimiter(): ?string
	{
		$delim = $this->checkDelimiter();
		if ($delim) {
			if ($suffix = $this->checkCloseDelimiterSuffix($delim)) {
				return $this->advance($delim + $suffix);
			}
		}
		return null;
	}

	/**
	 * transport-padding := *LWSP-char
	 */
	private function transportPadding(): string
	{
		return $this->wspRun() ?: '';
	}


	// --------------------------------------------------
	//  HEADERS
	// --------------------------------------------------

	/**
	 * Note that `version` has no CRLF built-in. The CRLF at the end of
	 * MIME-message-headers seems confusing in that way, since it does NOT mark
	 * the end of the headers— it merely marks the end of the version header.
	 *
	 * MIME-message-headers := entity-headers fields version CRLF
	 * MIME-part-headers := entity-headers [fields]
	 */
	private function headers(): ?array
	{
		$headers = [];
		while ($this->match('header')) {
			$headers[] = $header = $this->previous();
		}
		return $headers ?: null;
	}

	/**
	 * optional-field = field-name ":" unstructured CRLF
	 * field-name = 1*ftext
	 */
	private function header(): ?HeaderInterface
	{
		if (!$this->match('headerName')) {
			return null;
		}
		$name = $this->previous();
		$this->optional('wsp'); // due to obs-* fields
		$this->require(':');
		$this->optional('cfws');
		$value = $this->headerValue($name);
		switch (strtolower($name)) {
			case 'content-transfer-encoding':
				$header = new ContentTransferEncoding($value);
				break;
			case 'content-type':
				$params = $this->headerParams() ?: [];
				$header = new ContentType($value[0], $value[1], $params);
				break;
			case 'mime-version':
				$header = new MimeVersion($value->getMajor(), $value->getMinor());
				break;
			default:
				$header = new Header($name, $value);
				break;
		}
		$this->require('crlf');
		return $header;
	}

	/**
	 * field-name = 1*ftext
	 */
	private function headerName(): ?string
	{
		$name = null;
		while (self::isFText($this->peek())) {
			$name .= $this->advance();
		}
		return $name;
	}

	/**
	 * Many different rules covered here.
	 */
	private function headerValue(string $name)
	{
		switch (strtolower($name)) {
			case 'bcc': return $this->require('addressList');
			case 'cc': return $this->require('addressList');
			case 'content-id': return $this->require('messageIdList');
			case 'content-transfer-encoding': return $this->require('token');
			case 'content-type': return $this->require('mediaType');
			case 'date': return $this->require('dateTime');
			case 'from': return $this->require('mailboxList');
			case 'in-reply-to': return $this->require('messageIdList');
			case 'keywords': return $this->require('keywordList');
			case 'message-id': return $this->require('messageIdList');
			case 'mime-version': return $this->require('version');
			case 'references': return $this->require('messageIdList');
			case 'reply-to': return $this->require('addressList');
			case 'resent-bcc': return $this->require('addressList');
			case 'resent-cc': return $this->require('addressList');
			case 'resent-date': return $this->require('dateTime');
			case 'resent-message-id': return $this->require('messageIdList');
			case 'resent-sender': return $this->require('mailboxList');
			case 'resent-to': return $this->require('addressList');
			case 'resent-to': return $this->require('addressList');
			case 'sender': return $this->require('mailboxList');
			case 'to': return $this->require('addressList');
			default: return $this->unstructured();
		}
	}

	/**
	 * *(";" parameter)
	 * parameter := attribute "=" value
	 * attribute := token
	 * value := token / quoted-string
	 *
	 * NB. The official RFC 2045 does not allow spaces via ABNF, but indicates
	 * in the prose in § 5.1 to follow RFC 822 structured header value whitespace
	 * and comment rules.
	 *
	 * RFC 822 § 3.2
	 * field-body  =  field-body-contents [CRLF LWSP-char field-body]
	 * field-body-contents =
	 *   <the ASCII characters making up the field-body, as  defined in the
	 *   following sections, and consisting  of combinations of atom,
	 *   quoted-string, and  specials tokens, or else consisting of texts>
	 */
	private function headerParams(): ?array
	{
		$params = null;
		while ($this->match(';')) {
			$this->optional('cfws');
			if ($this->match('token')) {
				$name = $this->previous();
				$this->optional('cfws');
				$this->require('=');
				$this->optional('cfws');
				$params[$name] = $this->require('value');
				$this->optional('cfws');
			}
		}
		return $params;
	}

	/**
	 * value := token / quoted-string
	 */
	private function value(): ?string
	{
		if ($this->match('token', 'quotedString')) {
			return $this->previous();
		}
		return null;
	}


	// --------------------------------------------------
	//  HEADER ELEMENTS
	// --------------------------------------------------

	/**
	 * address-list = (address *("," address)) / obs-addr-list
	 * obs-addr-list = *([CFWS] ",") address *("," [address / CFWS])
	 *
	 * Note: using very forgiving parsing here as it will allow ,,,, etc
	 */
	private function addressList(): ?array
	{
		$addressList = null;
		while ($this->match('cfws', ',', 'address')) {
			if ($this->previousType() === 'address') {
				$addressList[] = $this->previous();
			}
		}
		return $addressList;
	}

	/**
	 * address = mailbox / group
	 * mailbox = name-addr / addr-spec
	 * group = display-name ":" [group-list] ";" [CFWS]
	 * name-addr = [display-name] angle-addr
	 */
	private function address(): ?ElementInterface
	{
		if ($this->match('displayName')) {

			// Could be group, mailbox, or local-part at this point since they all
			// basically start with display-name (except local-part allows dot-atom)
			$name = $this->previous();

			// group
			if ($this->match(':')) {
				$groupList = $this->optional('groupList');
				$group = new Group($name, $groupList ?: []);
				$this->require(';');
				$this->optional('cfws');
				return $group;
			}

			// addr-spec
			if ($this->match('@')) {
				$localPart = $name;
				$name = '';
				$domain = $this->require('domain');
				return new Mailbox($name, $localPart, $domain);
			}

			// Mailbox (name-addr)
			[$localPart, $domain] = $this->require('angleAddr');
			return new Mailbox($name, $localPart, $domain);
		}

		return null;
	}

	/**
	 * addr-spec = local-part "@" domain
	 */
	private function addrSpec(): ?array
	{
		if (!$this->match('localPart')) {
			return null;
		}
		$localPart = $this->previous();
		$this->require('@');
		$domain = $this->require('domain');
		return [$localPart, $domain];
	}

	/**
	 * angle-addr = [CFWS] "<" addr-spec ">" [CFWS] / obs-angle-addr
	 * obs-angle-addr = [CFWS] "<" obs-route addr-spec ">" [CFWS] TODO
	 */
	private function angleAddr(): ?array
	{
		$pos = $this->checkCfws();
		if ($this->peek($pos) !== '<') {
			return null;
		}
		$this->optional('cfws');
		$this->require('<');
		$ret = $this->require('addrSpec');
		$this->require('>');
		$this->optional('cfws');
		return $ret;
	}

	/**
	 * Let PHP parse this for ultimate convenience for everyone
	 */
	private function dateTime(): ?DateTimeInterface
	{
		$date = null;
		while ($this->match('cfws', 'vcharRun')) {
			$date .= $this->previous();
		}
		return $date ? new DateTimeImmutable($date) : null;
	}

	/**
	 * display-name = phrase
	 */
	private function displayName(): ?string
	{
		return $this->phrase();
	}

	/**
	 * domain = dot-atom / domain-literal / obs-domain
	 * obs-domain = atom *("." atom)
	 */
	private function domain(): ?string
	{
		if ($this->match('dotAtom', 'domainLiteral')) {
			return $this->previous();
		}
		return null;
	}

	/**
	 * domain-literal = [CFWS] "[" *([FWS] dtext) [FWS] "]" [CFWS]
	 */
	private function domainLiteral(): ?string
	{
		$domain = null;
		$pos = $this->checkCfws();
		if ($this->peek($pos) !== '[') {
			return null;
		}
		$this->advance($pos);
		$this->require('[');
		while ($this->match('dtextRun', 'fws')) {
			$domain .= $this->previous();
		}
		$this->require(']');
		$this->optional('cfws');
		return $domain;
	}

	/**
	 * 1*dtext
	 */
	private function dtextRun(): ?string
	{
		$pos = 0;
		$global = $this->env('global') ?? true;
		while (self::isDText($this->peek($pos), $global)) {
			$pos++;
		}
		return $pos ? $this->advance($pos) : null;
	}

	/**
	 * group-list = mailbox-list / CFWS / obs-group-list
	 * obs-group-list = 1*([CFWS] ",") [CFWS] -- NB wtf?
	 */
	private function groupList(): ?array
	{
		if ($this->match('mailboxList')) {
			return $this->previous();
		} elseif ($this->match('cfws')) {
			return [];
		}
		return null;
	}

	/**
	 * local-part = dot-atom / quoted-string / obs-local-part
	 * obs-local-part = word *("." word)
	 */
	private function localPart(): ?string
	{
		if ($this->match('quotedString', 'dotAtom')) {
			return $this->previous();
		}
		return null;
	}

	/**
	 * mailbox = name-addr / addr-spec
	 * name-addr = [display-name] angle-addr
	 */
	private function mailbox(): ?MailboxInterface
	{
		// name-addr
		if (!$this->match('displayName')) {
			return null;
		}

		// No checkPhrase() because local-part = display-name (with obs-*)
		$name = $this->previous();
		if ($this->match('@')) {
			$localPart = $name;
			$name = '';
			$domain = $this->require('domain');
		} else {
			[$localPart, $domain] = $this->require('angleAddr');
		}

		return new Mailbox($name, $localPart, $domain);
	}

	/**
	 * mailbox-list = (mailbox *("," mailbox)) / obs-mbox-list
	 * obs-mbox-list = *([CFWS] ",") mailbox *("," [mailbox / CFWS])
	 */
	private function mailboxList(): ?array
	{
		$list = null;

		// Modern syntax
		if ($this->match('mailbox')) {
			$list[] = $this->previous();
		}

		// Obsolete syntax
		// Must start with either a mailbox or *([CFWS] ",") mailbox
		else {
			$foundComma = false;
			$pos = $this->checkCfws();
			while ($this->peek($pos) === ',') {
				$pos++;
				$pos += $this->checkCfws($pos);
				$foundComma = true;
			}
			if (!$foundComma) {
				return null;
			}
			$this->advance($pos);
			$list[] = $this->require('mailbox');
		}

		// Round up the rest of the mailboxes
		// using both modern & obsolete syntaxes
		while ($this->match('mailbox', 'cfws', ',')) {
			if ($this->previousType() === 'mailbox') {
				$list[] = $this->previous();
			}
		}
		return $list;
	}

	/**
	 * type "/" subtype
	 * type := discrete-type / composite-type
	 * discrete-type := "text" / "image" / "audio" / "video" / "application" /
	 *   extension-token
	 * extension-token := ietf-token / x-token
	 * composite-type := "message" / "multipart" / extension-token
	 *
	 * NB. I added optional cfws to the end of the media type to comply with the
	 * prose in RFC 2045 § 5.1, "In addition, comments are allowed in accordance
	 * with RFC 822 rules for structured header fields."
	 */
	private function mediaType(): ?array
	{
		if (!$this->match('token')) {
			return null;
		}
		$type = $this->previous();
		$this->require('/');
		$subtype = $this->require('token');
		$this->optional('cfws');
		return [$type, $subtype];
	}

	/**
	 * keywords = "Keywords:" phrase *("," phrase) CRLF
	 * obs-phrase-list = [phrase / CFWS] *("," [phrase / CFWS])
	 *
	 * NB obs-phrase-list is moot b/c phrase has [CFWS] surrounding both its
	 * potential constitituents (quoted-string and dot-atom (due to obs-phrase))
	 */
	private function keywordList(): ?array
	{
		if (!$this->match('phrase')) {
			return null;
		}
		$keywords = [new Keyword($this->previous())];
		while ($this->match(',')) {
			if ($this->match('phrase')) {
				$keywords[] = new Keyword($this->previous());
			}
		}
		return $keywords;
	}

	/**
	 * 1*msg-id
	 */
	private function messageIdList(): ?array
	{
		$messageIds = null;
		while ($this->match('cfws', 'messageId')) {
			if ($this->previousType() === 'messageId') {
				$messageIds[] = $this->previous();
			}
		}
		return $messageIds;
	}

	/**
	 * msg-id = [CFWS] "<" id-left "@" id-right ">" [CFWS]
	 * id-left = dot-atom-text / obs-id-left
	 * id-right = dot-atom-text / no-fold-literal / obs-id-right
	 * no-fold-literal = "[" *dtext "]"
	 * obs-id-left = local-part
	 * obs-id-right = domain
	 */
	private function messageId(): ?MessageIdInterface
	{
		if (!$this->match('<')) {
			return null;
		}
		$left = $this->require('localPart');
		$this->require('@');
		$right = $this->require('domain');
		$this->require('>');
		return new MessageId($left, $right);
	}

	/**
	 * phrase = 1*word / obs-phras
	 * obs-phrase = word *(word / "." / CFWS)
	 *
	 * RFC 5322 § 3.2.2
	 * "Runs of FWS, comment, or CFWS that occur between lexical tokens in a
	 * structured header field are semantically interpreted as a single
	 * space character."
	 */
	private function phrase(): ?string
	{
		$final = null;
		$insertSpace = false;
		while (true) {
			$previous = $this->quotedString() ?? $this->dotAtom();
			if (is_null($previous)) {
				break;
			}
			if ($insertSpace) {
				$final .= ' ';
			}
			$final .= $previous;
			$insertSpace = $this->previousType() === 'cfws';
		}
		return $final;
	}

	/**
	 * unstructured = (*([FWS] VCHAR) *WSP) / obs-unstruct
	 * obs-unstruct = *((*LF *CR *(obs-utext *LF *CR)) / FWS)
	 * obs-utext = %d0 / obs-NO-WS-CTL / VCHAR
	 */
	private function unstructured(): string
	{
		$line = '';
		while($this->match('octetLine', 'fws')) {
			$line .= $this->previous();
		}
		return $line;
	}

	/**
	 * version = "MIME-Version" ":" 1*DIGIT "." 1*DIGIT
	 * N.B. Missing [CFWS]?
	 */
	private function version(): ?VersionInterface
	{
		if (!$this->match('number')) {
			return null;
		}
		$major = (int) $this->previous();
		$this->require('.');
		$minor = (int) $this->require('number');
		return new Version($major, $minor);
	}

	/**
	 * word = atom / quoted-string
	 */
	// private function word(): ?string
	// {
	// 	if (!$this->match('atom', 'quoted-string')) {
	// 		return null;
	// 	}
	// 	return $this->previous();
	// }


	// --------------------------------------------------
	//  BASICS
	// --------------------------------------------------

	/**
	 * atom = [CFWS] 1*atext [CFWS]
	 * atext = <printable ASCII excluding specials>
	 */
	// private function atom()
	// {
	// 	$final = null;
	// 	$pos = $this->checkCfws();
	// 	$global = $this->env('global') ?? true;
	// 	if (!self::isAText($this->peek($pos), $global)) {
	// 		return null;
	// 	}
	// 	$this->advance($pos);
	// 	$final = $this->require('dotAtomText');
	// 	$this->optional('cfws');
	// }

	/**
	 * 1*atext
	 */
	private function atextRun(): ?string
	{
		$atext = null;
		$global = $this->env('global') ?? true;
		while (self::isAText($this->peek(), $global)) {
			$atext .= $this->advance();
		}
		return $atext;
	}

	/**
	 * dot-atom = [CFWS] dot-atom-text [CFWS]
	 *
	 * RFC 5322 § 3.2.3 ¶ 4
	 * "Semantically, the optional comments and FWS surrounding the rest of the
	 * characters are not part of the atom; the atom is only the run of atext
	 * characters in an atom, or the atext and "." characters in a dot-atom."
	 */
	private function dotAtom(): ?string
	{
		$final = null;
		$pos = $this->checkCfws();
		$global = $this->env('global') ?? true;
		if (!self::isAText($this->peek($pos), $global)) {
			return null;
		}
		$this->advance($pos);
		$final .= $this->require('dotAtomText');
		$this->optional('cfws');
		return $final;
	}

	/**
	 * dot-atom-text = 1*atext *("." 1*atext)
	 */
	private function dotAtomText(): ?string
	{
		if (!$this->match('atextRun')) {
			return null; // @codeCoverageIgnore
		}
		$final = $this->previous();
		while ($this->match('atextRun', '.')) {
			$final .= $this->previous();
		}
		if ($this->previousType() === '.') {
			return $this->error('atext');
		}
		return $final;
	}

	/**
	 * 1*DIGIT
	 */
	private function number(): ?string
	{
		$number = null;
		while (self::isDigit($this->peek())) {
			$number .= $this->advance();
		}
		return $number;
	}

	/**
	 * quoted-string = [CFWS] DQUOTE *([FWS] qcontent) [FWS] DQUOTE [CFWS]
	 *
	 * RFC 5322 § 3.2.4 ¶ 3
	 * "...the CRLF in any FWS/CFWS that appears within the quoted-string are
	 * semantically "invisible" and therefore not part of the quoted-string..."
	 */
	private function quotedString(): ?string
	{
		$final = null;
		$pos = $this->checkCfws();
		if ($this->peek($pos) !== '"') {
			return null;
		}
		if ($pos) {
			$this->advance($pos);
		}
		$this->require('"');
		while ($this->match('fws', 'qcontent')) {
			if ($this->previousType() === 'qcontent') {
				$final .= $this->previous();
			}
		}
		$this->require('"');
		$this->optional('cfws');
		return $final;
	}

	/**
	 * qcontent = qtext / quoted-pair
	 */
	private function qcontent(): ?string
	{
		$q = null;
		while ($this->match('quotedPair', 'qtextRun')) {
			$q .= $this->previous();
		}
		return $q;
	}

	/**
	 * quoted-pair = ("\" (VCHAR / WSP)) / obs-qp
	 */
	private function quotedPair(): ?string
	{
		if ($this->match('\\')) {
			return $this->require('wsp', 'vchar');
		}
		return null;
	}

	/**
	 * 1*qtext
	 */
	private function qtextRun(): ?string
	{
		$global = $this->env('global') ?? true;
		$pos = 0;
		while (self::isQText($this->peek($pos), $global)) {
			$pos++;
		}
		return $pos ? $this->advance($pos) : null;
	}

	/**
	 * token := 1*<any (US-ASCII) CHAR except SPACE, CTLs, or tspecials>
	 */
	private function token(): ?string
	{
		$token = null;
		$global = (bool) $this->env('global');
		while (Lexeme::isToken($this->peek(), $global)) {
			$token .= $this->advance();
		}
		return $token;
	}

	/**
	 * VCHAR = %x21-7E
	 */
	private function vchar(): ?string
	{
		$global = $this->env('global') ?? true;
		if (self::isVChar($this->peek(), $global)) {
			return $this->advance();
		}
		return null;
	}

	/**
	 * x-vchar-run = *VCHAR
	 */
	private function vcharRun(): ?string
	{
		$pos = 0;
		$global = $this->env('global') ?? true;
		while (self::isVChar($this->peek($pos), $global)) {
			$pos++;
		}
		return $pos ? $this->advance($pos) : null;
	}


	// --------------------------------------------------
	//  WHITESPACE
	// --------------------------------------------------

	/**
	 * CFWS = (1*([FWS] comment) [FWS]) / FWS
	 *
	 * RFC 2045 § 3.2.2 ¶ 6
	 * "Runs of FWS, comment, or CFWS that occur between lexical tokens in a
	 * structured header field are semantically interpreted as a single space
	 * character."
	 */
	private function cfws(): ?string
	{
		$len = $this->checkCfws();
		if ($len) {
			$this->advance($len);
			return ' ';
		}
		return null;
	}

	/**
	 * crlf = CR LF
	 */
	private function crlf(): ?string
	{
		if ($this->peek(0, 2) === "\r\n") {
			return $this->advance(2);
		}
		return null;
	}

	/**
	 * FWS = ([*WSP CRLF] 1*WSP) / obs-FWS
	 * obs-FWS = 1*WSP *(CRLF 1*WSP)
	 */
	private function fws(): ?string
	{
		$len = $this->checkFws();
		if ($len) {
			$this->advance($len);
			return ' ';
		}
		return null;
	}

	/**
	 * WSP = SP / HTAB
	 */
	private function wsp(): ?string
	{
		if ($this->match(' ', "\t")) {
			return $this->previous();
		}
		return null;
	}

	/**
	 * 1*WSP
	 */
	private function wspRun(): ?string
	{
		$pos = $this->checkWspRun();
		return $pos ? $this->advance($pos) : null;
	}


	// --------------------------------------------------
	//  SPECIAL PEEKS
	// --------------------------------------------------

	/**
	 * CFWS = (1*([FWS] comment) [FWS]) / FWS
	 */
	private function checkCfws(int $start = 0): int
	{
		$pos = $start;
		$len = 0;
		while ($adv = $this->checkFws($pos) ?: $this->checkComment($pos)) {
			$len += $adv;
			$pos += $adv;
		}
		return $len;
	}

	/**
	 * FWS = ([*WSP CRLF] 1*WSP) / obs-FWS
	 * obs-FWS = 1*WSP *(CRLF 1*WSP)
	 */
	private function checkFws(int $start = 0): int
	{
		$pos = $start;
		$wsp = 0;
		while (self::isWsp($this->peek($pos))) {
			$wsp++;
			$pos++;
		}
		if ($this->peek($pos, 2) === "\r\n") {
			$fws = 0;
			$pos += 2;
			while (self::isWsp($this->peek($pos))) {
				$fws++;
				$pos++;
			}
			if ($fws) {
				return $fws + 2;
			}
		}
		if ($wsp) {
			return $wsp;
		}
		return 0;
	}

	/**
	 * *WSP
	 */
	private function checkWspRun(int $start = 0): int
	{
		$pos = $start;
		$len = 0;
		while (self::isWsp($this->peek($pos))) {
			$len++;
			$pos++;
		}
		return $len;
	}

	/**
	 * comment = "(" *([FWS] ccontent) [FWS] ")"
	 */
	private function checkComment(int $start = 0): int
	{
		$pos = $start;
		$len = 0;
		if ($this->peek($pos) === '(') {
			$pos += 1;
			$len += 1;
			while ($ccont = $this->checkCContent($pos) ?: $this->checkFws($pos)) {
				$pos += $ccont;
				$len += $ccont;
			}
			if ($this->peek($pos) !== ')') {
				return 0;
			} else {
				$pos += 1;
				$len += 1;
			}
		}
		return $len;
	}

	/**
	 * ccontent = ctext / quoted-pair / comment
	 */
	private function checkCContent(int $start = 0): int
	{
		$len = 0;
		$pos = $start;
		$content = null;
		while (
			$adv =
				$this->checkComment($pos) ?:
				$this->checkQuotedPair($pos) ?:
				$this->checkCText($pos)
		) {
			$len += $adv;
			$pos += $adv;
		}
		return $len;
	}

	/**
	 * quoted-pair = "\" (VCHAR / WSP)
	 */
	private function checkQuotedPair(int $start = 0): int
	{
		if ($this->peek($start) !== '\\') {
			return 0;
		}
		$next = $this->peek($start + 1);
		return self::isVChar($next) || self::isWsp($next) ? 2 : 0;
	}

	/**
	 * ctext = <printable ASCII except "(", ")", or "\">
	 * ctext = %d33-39 / %d42-91 / %d93-126
	 */
	private function checkCText(int $start = 0): int
	{
		$len = 0;
		$pos = $start;
		while ($this->isCText($this->peek($pos))) {
			$pos++;
			$len++;
		}
		return $len;
	}

	/**
	 * delimiter = CRLF dash-boundary
	 */
	private function checkDashBoundary(int $start = 0): int
	{
		$boundary = $this->env('boundary');
		$expected = "--" . $boundary;
		$expectedLen = strlen($expected);
		$len = 0;
		if ($this->peek($start, $expectedLen) === $expected) {
			return $expectedLen;
		}
		return 0;
	}

	/**
	 * delimiter = CRLF dash-boundary
	 */
	private function checkDelimiter(int $start = 0): int
	{
		$boundary = $this->env('boundary');
		$expected = "\r\n--" . $boundary;
		$expectedLen = strlen($expected);
		$len = 0;
		if ($this->peek($start, $expectedLen) === $expected) {
			return $expectedLen;
		}
		return 0;
	}

	/**
	 * close-delimiter = delimiter "--"
	 */
	private function checkCloseDelimiterSuffix(int $start = 0): int
	{
		return $this->peek($start, 2) === '--' ? 2 : 0;
	}

	/**
	 * Check for an arbitrary string
	 */
	private function checkRange(int $start = 0, string $str): int
	{
		$len = strlen($str);
		return $this->peek($start, $len) === $str ? $len : 0;
	}


	// --------------------------------------------------
	//  ENV
	// --------------------------------------------------

	/**
	 * Get an entry from the current env
	 */
	private function env(string $key)
	{
		return $this->env[$key] ?? null;
	}

	/**
	 * Swap the current env for another env.
	 */
	private function swapEnv(array $env = []): ?array
	{
		$oldEnv = $this->env;
		$this->env = $env;
		// echo "ENV: ";
		// print_r($env);
		return $oldEnv;
	}

	/**
	 * Extract the part environment from parsed headers
	 */
	private function extractEnv(array $headers = []): array
	{
		$env = $this->env;
		foreach ($headers as $header) {
			if ($header instanceof ContentType) {
				if ($header->getParam('boundary')) {
					$env['boundary'] = $header->getParam('boundary');
				}
				$env['charset'] = $header->getParam('charset') ?? self::DEFAULT_CHARSET;
				$env['global'] = $env['global'] || $header->isMessageGlobal();
			} elseif ($header instanceof ContentTransferEncoding) {
				if ($header->getEncoding()) {
					$env['encoding'] = $header->getEncoding();
				}
			}
		}
		return $env;
	}


	// --------------------------------------------------
	//  API
	// --------------------------------------------------

	/**
	 * Create an exception
	 */
	private function createException(
		string $message = '',
		int $code = 0,
		?Throwable $previous = null
	): Throwable
	{
		return new RuntimeException($message, $code, $previous);
	}

	/**
	 * Set the source you want to parse
	 */
	private function setSource($source, string $sourceType = 'message'): void
	{
		$this->source = $source;
		$this->sourceType = $sourceType;
		$this->previous = null;
		$this->previousType = null;
		$this->buf = null;
		$this->bufLen = 0;
		$this->bufPos = 0;
		$this->bufMaxSizeReached = 0;
		$this->bufMaxSizeAdjustments = 0;
		$this->env = [
			'charset' => self::DEFAULT_CHARSET,
			'encoding' => self::DEFAULT_TRANSFER_ENCODING,
			'global' => null,
			'boundary' => null,
		];
		$this->stack = [];
	}

	/**
	 * Get the correct codec given an encoding
	 * Note this will not return the Identity codec since it has no effect.
	 */
	private function getCodec(string $encoding = '7bit'): ?CodecInterface
	{
		if (isset($this->codecs[$encoding])) {
			return $this->codecs[$encoding];
		}
		switch ($encoding) {
			case ContentTransferEncoding::ENCODING_BASE64:
				return $this->codecs[$encoding] = new Base64();
			case ContentTransferEncoding::ENCODING_QP:
				return $this->codecs[$encoding] = new QuotedPrintable();
		}
		return null;
	}


	// --------------------------------------------------
	//  HELPERS
	// --------------------------------------------------

	/**
	 * atext = ALPHA / DIGIT /  "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" /
	 *   "-" / "/" / "=" / "?" / "^" / "_" / "`" / "{" / "|" / "}" / "~"
	 */
	private static function isAText(string $char, bool $global = false): bool
	{
		return Lexeme::isAText($char, $global);
	}

	/**
	 * ctext = <printable ASCII except "(", ")", or "\">
	 * ctext = %d33-39 / %d42-91 / %d93-126
	 */
	private static function isCText(string $char, bool $global = false): bool
	{
		$ord = ord($char);
		return
			($ord >= 33 && $ord <= 39) ||
			($ord >= 42 && $ord <= 91) ||
			($ord >= 93 && $ord <= 126) ||
			($global && $ord > 127)
		;
	}

	/**
	 * dtext = %d33-90 / %d94-126 / obs-dtext
	 *   ; printable ASCII except "[", "]", and "\"
	 * obs-dtext = obs-NO-WS-CTL / quoted-pair TODO
	 */
	private static function isDText(string $char, bool $global = false): bool
	{
		$ord = ord($char);
		return
			($ord >= 33 && $ord <= 90) ||
			($ord >= 94 && $ord <= 126) ||
			($global && $ord > 127)
		;
	}

	/**
	 * 0-9
	 */
	private static function isDigit(string $char): bool
	{
		$ord = ord($char);
		return ($ord >= 48 && $ord <= 57);
	}

	/**
	 * ftext = %d33-57 / %d59-126
	 */
	private static function isFText(string $char): bool
	{
		$ord = ord($char);
		return ($ord >= 33 && $ord <= 57) || ($ord >= 59 && $ord <= 126);
	}

	/**
	 * qtext = %d33 / %d35-91 / %d93-126 / obs-qtext
	 * 	; printable ASCII not including "\" or DQUOTE
	 * obs-qtext = obs-NO-WS-CTL
	 * obs-NO-WS-CTL = %d1-8 / %d11 / %d12 / %d14-31 / %d127
	 */
	private static function isQText(string $char, bool $global = false): bool
	{
		$ord = ord($char);
		return
			($ord > 0) &&
			($char !== '"' && $char !== '\\') &&
			($global || $ord <= 127)
		;
	}

	/**
	 * VCHAR = %x21-7E
	 */
	private static function isVChar(string $char, bool $global = false): bool
	{
		$ord = ord($char);
		if ($ord < 0x21) {
			return false;
		}
		if (!$global && ($ord > 126)) {
			return false;
		}
		if ($ord === 127) {
			return false;
		}
		return true;
	}

	/**
	 * WSP = HT / SP
	 */
	private static function isWsp(string $char): bool
	{
		return $char === ' ' || $char === "\t";
	}


	// --------------------------------------------------
	//  HELPERS
	// --------------------------------------------------

	/**
	 * Keep track of the previous token
	 * Internal use only. Do not use this. In fact, get rid of it.
	 */
	private function consume(
		string $type = 'default',
		int $n = 1,
		?string $value = null
	): ?string
	{
		$lexeme = $n > 0 ? $this->advance($n) : '';
		$this->previous = is_null($value) ? $lexeme : $value;
		$this->previousType = $type;
		return $this->previous;
	}

	/**
	 * Advance the cursor forward
	 */
	private function advance(int $n = 1): ?string
	{
		$expectedLen = $n;
		$fullLen = 0;
		$lexeme = '';
		while ($n > 0) {

			// Read chunk from the buffer
			$chunk = substr($this->buf, $this->bufPos, $n);
			$len = strlen($chunk);
			$fullLen += $len;
			$this->bufPos += $len;
			$this->pos += $len;
			$n -= $len;

			// Consume beyond buffer?
			// Again, I have never seen this error happen and no idea how.
			// @codeCoverageIgnoreStart
			if ($fullLen < $expectedLen) {
				if ($this->eof() || feof($this->source)) {
					return $this->error('octet');
				}
			}
			// @codeCoverageIgnoreEnd

			// Next buffer?
			if ($this->bufPos >= $this->bufLen) {
				$this->readNextBuffer();
			}

			// Append chunk
			$lexeme .= $chunk;
		}
		return $lexeme;
	}

	/**
	 * Check current token
	 */
	private function check(string $str, int $offset = 0): bool
	{
		return $this->peek($offset, strlen($str)) === $str;
	}

	/**
	 * Log an error
	 */
	private function error(string $expected): void
	{
		$start = max(0, $this->bufPos - 10);
		$pad = $this->bufPos - $start;
		$excerpt = preg_replace(
			'/\s/',' ',
			substr($this->buf, max(0, $this->bufPos - 10), 20)
		);
		$token = $this->eof() ? 'EOF' : $this->peek();
		// echo "ERROR: $expected $token $start $pad $excerpt\n";
		throw $this->createException(
			'Expected ' . $expected . "\n" .
			'Unexpected ' . json_encode($token) . ' at char ' . $this->pos . ".\n" .
			'Near \'' . $excerpt . "'\n" .
			'      ' . str_pad(' ', $pad) . '^' . "\n" .
			'Stack:' . "\n" . $this->renderStack($this->stack),
		);
	}

	/**
	 * Render the current parser stack for error messages
	 */
	private function renderStack(array $stack): string
	{
		$out = [];
		$rstack = array_reverse($stack);
		foreach ($rstack as $id => [$pos, $type]) {
			$out[] = sprintf('%6d: %s', $pos, $type);
		}
		return implode("\n", $out);
	}

	/**
	 * Whether we're currently at the end of the source
	 */
	private function eof(): bool
 	{
 		return $this->bufLen === 0;
 	}

	/**
	 * Check if the first peek token matches a set of types.
	 * If it does, consume the token and return it.
	 */
	private function match(...$types): bool
	{
		foreach ($types as $type) {

			// Lexeme
			if (isset($type[1])) {

				// echo str_repeat('.', count($this->stack));
				// echo "❔ $type\n";
				$this->stack[] = [$this->pos, $type];
				$match = ([$this, $type])();
				array_pop($this->stack);
				if (!is_null($match)) {
					// echo str_repeat('.', count($this->stack));
					// echo "⚪ $type=";
					// echo is_object($match) || is_array($match) ? gettype($match) : json_encode($match);
					// echo "\n";
					$this->previousType = $type;
					$this->previous = $match;
					return true;
				} else {
					// echo str_repeat('.', count($this->stack));
					// echo "⚫ $type\n";
				}
			}

			// Single char
			else {

				// echo str_repeat('.', count($this->stack));
				// echo "⚫ char=" . json_encode($type) . "\n";

				if ($this->check($type)) {
					$this->consume($type, 1);
					// echo str_repeat('.', count($this->stack));
					// echo "🌲 char=" . json_encode($type) . "\n";
					return true;
				} else {
					// echo str_repeat('.', count($this->stack));
					// echo "⚫ char=" . json_encode($type) . "\n";
				}
			}
		}
		return false;
	}

	/**
	 * Optional match, returning the matched token
	 */
	private function optional(...$types)
	{
		return $this->match(...$types) ? $this->previous() : null;
	}

	/**
	 * Load the next buffer after exhuasting the current buffer
	 */
	private function readNextBuffer()
	{
		$this->buf = fread($this->source, $this->bufReadSize);
		$this->bufLen = strlen($this->buf);
		$this->bufPos = 0;
	}

	/**
	 * Require one of the types or throw an error
	 */
	private function require(...$types)
	{
		if (!$this->match(...$types)) {
			return $this->error(implode(', ', $types));
		}
		return $this->previous();
	}

	/**
	 * Peek a number of characters ahead
	 */
	private function peek($offset = 0, $len = 1): string
	{
		// Burstable
		while ($this->bufPos + $offset + $len >= $this->bufLen) {
			if (!is_resource($this->source) || feof($this->source)) {
				break;
			}

			// Handle max size
			if ($this->bufMaxSize) {
				if ($this->bufLen + $this->bufPeekSize > $this->bufMaxSize) {
					if (($this->bufLen - $this->bufPos) + $this->bufPeekSize > $this->bufMaxSize) {
						throw new OverflowException(
							'Required peek length exceeded max buffer size. Current buffer: ' . $this->buf
						);
					}
					$this->buf = substr($this->buf, $this->bufPos);
					$this->bufPos = 0;
					$this->bufLen = strlen($this->buf);
					$this->bufMaxSizeAdjustments++;
				}
			}

			$ext = fread($this->source, $this->bufPeekSize);
			$this->buf .= $ext;
			$this->bufLen = strlen($this->buf);

			$this->bufMaxSizeReached = max($this->bufMaxSizeReached, $this->bufLen);

			// if (strlen($ext) === 0) {
			// 	break;
			// }
		}

		return substr($this->buf, $this->bufPos + $offset, $len);
	}

	/**
	 * Previously consumed token
	 */
	private function previous()
	{
		return $this->previous;
	}

	/**
	 * Previously consumed token type
	 */
	private function previousType()
	{
		return $this->previousType;
	}

}
