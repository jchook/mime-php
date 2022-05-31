# MIME

![Version 0.0.1](https://img.shields.io/badge/v-0.0.1-red)
![License MIT](https://img.shields.io/badge/license-MIT-brightgreen)
![Test Coverage 100%](https://img.shields.io/badge/test%20coverage-100%25-brightgreen)

Compose, render, parse, and validate MIME documents in PHP 7.

## Design Goals

- Complete yet minimal
- Compliant yet resilient
- Convenient yet secure
- Contemporary yet reliable

## Roadmap

- [x] Abstract Syntax Tree
- [x] Message Builder
- [x] Renderer
- [x] Parser
- [ ] Validator (WIP)

## Usage

### Compose

To compose new MIME messages, you can either:

- use the friendly `MessageBuilder` tool
- or manually construct an <abbr title="Abstract Syntax Tree">AST</abbr>

#### MessageBuilder

The `MessageBuilder` provides some syntactic sugar for composing new messages.

The final call to `->getMessage()` returns an AST similar to the one shown below.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Virtu\Mime\MessageBuilder;

$message = (new MessageBuilder)
  ->date('July 20, 1969')
  ->from('neil@nasa.gov')
  ->to('jshearon@yahoo.com')
  ->subject('The Sea of Tranquility')
  ->text('Hi! Can you see me waving to you from the Moon?!')
  ->html('<p>Hi! Can you see me waving to you from the Moon?!</p>')
  ->attach('moon.jpg')
  ->getMessage();

?>
```

#### Manual AST

Alternatively, enjoy total control by manually constructing an AST.

**The AST represents nearly every single byte of the MIME document.** You can even generate invalid MIME if you choose. For example, if you do not include `new MimeVersion()` then `Renderer` will not render a `MIME-Version` header.

```php
<?php

use Virtu\Mime\Body\Part;
use Virtu\Mime\Body\Text;
use Virtu\Mime\Element\Group;
use Virtu\Mime\Element\Mailbox;
use Virtu\Mime\Header\ContentTransferEncoding;
use Virtu\Mime\Header\ContentType;
use Virtu\Mime\Header\Header;
use Virtu\Mime\Header\MimeVersion;
use Virtu\Mime\Message;

$message = new Message([
  new MimeVersion(),
  new ContentType('message', 'global'),
  new Part([
    new Header('From', [
      new Mailbox('Bertrand Russell', 'brussel', 'trin.cam.ac.uk')
    ]),
    new Header('To', [
      new Mailbox('Gottlob Frege', 'frege', 'uni-goettingen.de'),
      new Group('Philomaths', [
        new Mailbox('Paul Erdős', 'erdős', 'יְרוּשָׁלַיִם.edu'),
        new Mailbox('Giuseppe Peano', 'peano', 'unito.it'),
      ]),
    ]),
    new Header('Subject', 'Begriffsschrift'),
    new ContentType('multipart', 'alternative', [
      'boundary' => 'my-custom-boundary-here',
      'charset' => 'utf-8',
    ]),
    new ContentTransferEncoding('8bit'),
    new Part([
      new ContentType('text', 'html', ['charset' => 'utf-8']),
      new Text([
        '<h1>Begriffsschrift 🇩🇪</h1>',
        '<p>My proposal concerning logical types now seems to me incapable of doing what I had hoped it would do.</p>',
        '<p>Here we must consider the content of the propositions, not their meaning; and we must not take equivalent propositions to be simply identical.</p>',
        '<p>— 😬 B. Russell</p>'
      ]),
    ]),
    new Part([
      new ContentType('text', 'plain', ['charset' => 'utf-8']),
      new Text([
        '== Begriffsschrift 🇩🇪 ==',
        'My proposal concerning logical types now seems to me incapable of doing what I had hoped it would do.',
        'Here we must consider the content of the propositions, not their meaning; and we must not take equivalent propositions to be simply identical.',
        '— 😬 B. Russell'
      ]),
    ]),
  ]),
]);

?>
```

### Render

The `Renderer` allows you to translate a MIME message AST into a string or iterable.

```php
<?php

use Virtu\Mime\Textual\Renderer;

$renderer = new Renderer();
$iterable = $renderer->renderMessage($message);

?>
```

Returning an `iterable` keeps memory usage predictable and configurable, even for very large attachments.

You can easily write the output to any stream:

```php
<?php

// Use anything for a stream
// stdout, file, tcp connection, etc
$stream = fopen('php://temp', 'rw');

// Emits <= 8 KB chunks by default
foreach ($iterable as $chunk) {
  fwrite($stream, $chunk);
}

// Rewind for later use
rewind($stream);

?>
```

Or if you really want to throw caution to the wind and render the entire MIME document as a string in RAM, you can do so.

```php
<?php

echo $renderer->renderMessageString($message);

?>
```

### Parse

The `Parser`, when given `$stream`, will generate an AST identical to the one above. <!-- NB. store to Text until string length necessitates Resource -->

```php
<?php

use Virtu\Mime\Textual\Parser;

$parser = new Parser();
$message = $parser->parseMessage($stream);

?>
```

### Validate

The `Validator` validates a MIME message AST.

Note, the `Parser` detects syntax errors in the text, but the `Validator` detects structural errors in the AST. You can use the `Validator` on messages you have parsed or composed.

```php
<?php

use Virtu\Mime\Contract\Validator;

$validator = new Validator();
$result = $validator->validateMessage($message);

?>
```

## Specification

- [RFC 5322](https://www.rfc-editor.org/rfc/rfc5322.html) - Internet Message Format
  - Obsoletes [RFC 822](https://www.rfc-editor.org/rfc/rfc822)
  - Obsoletes [RFC 2822](https://www.rfc-editor.org/rfc/rfc2822)
- [RFC 2045](https://www.rfc-editor.org/rfc/rfc2045) - MIME Message Format
- [RFC 2046](https://www.rfc-editor.org/rfc/rfc2046) - MIME Media Types
- [RFC 2047](https://www.rfc-editor.org/rfc/rfc2047) - MIME Non-ASCII Headers
- [RFC 2048](https://www.rfc-editor.org/rfc/rfc2048) - MIME Media Type Registration
- [RFC 2049](https://www.rfc-editor.org/rfc/rfc2048) - MIME Conformance &amp; Examples
- [RFC 2231](https://tools.ietf.org/html/rfc2231) - Internationalized Header Parameters
- [RFC 6530](https://www.rfc-editor.org/rfc/rfc6530.html) - Internationalized Email Framework
- [RFC 6532](https://www.rfc-editor.org/rfc/rfc6532.html) - Internationalized Email Headers

## Contribute

- [Submit an issue](ISSUES.md)
- [Submit a pull request](CONTRIB.md)

## LICENSE

Copyright 2019 Wesley Roberts

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
