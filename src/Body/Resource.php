<?php

namespace Virtu\Mime\Body;

use Iterator;
use TypeError;

class Resource implements BodyStreamInterface, Iterator
{
	private $resource;
	private $initialized = false;
	private $bufLength = 8192;
	private $buf;
	private $pos = 0;

	public function __construct($resource)
	{
		if (!is_resource($resource)) {
			throw new TypeError(
				'You must pass a valid resource'
			);
		}
		$this->resource = $resource;
	}

	public function close(): void
	{
		if (is_resource($this->resource)) {
			fclose($this->resource);
		}
		$this->buf = null;
		$this->pos = 0;
	}

	public function getBufferLength(): int
	{
		return $this->bufLength;
	}

	public function setBufferLength(int $bufLength): void
	{
		$this->bufLength = $bufLength;
	}

	public function getResource() //: resource
	{
		return $this->resource;
	}

	private function initialize(): void
	{
		if (!$this->initialized) {
			$this->buf = null;
			$this->next();
			$this->pos = 0;
			$this->initialized = true;
		}
	}

	public function current(): mixed
	{
		$this->initialize();
		return $this->buf;
	}

	public function key(): mixed
	{
		return $this->pos;
	}

	public function next(): void
	{
		$this->buf = is_resource($this->resource)
			? fread($this->resource, $this->bufLength)
			: null
		;
		if ($this->buf === '' || $this->buf === false || $this->buf === null) {
			$this->buf = null;
		} else {
			$this->pos += 1;
		}
	}

	public function rewind(): void
	{
		$this->initialize();
		if (is_resource($this->resource)) {
			rewind($this->resource);
		}
		$this->buf = null;
		$this->next();
		$this->pos = 0;
	}

	public function valid(): bool
	{
		$this->initialize();
		return !is_null($this->buf);
	}
}
