<?php

use Virtu\Mime\Textual\Parser;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$source = implode("\r\n", [
	'From: "Wes Roberts" <wes@wzap.org>',
	'To: , ',
	' Sam Scoville <sam@wzap.org> ,',
	' "Evan \"The Rock\" Wantland" <ewantland@[127.0.0.1]> (\(Senior Badass\))',
	' ,friends:;, family:maggie@wzap.org, Ben Chamberlin <bec@chamberlindesign.com',
	' >;',
	'Subject: Etymology and',
		"\t other stuff",
	'Keywords: "Big If True", "Any!"thing,',
		"\t another keyword, 11111",
	'MIME-Version: 1.0',
	'Date: ' . date('r'),
	'Content-Type: multipart/mixed; boundary="=_mixed"',
	'Content-Description: Some random description here',
	'',
	'preamble here',
	'with more than one line',
	'',
	'--=_mixed',
	'Content-Type: multipart/alternative; boundary=boundary',
	'Content-Transfer-Encoding: 8bit',
	'',
	'This is some preamble',
	'Please ignore this shit',
	'--boundary',
	'Content-Type: text/plain; charset=us-ascii',
	'',
	'Some text here',
	'lalalalala',
	'--boundary',
	'Content-Type: text/html; charset=us-ascii',
	'',
	'<h1>Hey</h1>',
	'--boundary--',
	'some epilogue here please ignore',
	'la ti data',
	'--=_mixed',
	'Content-Type: application/octet-stream',
	'Content-Transfer-Encoding: base64',
	'',
	'dGVzdAo=',
	'--=_mixed--',
	'',
	'another epilogue',
	'',
]);
$parser = new Parser([
	'bufReadSize' => 8,
	'bufPeekSize' => 4,
]);

try {
	$message = $parser->parseMessageString($source);
	print_r($message);
} catch (Throwable $t) {
	echo $t;
}

