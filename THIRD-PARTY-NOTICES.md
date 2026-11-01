# Third-party notices

This project is licensed under the MIT License (see LICENSE). This file
records attribution for third-party works that are redistributed or embedded
in this repository.

## Asciidoctor

Asciidoctor is the Ruby implementation of AsciiDoc and the reference
implementation this port follows.

The specific reference implementation targeted by this port is the fork at
<https://github.com/tyrsson/asciidoctor>, a fork of
<https://github.com/asciidoctor/asciidoctor>. That fork is MIT licensed under
the same upstream copyright line reproduced below, so this file carries one MIT
license text and one copyright line for both the upstream project and the fork.

Copyright (C) 2012-present Dan Allen, Sarah White, Ryan Waldron, and the individual contributors to Asciidoctor.

Licensed under the MIT License:

MIT License

Copyright (c) 2012-present Dan Allen, Sarah White, Ryan Waldron, and the individual contributors to Asciidoctor.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## Asciidoctor default stylesheet

`resources/css/asciidoctor.css` is Asciidoctor's default stylesheet,
redistributed unmodified. Its first line reads:

```text
/*! Asciidoctor default stylesheet | MIT License | https://asciidoctor.org */
```

The stylesheet is covered by the Asciidoctor MIT License and copyright notice
reproduced above.

The default converter embeds this stylesheet inline in generated HTML. The
committed HTML artifacts under `test/reference/`, `test/php-output/`,
`test/results/`, `test/benchmark/output/`, and `docs/` therefore contain the
stylesheet inline.

## AsciiDoc and AsciiDoc.py

AsciiDoc, the markup language, was created by Stuart Rackham. The original
implementation, AsciiDoc.py, is licensed under GPL-2.0.

This project does not include or derive from AsciiDoc.py, so no AsciiDoc.py
code or license text is redistributed here. It is listed for attribution only.

## Assets loaded from third-party CDNs

Generated HTML can reference highlight.js 9.18.3, Font Awesome 4.7.0, and
Google Fonts from public CDNs when the corresponding document attributes are
set. These assets are loaded by the browser at view time and are not bundled
in this repository. See each project for its license terms.
