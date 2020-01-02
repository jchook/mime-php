<?php

namespace Virtu\Mime\Body;

use Iterator;
use RuntimeException;

class File implements Iterator, FileInterface
{
	private $path;
	private $resource;
	private $bufLength = 8192;
	private $buf;
	private $pos = 0;

	public function __construct(string $path)
	{
		$this->path = $path;
	}

	public function getBufferLength(): int
	{
		return $this->bufLength;
	}

	public function setBufferLength(int $bufLength): void
	{
		$this->bufLength = $bufLength;
	}

	public function getPath(): string
	{
		return $this->path;
	}

	public function getName(): string
	{
		return basename($this->path);
	}

	public function close(): void
	{
		if ($this->resource) {
			fclose($this->resource);
		}
		$this->resource = null;
		$this->buf = null;
		$this->pos = 0;
	}

	public function getResource()
	{
		$this->initialize();
		rewind($this->resource);
		return $this->resource;
	}

	private function initialize(): void
	{
		if (!$this->resource) {
			$this->close();
			$this->resource = @fopen($this->path, 'r');
			if (!is_resource($this->resource)) {
				throw new RuntimeException('Failed to open file: ' . $this->path);
			}
			$this->next();
			$this->pos = 0;
		}
	}

	public function current() //: mixed
	{
		$this->initialize();
		return $this->buf;
	}

	public function key() //: scalar
	{
		return $this->pos;
	}

	public function next(): void
	{
		$this->initialize();
		$this->buf = fread($this->resource, $this->bufLength);
		if (empty($this->buf)) {
			$this->buf = null;
		}
		if ($this->valid()) {
			$this->pos += 1;
		}
	}

	public function rewind(): void
	{
		$this->initialize();
		$this->buf = null;
		if (is_resource($this->resource)) {
			rewind($this->resource);
			$this->next();
		}
		$this->pos = 0;
	}

	public function valid(): bool
	{
		$this->initialize();
		return !is_null($this->buf);
	}
}