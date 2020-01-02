<?php

namespace Virtu\Mime\Body;

interface BodyStreamInterface extends BodyInterface
{
	public function close(): void;

	public function getBufferLength(): int;
	public function setBufferLength(int $bufLength): void;

	public function getResource(); // : resource
}
