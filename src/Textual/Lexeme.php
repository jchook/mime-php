<?php declare(strict_types=1);

namespace Virtu\Mime\Textual;

/**
 * Lexeme (n.) a sequence of characters in the source program that matches the
 * pattern for a token and is identified by the lexical analyzer as an instance
 * of that token.
 *
 * "Compilers Principles, Techniques, & Tools, 2nd Ed." (WorldCat)
 * by Aho, Lam, Sethi and Ullman (p. 111).
 */
class Lexeme
{
	/**
	 * Detect whether a string uses only 7-bit bytes
	 */
	public static function is7Bit(string $str): bool
	{
		return !preg_match('/[^\x00-\x7F]/', $str);
	}

	/**
	 * RFC 5322 § 3.2.3
	 * atom = [CFWS] 1*atext [CFWS]
	 * atext = ALPHA / DIGIT / "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" /
	 *   "-" / "/" / "=" / "?" / "^" / "_" / "`" / "{" / "|" / "}" / "~"
	 * specials = "(" / ")" / "<" / ">" / "[" / "]" / ":" / ";" / "@" / "\" /
	 *   "," / "." / DQUOTE
	 *
	 * RFC 822 § 3.4
	 * atom = 1*<any CHAR except specials, SPACE and CTLs>
	 * specials = "(" / ")" / "<" / ">" / "@" /  "," / ";" / ":" / "\" / <"> /
	 *   "." / "[" / "]"
	 */
	public static function isAText(string $str, bool $global = false): bool
	{
		if ($global) {
			return ! preg_match('/[\x00-\x1F\x7F()<>@.,;:\\\\"\[\] ]/', $str);
		}
		return ! preg_match('/[\x00-\x1F\x7F()<>@.,;:\\\\"\[\] \x80-\xFF]/', $str);
	}

	/**
	 * RFC 5322 § 3.2.3
	 * dot-atom-text = 1*atext *("." 1*atext)
	 */
	public static function isDotAtomText(string $str, bool $global = false): bool
	{
		$parts = explode('.', $str);
		if ($parts[0] === '') {
			return false;
		}
		foreach ($parts as $part) {
			if (!self::isAText($part, $global)) {
				return false;
			}
		}
		if ($part === '') {
			return false;
		}
		return true;
	}

	/**
	 * Can we safely create a quoted-string from the given text?
	 *
	 * RFC 5322 § 3.2.4
	 * quoted-string = [CFWS] DQUOTE *([FWS] qcontent) [FWS] DQUOTE
	 * qcontent = qtext / quoted-pair
	 * qtext = %d33 / %d35-91 / %d93-126
	 * quoted-pair = "\" (VCHAR / WSP)
	 *
	 * RFC 6532 § 3.2
	 * qtext =/ UTF8-non-ascii
	 */
	public static function isQuotable(string $str, bool $global = false): bool
	{
		if ($global) {
			// Forbid CTL
			return ! preg_match('/[\x00-\x1E\x7F]/', $str);
		}

		// Forbid CTL and 8-bit
		return ! preg_match('/[\x00-\x1E\x7F\x80-\xFF]/', $str);
	}

	/**
	 * RFC 2045 § 5.1 - Use `token` within header parameters.
	 * parameter = attribute "=" value
	 * attribute = token
	 * value = token / quoted-string
	 * token = 1*<Any US-ASCII CHAR except SPACE, CTLs, or tspecials
	 * tspecials =
	 *  "(" / ")" / "<" / ">" / "@" /
	 *  "," / ";" / ":" / "\" / <">
	 *  "/" / "[" / "]" / "?" / "="
	 *  ; Must be in quoted-string,
	 *  ; to use within parameter values
	 */
	public static function isToken(string $str, bool $global = false): bool
	{
		// forbid CTLs, SPACE, 8bit, and tspecials
		return ! preg_match('/[\x00-\x1F\x7F \x80-\xFF()<>@,;:\\\\"\/\[\]?=]/', $str);
	}

}

/*

RFC 5234 § Appendix B.1 Core Rules
WSP = SP / HTAB ; linear whitespace
VCHAR = %x21-7E ; visible chars

RFC 5322 § 3.2.2
FWS = ([*WSP CRLF] 1*WSP)
CFWS = (1*([FWS] comment) [FWS]) / FWS

*/
