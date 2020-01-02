<?php

namespace Virtu\Mime;

use Virtu\Mime\Body\BodyInterface;
use Virtu\Mime\Body\PartInterface;
use Virtu\Mime\Element\MediaType;
use Virtu\Mime\Element\Version;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\HeaderInterface;

use Generator;

/**
 *
 * Why have a MessageMaster?
 *
 * The Message class represents ordered details of a MIME document. It lacks
 * efficient, repeat access to details like specific headers, attachments, etc.
 *
 * The MessageMaster strategically extracts high-level information out of a
 * Message AST, for performance, conformance, inspection, and reproducibility.
 *
 * For example, you can reliably retrieve:
 * 	- Message headers by name
 * 	- Whether the part is multipart
 * 	- Body part tree, charsets, encodings, etc.
 *
 * The MessageMaster has a recursive structure, meaning it represents subparts
 * as MessageMaster instances as well.
 *
 */
class MessageMaster
{
	private $loaded;

	private $part;

	private $subparts;

	private $headers;

	private $bodies;

	private $verbatims;

	private $multipart;

	public function __construct(PartInterface $part, ?MessageMaster $parent = null)
	{
		$this->part = $part;
		$this->parent = $parent;
		$this->reset();
	}

	private function load(): void
	{
		if (!$this->loaded) {
			$this->reload();
		}
	}

	private function reset(): void
	{
		$this->loaded = false;
		$this->subparts = [];
		$this->headers = [];
		$this->bodies = [];
		$this->verbatims = [];
		$this->multipart = false;
	}

	private function reload(): void
	{
		$this->reset();

		foreach ($this->part as $child) {
			if ($child instanceof PartInterface) {
				$this->multipart = true;
				$this->subparts[] = new static($child, $this);
			} elseif ($child instanceof BodyInterface) {
				$this->bodies[] = $child;
			} elseif ($child instanceof HeaderInterface) {
				$this->headers[strtolower($child->getName())][] = $child;
			} elseif (is_string($child)) {
				$this->verbatims[] = $child;
			}
		}
	}

	public function getVersion(): ?Version
	{
		$header = $this->getHeader('mime-version');
		return $header
			? $header->getVersion()
			: null
		;
	}

	public function getMediaType(): MediaType
	{
		return $this->getContentType()->getValue()[0] ?? new MediaType();
	}

	public function getContentType(): HeaderInterface
	{
		return $this->getHeader('content-type') ?: new ContentType();
	}

	public function getContentTransferEncoding(): ContentTransferEncoding
	{
		$mine = $this->getHeader('content-transfer-encoding');
		if ($mine) return $mine;
		if ($parent = $this->getParent()) {
			return $parent->getContentTransferEncoding();
		} else {
			return new ContentTransferEncoding();
		}
	}

	public function getBodies(): array
	{
		$this->load();
		return $this->bodies;
	}

	public function getBodiesRecursive(): array
	{
		$bodies = [];
		$parts = [];
		$next = $this;
		while ($next) {
			array_push($parts, ...$next->getSubparts());
			array_push($bodies, ...$next->getBodies());
			$next = array_shift($parts);
		}
		return $bodies;
	}

	public function getHeader(string $name): ?HeaderInterface
	{
		$this->load();
		$name = strtolower($name);
		if (isset($this->headers[$name][0])) {
			return $this->headers[$name][0];
		}
		return null;
	}

	public function getHeaders(string $name): array
	{
		$this->load();
		$name = strtolower($name);
		return $this->headers[$name] ?? [];
	}

	public function getAllHeaders(): array
	{
		$this->load();
		$headers = [];
		foreach ($this->headers as $name => $list) {
			$headers = array_merge($headers, $list);
		}
		return $headers;
	}

	/**
	 * Breadth-first
	 */
	public function getAllHeadersRecursive(): array
	{
		$headers = [];
		$parts = [];
		$next = $this;
		while ($next) {
			array_push($parts, ...$next->getSubparts());
			array_push($headers, ...$next->getAllHeaders());
			$next = array_shift($parts);
		}
		return $headers;
	}

	/**
	 * Breadth-first
	 */
	public function getHeadersRecursive(string $name): array
	{
		$headers = [];
		$parts = [];
		$next = $this;
		while ($next) {
			array_push($parts, ...$next->getSubparts());
			array_push($headers, ...$next->getHeaders($name));
			$next = array_shift($parts);
		}
		return $headers;
	}


	public function getParent(): ?MessageMaster
	{
		return $this->parent;
	}

	public function getPart(): PartInterface
	{
		return $this->part;
	}

	public function getSubparts(): array
	{
		$this->load();
		return $this->subparts;
	}

	public function isMultipart(): bool
	{
		$this->load();
		return $this->multipart;
	}

	public function isMessageGlobal(): bool
	{
		$mt = $this->getMediaType();
		return $mt->getType() === 'message' && $mt->getSubtype() === 'global';
	}

}
