<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/reedcrm_email_message.class.php
 * \ingroup reedcrm
 * \brief   Self-contained (zero dependency) parser for Outlook .msg and MIME .eml email files.
 *
 * No external library is used: .msg is read directly from its OLE2/CFBF + MAPI streams,
 * .eml from RFC822/MIME using only native PHP (mbstring/iconv). The class is Dolibarr
 * independent on purpose so it can be unit-tested standalone; HTML sanitisation is done
 * by the caller (the AJAX endpoint) with Dolibarr's bundled tools.
 */

/**
 * Parsed representation of an email file (.msg or .eml).
 */
class ReedcrmEmailMessage
{
    /** @var int Deepest level of attached messages parsed (a message attached to a message attached to...) */
    const MAX_DEPTH = 3;

    /** @var string */
    public $subject = '';
    /** @var string Sender display name */
    public $fromName = '';
    /** @var string Sender email */
    public $fromEmail = '';
    /** @var string[] "To" recipients (raw display strings) */
    public $to = [];
    /** @var string[] "Cc" recipients */
    public $cc = [];
    /** @var int|null Unix timestamp of the message date */
    public $date = null;
    /** @var string HTML body (UTF-8) when available */
    public $htmlBody = '';
    /** @var string Plain text body (UTF-8) */
    public $textBody = '';
    /**
     * Attachments. An attached email comes with its parsed content in 'message'; its 'data' is the raw
     * .eml when there is one, '' for a message embedded in a .msg (it only exists as MAPI streams).
     *
     * @var array<int, array{filename: string, mime: string, data: string, cid: string, message: ReedcrmEmailMessage|null}>
     */
    public $attachments = [];

    /**
     * Build from a file on disk, auto-detecting .msg (OLE2) vs .eml (MIME).
     *
     * @param  string $path Full path
     * @return self
     * @throws Exception
     */
    public static function fromFile(string $path): self
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new Exception('Cannot read file: ' . $path);
        }
        if (substr($bytes, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return self::fromMsg($bytes);
        }
        return self::fromEml($bytes);
    }

    /**
     * The message reached by a path of attachment indexes ('' = this message, '2' = its 3rd attachment,
     * '2.0' = the 1st attachment of that one), null when the path does not lead to an attached email.
     *
     * @param  string $part Dot separated attachment indexes
     * @return self|null
     */
    public function getPart(string $part): ?self
    {
        $msg = $this;
        if ($part === '') {
            return $msg;
        }
        foreach (explode('.', $part) as $index) {
            if (!ctype_digit($index) || !isset($msg->attachments[(int) $index]['message'])) {
                return null;
            }
            $msg = $msg->attachments[(int) $index]['message'];
        }
        return $msg;
    }

    /* ===================================================================== */
    /*  .msg  (OLE2 / CFBF + MAPI)                                            */
    /* ===================================================================== */

    /**
     * Parse an Outlook .msg from its raw bytes.
     *
     * @param  string $bytes Raw file content
     * @return self
     * @throws Exception
     */
    public static function fromMsg(string $bytes): self
    {
        return self::fromMsgStorage(new ReedcrmOle2Reader($bytes), 0, 32, 0);
    }

    /**
     * Parse the message stored under an OLE2 storage: the root of the file, or the storage of a
     * message embedded as an attachment.
     *
     * @param  ReedcrmOle2Reader $ole        Reader of the file
     * @param  int               $root       Index of the storage of the message
     * @param  int               $headerLen  Header length of its property stream: 32 for the file, 24 when embedded
     * @param  int               $depth      Level of embedding
     * @return self
     */
    private static function fromMsgStorage(ReedcrmOle2Reader $ole, int $root, int $headerLen, int $depth): self
    {
        $msg = new self();

        // 8-bit strings are in the code page of the message (PR_MESSAGE_CODEPAGE, else PR_INTERNET_CPID)
        $charset = self::codepageCharset($ole->propLong($root, 0x3FFD, $headerLen) ?: ($ole->propLong($root, 0x3FDE, $headerLen) ?: 1252));

        $msg->subject  = trim((string) $ole->propString($root, '0037', $charset));
        $msg->fromName = trim((string) ($ole->propString($root, '0C1A', $charset) ?: $ole->propString($root, '0042', $charset)));
        // SMTP address of the sender, else of the account it was sent for; 0C1F/0065 hold an Exchange DN (/O=...) when not SMTP
        foreach (['5D01', '5D02', '0C1F', '0065'] as $tag) {
            $email = trim((string) $ole->propString($root, $tag, $charset));
            if (strpos($email, '@') !== false && $email[0] !== '/') {
                $msg->fromEmail = $email;
                break;
            }
        }

        $to = trim((string) $ole->propString($root, '0E04', $charset));
        $cc = trim((string) $ole->propString($root, '0E03', $charset));
        $msg->to = $to !== '' ? array_values(array_filter(array_map('trim', explode(';', $to)), static fn (string $v): bool => $v !== '')) : [];
        $msg->cc = $cc !== '' ? array_values(array_filter(array_map('trim', explode(';', $cc)), static fn (string $v): bool => $v !== '')) : [];

        $msg->textBody = (string) $ole->propString($root, '1000', $charset); // PR_BODY

        // PR_HTML: bytes in the internet code page of the message
        $html = $ole->propString($root, '1013', self::codepageCharset($ole->propLong($root, 0x3FDE, $headerLen) ?: 0));
        if ($html !== null && trim($html) !== '') {
            $msg->htmlBody = $html;
        } else {
            // Classic Outlook only keeps the body as compressed RTF, wrapping the original HTML when there was one
            $rtf = $ole->propBinary($root, '1009'); // PR_RTF_COMPRESSED
            if ($rtf !== null && $rtf !== '') {
                $msg->htmlBody = self::rtfToHtml(self::decompressRtf($rtf));
            }
        }

        // Date: PR_MESSAGE_DELIVERY_TIME, else PR_CLIENT_SUBMIT_TIME
        $msg->date = $ole->propTime($root, [0x0E06, 0x0039], $headerLen);

        foreach ($ole->children($root) as $child) {
            if (stripos($child['name'], '__attach_version1.0') !== 0) {
                continue;
            }
            $idx  = $child['idx'];
            $name = trim((string) ($ole->propString($idx, '3707', $charset) ?: $ole->propString($idx, '3704', $charset))); // long / short file name
            // PR_DISPLAY_NAME, last resort: Outlook may append the size to it ("sample.eml (555 bytes)")
            $displayName = trim((string) $ole->propString($idx, '3001', $charset));

            // PR_ATTACH_METHOD 5 = ATTACH_EMBEDDED_MSG: the attached email is a storage, not a file
            $embedded = $ole->child($idx, '__substg1.0_3701000D');
            if ($ole->propLong($idx, 0x3705, 8) === 5 && $embedded !== null) {
                if ($depth >= self::MAX_DEPTH) {
                    continue;
                }
                $nested = self::fromMsgStorage($ole, $embedded['idx'], 24, $depth + 1);
                $msg->attachments[] = [
                    'filename' => $name !== '' ? $name : ($nested->subject !== '' ? $nested->subject : ($displayName !== '' ? $displayName : 'message')),
                    'mime'     => 'message/rfc822',
                    'data'     => '',
                    'cid'      => '',
                    'message'  => $nested,
                ];
                continue;
            }

            $data = $ole->propBinary($idx, '3701'); // PR_ATTACH_DATA_BIN
            if ($data === null || $data === '') {
                continue; // OLE objects and links: nothing that can be shown or downloaded
            }
            $msg->attachments[] = [
                'filename' => $name !== '' ? $name : ($displayName !== '' ? $displayName : 'attachment'),
                'mime'     => trim((string) $ole->propString($idx, '370E', $charset)), // PR_ATTACH_MIME_TAG
                'data'     => $data,
                'cid'      => trim((string) $ole->propString($idx, '3712', $charset), " <>\t"), // PR_ATTACH_CONTENT_ID
                'message'  => null,
            ];
        }

        return $msg;
    }

    /**
     * Decompress a PR_RTF_COMPRESSED stream (MS-OXRTFCP).
     *
     * @param  string $data Compressed stream
     * @return string       RTF, '' when the stream is not readable
     */
    public static function decompressRtf(string $data): string
    {
        if (strlen($data) < 16) {
            return '';
        }
        $header = unpack('VcompSize/VrawSize/VcompType', substr($data, 0, 12));
        $rawSize = min((int) $header['rawSize'], 50 * 1024 * 1024);
        if ($header['compType'] === 0x414C454D) { // "MELA": stored without compression
            return substr($data, 16, $rawSize);
        }
        if ($header['compType'] !== 0x75465A4C) { // "LZFu"
            return '';
        }

        $dict = '{\rtf1\ansi\mac\deff0\deftab720{\fonttbl;}{\f0\fnil \froman \fswiss \fmodern \fscript \fdecor MS Sans SerifSymbolArialTimes New RomanCourier{\colortbl\red0\green0\blue0' . "\r\n" . '\par \pard\plain\f0\fs20\b\i\u\tab\tx';
        $writePos = strlen($dict);
        $dict     = str_pad($dict, 4096, "\0");
        $end      = min(strlen($data), (int) $header['compSize'] + 4);
        $out      = '';
        $pos      = 16;
        while ($pos < $end && strlen($out) < $rawSize) {
            $control = ord($data[$pos++]);
            for ($bit = 0; $bit < 8 && $pos < $end; $bit++) {
                if (($control & (1 << $bit)) === 0) {
                    $char             = $data[$pos++];
                    $out             .= $char;
                    $dict[$writePos]  = $char;
                    $writePos         = ($writePos + 1) & 0xFFF;
                    continue;
                }
                if ($pos + 1 >= $end) {
                    return $out;
                }
                $ref  = (ord($data[$pos]) << 8) | ord($data[$pos + 1]);
                $pos += 2;
                $offset = $ref >> 4;
                if ($offset === $writePos) {
                    return $out; // end marker
                }
                $length = ($ref & 0x0F) + 2;
                for ($i = 0; $i < $length; $i++) {
                    $char             = $dict[($offset + $i) & 0xFFF];
                    $out             .= $char;
                    $dict[$writePos]  = $char;
                    $writePos         = ($writePos + 1) & 0xFFF;
                }
            }
        }
        return $out;
    }

    /**
     * Extract the HTML that Outlook wrapped into an RTF body (MS-OXRTFEX, "\fromhtml1").
     *
     * @param  string $rtf RTF document
     * @return string      UTF-8 HTML, '' when the RTF was not made from HTML
     */
    public static function rtfToHtml(string $rtf): string
    {
        return (new ReedcrmRtfHtml())->extract($rtf);
    }

    /* ===================================================================== */
    /*  .eml  (RFC822 / MIME)                                                 */
    /* ===================================================================== */

    /**
     * Parse a MIME .eml from its raw bytes.
     *
     * @param  string $bytes Raw file content
     * @param  int    $depth Level of embedding (an .eml attached to an .eml)
     * @return self
     */
    public static function fromEml(string $bytes, int $depth = 0): self
    {
        $msg = new self();
        $bytes = str_replace("\r\n", "\n", $bytes);
        [$headerRaw, $body] = self::splitHeaderBody($bytes);
        $headers = self::parseHeaders($headerRaw);

        $msg->subject = self::decodeHeader($headers['subject'] ?? '');
        [$fn, $fe]    = self::parseAddress(self::decodeHeader($headers['from'] ?? ''));
        $msg->fromName  = $fn;
        $msg->fromEmail = $fe;
        $msg->to = self::splitAddresses(self::decodeHeader($headers['to'] ?? ''));
        $msg->cc = self::splitAddresses(self::decodeHeader($headers['cc'] ?? ''));
        if (!empty($headers['date'])) {
            // strtotime() does not read the trailing comment of "+0200 (CEST)"
            $ts = strtotime((string) preg_replace('/\s*\(.*\)\s*$/', '', $headers['date']));
            $msg->date = $ts !== false ? $ts : null;
        }

        self::walkMimePart($headers, $body, $msg, $depth);

        return $msg;
    }

    /**
     * Recursively walk a MIME part, filling bodies and attachments.
     *
     * @param  array<string, string> $headers Part headers (lowercased keys)
     * @param  string                $body    Part body
     * @param  self                  $msg     Accumulator
     * @param  int                   $depth   Level of embedding of the message
     * @return void
     */
    private static function walkMimePart(array $headers, string $body, self $msg, int $depth): void
    {
        $ctype = strtolower($headers['content-type'] ?? 'text/plain');
        $mime  = trim(explode(';', $ctype)[0]);

        if (strpos($mime, 'multipart/') === 0) {
            $boundary = self::headerParam($headers['content-type'] ?? '', 'boundary');
            if ($boundary === '') {
                return;
            }
            $parts = self::splitMultipart($body, $boundary);
            foreach ($parts as $part) {
                [$ph, $pb] = self::splitHeaderBody($part);
                self::walkMimePart(self::parseHeaders($ph), $pb, $msg, $depth);
            }
            return;
        }

        $encoding = strtolower(trim($headers['content-transfer-encoding'] ?? '7bit'));
        $decoded  = self::decodeBody($body, $encoding);
        $charset  = self::headerParam($headers['content-type'] ?? '', 'charset') ?: 'UTF-8';

        $disp     = strtolower($headers['content-disposition'] ?? '');
        $filename = self::headerParam($headers['content-disposition'] ?? '', 'filename')
            ?: self::headerParam($headers['content-type'] ?? '', 'name');
        $cid      = trim((string) ($headers['content-id'] ?? ''), " <>\t");
        $isAttach = strpos($disp, 'attachment') === 0 || $filename !== '';

        if ($mime === 'message/rfc822') {
            if ($depth >= self::MAX_DEPTH) {
                return;
            }
            $nested = self::fromEml($decoded, $depth + 1);
            $msg->attachments[] = [
                'filename' => $filename !== '' ? $filename : ($nested->subject !== '' ? $nested->subject : 'message') . '.eml',
                'mime'     => $mime,
                'data'     => $decoded,
                'cid'      => '',
                'message'  => $nested,
            ];
        } elseif (!$isAttach && $mime === 'text/html') {
            // Apple Mail splits the HTML around the inline attachments: every part is part of the body
            $msg->htmlBody .= self::convertCharset($decoded, $charset);
        } elseif (!$isAttach && $mime === 'text/plain') {
            $msg->textBody .= ($msg->textBody !== '' ? "\n" : '') . self::convertCharset($decoded, $charset);
        } else {
            $msg->attachments[] = [
                'filename' => $filename !== '' ? $filename : 'attachment',
                'mime'     => $mime,
                'data'     => $decoded,
                'cid'      => $cid,
                'message'  => null,
            ];
        }
    }

    /* ===================================================================== */
    /*  Helpers                                                              */
    /* ===================================================================== */

    /**
     * Split raw message into [headers, body] at the first blank line.
     *
     * @param  string $raw Raw content (LF newlines)
     * @return array{0:string,1:string}
     */
    private static function splitHeaderBody(string $raw): array
    {
        $pos = strpos($raw, "\n\n");
        if ($pos === false) {
            return [$raw, ''];
        }
        return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    /**
     * Parse and unfold MIME headers into a lowercased-key map.
     *
     * @param  string $raw Header block
     * @return array<string, string>
     */
    private static function parseHeaders(string $raw): array
    {
        $raw     = (string) preg_replace("/\n[ \t]+/", ' ', $raw); // unfold
        $headers = [];
        foreach (explode("\n", $raw) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
        return $headers;
    }

    /**
     * Extract a parameter (e.g. boundary, charset, filename) from a header value, decoded to UTF-8.
     * Handles the RFC 2231 forms (name*=charset'lang'value, name*0*=..., name*1*=...) and RFC 2047 words.
     *
     * @param  string $header Header value
     * @param  string $param  Parameter name
     * @return string
     */
    private static function headerParam(string $header, string $param): string
    {
        $name = preg_quote($param, '/');

        // RFC 2231: one value or numbered continuations, each one optionally percent-encoded (trailing *)
        if (preg_match_all('/;\s*' . $name . '\*(\d*)(\*?)\s*=\s*(?:"([^"]*)"|([^;\s]*))/i', $header, $all, PREG_SET_ORDER)) {
            $pieces = [];
            foreach ($all as $piece) {
                $pieces[(int) $piece[1]] = [$piece[2] === '*' || $piece[1] === '', $piece[3] !== '' ? $piece[3] : ($piece[4] ?? '')];
            }
            ksort($pieces);
            $charset = 'UTF-8';
            $value   = '';
            foreach ($pieces as $index => [$encoded, $text]) {
                if ($encoded && $index === array_key_first($pieces) && preg_match("/^([^']*)'[^']*'(.*)$/", $text, $cm)) {
                    $charset = $cm[1] !== '' ? $cm[1] : $charset;
                    $text    = $cm[2];
                }
                $value .= $encoded ? rawurldecode($text) : $text;
            }
            return self::convertCharset($value, $charset);
        }

        if (preg_match('/;\s*' . $name . '\s*=\s*"([^"]*)"/i', $header, $m)) {
            return self::decodeHeader($m[1]);
        }
        if (preg_match('/;\s*' . $name . '\s*=\s*([^;\s]+)/i', $header, $m)) {
            return self::decodeHeader($m[1]);
        }
        return '';
    }

    /**
     * Split a multipart body on its boundary.
     *
     * @param  string $body     Multipart body
     * @param  string $boundary Boundary string
     * @return string[]
     */
    private static function splitMultipart(string $body, string $boundary): array
    {
        $parts = preg_split('/(?:^|\n)--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\n/', $body) ?: [];
        $out   = [];
        foreach ($parts as $p) {
            if (trim($p) !== '') {
                $out[] = ltrim($p, "\n");
            }
        }
        return $out;
    }

    /**
     * Decode a transfer-encoded body.
     *
     * @param  string $body     Encoded body
     * @param  string $encoding Transfer encoding
     * @return string
     */
    private static function decodeBody(string $body, string $encoding): string
    {
        if ($encoding === 'base64') {
            return base64_decode((string) preg_replace('/\s+/', '', $body)) ?: '';
        }
        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($body);
        }
        return $body;
    }

    /**
     * Decode a header value to UTF-8: RFC 2047 encoded words (=?charset?B/Q?...?=) and raw 8-bit text,
     * that many mailers write without encoding it (RFC 6532).
     *
     * @param  string $value Raw header value
     * @return string UTF-8
     */
    private static function decodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $word = '=\?([^?\s]+)\?([BbQq])\?([^?\s]*)\?=';

        // [charset or null for raw text, bytes]; adjacent words of one charset are joined before converting,
        // as a multibyte character can be split between two of them
        $segments = [];
        $pos      = 0;
        if (preg_match_all('/' . $word . '/', $value, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $between = substr($value, $pos, $match[0][1] - $pos);
                // The whitespace between two encoded words is not part of the text (RFC 2047 6.2)
                if ($between !== '' && !($segments && end($segments)[0] !== null && trim($between) === '')) {
                    $segments[] = [null, $between];
                }
                $charset = strtoupper((string) preg_replace('/\*.*$/', '', $match[1][0]));
                $text    = $match[3][0];
                $bytes   = strtoupper($match[2][0]) === 'B' ? (string) base64_decode($text) : quoted_printable_decode(str_replace('_', ' ', $text));
                $last    = count($segments) - 1;
                if ($last >= 0 && $segments[$last][0] === $charset) {
                    $segments[$last][1] .= $bytes;
                } else {
                    $segments[] = [$charset, $bytes];
                }
                $pos = $match[0][1] + strlen($match[0][0]);
            }
        }
        if ($pos < strlen($value)) {
            $segments[] = [null, substr($value, $pos)];
        }

        $out = '';
        foreach ($segments as [$charset, $bytes]) {
            $out .= $charset === null ? self::toUtf8($bytes) : self::convertCharset($bytes, $charset);
        }
        return $out;
    }

    /**
     * Convert a string from a charset to UTF-8.
     *
     * @param  string $s       Input
     * @param  string $charset Source charset
     * @return string
     */
    public static function convertCharset(string $s, string $charset): string
    {
        $charset = strtoupper(trim($charset, " \t\"'"));
        $aliases = ['' => 'UTF-8', 'UTF8' => 'UTF-8', 'KS_C_5601-1987' => 'CP949', 'ISO-8859-8-I' => 'ISO-8859-8', 'X-SJIS' => 'CP932', 'SHIFT_JIS' => 'CP932'];
        $charset = $aliases[$charset] ?? $charset;
        if ($charset === 'UTF-8' || $charset === 'US-ASCII') {
            return self::toUtf8($s);
        }
        $out = @iconv($charset, 'UTF-8//IGNORE', $s);
        return $out !== false ? $out : self::toUtf8($s);
    }

    /**
     * Best-effort conversion to UTF-8 of bytes of unknown charset: kept when already valid UTF-8,
     * read as Windows-1252 otherwise.
     *
     * @param  string $s Bytes
     * @return string
     */
    private static function toUtf8(string $s): string
    {
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        $out = @iconv('Windows-1252', 'UTF-8//IGNORE', $s);
        return $out !== false ? $out : $s;
    }

    /**
     * Charset name, for iconv, of a Windows code page.
     *
     * @param  int    $codepage Code page (1252, 65001, 932...); 0 = unknown
     * @return string
     */
    public static function codepageCharset(int $codepage): string
    {
        if ($codepage === 65001) {
            return 'UTF-8';
        }
        if ($codepage === 20127) {
            return 'US-ASCII';
        }
        if ($codepage >= 28591 && $codepage <= 28599) {
            return 'ISO-8859-' . ($codepage - 28590);
        }
        if ($codepage === 28605) {
            return 'ISO-8859-15';
        }
        if (in_array($codepage, [50220, 50221, 50222], true)) {
            return 'ISO-2022-JP';
        }
        if ($codepage === 51932) {
            return 'EUC-JP';
        }
        if ($codepage === 10000) {
            return 'MACINTOSH';
        }
        if ($codepage >= 1250 && $codepage <= 1258) {
            return 'Windows-' . $codepage;
        }
        return $codepage > 0 ? 'CP' . $codepage : 'UTF-8';
    }

    /**
     * Parse a single "Name <email>" address.
     *
     * @param  string $addr Address string
     * @return array{0:string,1:string} [name, email]
     */
    private static function parseAddress(string $addr): array
    {
        if (preg_match('/^(.*)<([^>]+)>\s*$/', $addr, $m)) {
            return [trim(trim($m[1]), '"'), trim($m[2])];
        }
        return ['', trim($addr)];
    }

    /**
     * Split a recipient header into individual display strings.
     *
     * @param  string $value Header value
     * @return string[]
     */
    private static function splitAddresses(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }
        // split on commas not inside quotes
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $v): bool => $v !== ''));
    }
}

/**
 * Minimal OLE2 / Compound File Binary Format reader exposing MAPI property substreams.
 * Zero dependency.
 */
class ReedcrmOle2Reader
{
    /** @var string */
    private $data;
    /** @var int */
    private $sectorSize;
    /** @var int */
    private $miniSectorSize;
    /** @var int */
    private $miniCutoff;
    /** @var int[] */
    private $fat = [];
    /** @var int[] */
    private $miniFat = [];
    /** @var array<int, array{name: string, type: int, left: int, right: int, child: int, start: int, size: int}> index => directory entry */
    private $dir = [];
    /** @var string */
    private $miniStream = '';
    /** @var array<int, array<int, array{name: string, type: int, left: int, right: int, child: int, start: int, size: int, idx: int}>> Children of each storage already listed */
    private $childrenCache = [];
    /** @var array<int, array<int, array{type: int, value: string}>> Fixed size properties of each storage already read */
    private $propsCache = [];

    /**
     * @param string $bytes Raw OLE2 file content
     * @throws Exception
     */
    public function __construct(string $bytes)
    {
        $this->data = $bytes;
        $this->parse();
    }

    /**
     * @param  int $o Offset
     * @return int
     */
    private function u16(int $o): int
    {
        return unpack('v', substr($this->data, $o, 2) . "\0\0")[1];
    }

    /**
     * @param  int $o Offset
     * @return int
     */
    private function u32(int $o): int
    {
        return unpack('V', substr($this->data, $o, 4) . "\0\0\0\0")[1];
    }

    /**
     * @param  int $n Sector number
     * @return string
     */
    private function sector(int $n): string
    {
        return substr($this->data, 512 + $n * $this->sectorSize, $this->sectorSize);
    }

    /**
     * Read a full-sector FAT chain.
     *
     * @param  int $start First sector
     * @param  int $size  Bytes needed (-1 = the whole chain)
     * @return string
     */
    private function readChain(int $start, int $size = -1): string
    {
        $out = '';
        $s   = $start;
        $g   = 0;
        $max = intdiv(strlen($this->data), max(1, $this->sectorSize)) + 1; // a chain longer than the file loops
        while ($s >= 0 && $s < 0xFFFFFFFA && $g++ < $max && ($size < 0 || strlen($out) < $size)) {
            $out .= $this->sector($s);
            $s    = $this->fat[$s] ?? 0xFFFFFFFE;
        }
        return $size < 0 ? $out : substr($out, 0, $size);
    }

    /**
     * Read a mini-FAT chain from the mini stream.
     *
     * @param  int $start First mini sector
     * @param  int $size  Stream size
     * @return string
     */
    private function readMiniChain(int $start, int $size): string
    {
        $out = '';
        $s   = $start;
        $g   = 0;
        $max = intdiv(strlen($this->miniStream), max(1, $this->miniSectorSize)) + 1;
        while ($s >= 0 && $s < 0xFFFFFFFA && $g++ < $max && strlen($out) < $size) {
            $out .= substr($this->miniStream, $s * $this->miniSectorSize, $this->miniSectorSize);
            $s    = $this->miniFat[$s] ?? 0xFFFFFFFE;
        }
        return substr($out, 0, $size);
    }

    /**
     * Parse the CFBF structure (header, DIFAT, FAT, directory, mini-FAT).
     *
     * @return void
     * @throws Exception
     */
    private function parse(): void
    {
        if (substr($this->data, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            throw new Exception('Not an OLE2 file');
        }
        $this->sectorSize     = 1 << min(16, $this->u16(30));
        $this->miniSectorSize = 1 << min(16, $this->u16(32));
        $this->miniCutoff     = $this->u32(56);
        $firstDir             = $this->u32(48);
        $firstMiniFat         = $this->u32(60);
        $firstDifat           = $this->u32(68);

        $difat = [];
        for ($i = 0; $i < 109; $i++) {
            $v = $this->u32(76 + $i * 4);
            if ($v < 0xFFFFFFFE) {
                $difat[] = $v;
            }
        }
        $ds  = $firstDifat;
        $g   = 0;
        $cnt = intdiv($this->sectorSize, 4);
        while ($ds < 0xFFFFFFFA && $g++ < 100000) {
            $sec = $this->sector($ds);
            if ($sec === '') {
                break;
            }
            for ($i = 0; $i < $cnt - 1; $i++) {
                $v = unpack('V', substr($sec, $i * 4, 4) . "\0\0\0\0")[1];
                if ($v < 0xFFFFFFFE) {
                    $difat[] = $v;
                }
            }
            $ds = unpack('V', substr($sec, ($cnt - 1) * 4, 4) . "\0\0\0\0")[1];
        }
        foreach ($difat as $fs) {
            $sec = $this->sector($fs);
            for ($i = 0; $i < $cnt; $i++) {
                $this->fat[] = unpack('V', substr($sec, $i * 4, 4) . "\0\0\0\0")[1];
            }
        }

        $dirData = $this->readChain($firstDir);
        $n       = intdiv(strlen($dirData), 128);
        for ($i = 0; $i < $n; $i++) {
            $e       = substr($dirData, $i * 128, 128);
            $nameLen = min(64, unpack('v', substr($e, 64, 2))[1]);
            $name    = $nameLen > 2 ? mb_convert_encoding(substr($e, 0, $nameLen - 2), 'UTF-8', 'UTF-16LE') : '';
            $this->dir[$i] = [
                'name'  => $name,
                'type'  => ord($e[66]),
                'left'  => unpack('V', substr($e, 68, 4))[1],
                'right' => unpack('V', substr($e, 72, 4))[1],
                'child' => unpack('V', substr($e, 76, 4))[1],
                'start' => unpack('V', substr($e, 116, 4))[1],
                'size'  => unpack('V', substr($e, 120, 4))[1],
            ];
        }
        if (empty($this->dir)) {
            throw new Exception('Empty OLE2 directory');
        }

        $root             = $this->dir[0];
        $this->miniStream = $this->readChain($root['start'], $root['size']);

        if ($firstMiniFat < 0xFFFFFFFA) {
            $mf  = $this->readChain($firstMiniFat);
            $cnt = intdiv(strlen($mf), 4);
            for ($i = 0; $i < $cnt; $i++) {
                $this->miniFat[] = unpack('V', substr($mf, $i * 4, 4))[1];
            }
        }
    }

    /**
     * In-order traversal of the red-black sibling tree.
     *
     * @param  int   $idx  Node index
     * @param  int[] $acc  Accumulator
     * @param  int[] $seen Nodes already visited (a corrupt tree can loop)
     * @return void
     */
    private function siblings(int $idx, array &$acc, array &$seen): void
    {
        if ($idx < 0 || $idx >= 0xFFFFFFFA || !isset($this->dir[$idx]) || isset($seen[$idx])) {
            return;
        }
        $seen[$idx] = 1;
        $this->siblings($this->dir[$idx]['left'], $acc, $seen);
        $acc[] = $idx;
        $this->siblings($this->dir[$idx]['right'], $acc, $seen);
    }

    /**
     * Children entries of a storage entry.
     *
     * @param  int $storageIdx Storage index
     * @return array<int, array{name: string, type: int, left: int, right: int, child: int, start: int, size: int, idx: int}>
     */
    public function children(int $storageIdx): array
    {
        if (!isset($this->childrenCache[$storageIdx])) {
            $acc   = [];
            $seen  = [];
            $child = $this->dir[$storageIdx]['child'] ?? 0xFFFFFFFE;
            $this->siblings($child, $acc, $seen);
            $this->childrenCache[$storageIdx] = array_map(fn ($i) => $this->dir[$i] + ['idx' => $i], $acc);
        }
        return $this->childrenCache[$storageIdx];
    }

    /**
     * First child of a storage whose name starts with a prefix (case insensitive).
     *
     * @param  int    $storageIdx Storage index
     * @param  string $prefix     Name prefix, e.g. '__substg1.0_0037'
     * @return array{name: string, type: int, left: int, right: int, child: int, start: int, size: int, idx: int}|null
     */
    public function child(int $storageIdx, string $prefix): ?array
    {
        foreach ($this->children($storageIdx) as $c) {
            if (stripos($c['name'], $prefix) === 0) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Raw bytes of a stream entry ('' for a storage).
     *
     * @param  array{type: int, start: int, size: int} $entry Directory entry
     * @return string
     */
    public function streamData(array $entry): string
    {
        if ($entry['type'] !== 2) {
            return '';
        }
        if ($entry['size'] < $this->miniCutoff) {
            return $this->readMiniChain($entry['start'], $entry['size']);
        }
        return $this->readChain($entry['start'], $entry['size']);
    }

    /**
     * MAPI string property under a storage, by 4-hex tag id, decoded to UTF-8.
     *
     * @param  int    $storageIdx Storage index
     * @param  string $tagId      Property id (e.g. '0037')
     * @param  string $charset    Charset of 8-bit strings (PT_STRING8) and of binary text
     * @return string|null
     */
    public function propString(int $storageIdx, string $tagId, string $charset): ?string
    {
        $entry = $this->child($storageIdx, '__substg1.0_' . $tagId);
        if ($entry === null || $entry['type'] !== 2) {
            return null;
        }
        $raw  = $this->streamData($entry);
        $type = strtoupper(substr($entry['name'], -4));
        if ($type === '001F') { // PT_UNICODE
            return rtrim(mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE'), "\0");
        }
        $raw = rtrim($raw, "\0");
        if ($charset === 'UTF-8' || $charset === 'US-ASCII') {
            return mb_check_encoding($raw, 'UTF-8') ? $raw : (string) @iconv('Windows-1252', 'UTF-8//IGNORE', $raw);
        }
        $out = @iconv($charset, 'UTF-8//IGNORE', $raw);
        return $out !== false ? $out : $raw;
    }

    /**
     * Raw bytes of a binary property under a storage.
     *
     * @param  int    $storageIdx Storage index
     * @param  string $tagId      Property id
     * @return string|null
     */
    public function propBinary(int $storageIdx, string $tagId): ?string
    {
        $entry = $this->child($storageIdx, '__substg1.0_' . $tagId);
        return ($entry !== null && $entry['type'] === 2) ? $this->streamData($entry) : null;
    }

    /**
     * Fixed size properties of a storage, read from its __properties_version1.0 stream.
     *
     * @param  int $storageIdx Storage index
     * @param  int $headerLen  Header length: 32 for the message of the file, 24 for an embedded one, 8 for an attachment
     * @return array<int, array{type: int, value: string}> Property id => type and 8 value bytes
     */
    private function fixedProps(int $storageIdx, int $headerLen): array
    {
        if (!isset($this->propsCache[$storageIdx])) {
            $props = [];
            $entry = $this->child($storageIdx, '__properties_version1.0');
            $raw   = $entry !== null ? $this->streamData($entry) : '';
            for ($o = $headerLen; $o + 16 <= strlen($raw); $o += 16) {
                $head = unpack('vtype/vid', substr($raw, $o, 4));
                $props[$head['id']] = ['type' => $head['type'], 'value' => substr($raw, $o + 8, 8)];
            }
            $this->propsCache[$storageIdx] = $props;
        }
        return $this->propsCache[$storageIdx];
    }

    /**
     * PT_LONG property of a storage.
     *
     * @param  int $storageIdx Storage index
     * @param  int $id         Property id (e.g. 0x3705)
     * @param  int $headerLen  Header length of the property stream
     * @return int|null
     */
    public function propLong(int $storageIdx, int $id, int $headerLen): ?int
    {
        $prop = $this->fixedProps($storageIdx, $headerLen)[$id] ?? null;
        if ($prop === null || $prop['type'] !== 0x0003) {
            return null;
        }
        return unpack('l', substr($prop['value'], 0, 4))[1];
    }

    /**
     * First PT_SYSTIME property found among ids, in their order, as a unix timestamp.
     *
     * @param  int   $storageIdx Storage index
     * @param  int[] $ids        Property ids by preference (e.g. [0x0E06, 0x0039])
     * @param  int   $headerLen  Header length of the property stream
     * @return int|null
     */
    public function propTime(int $storageIdx, array $ids, int $headerLen): ?int
    {
        $props = $this->fixedProps($storageIdx, $headerLen);
        foreach ($ids as $id) {
            if (($props[$id]['type'] ?? 0) !== 0x0040) {
                continue;
            }
            $parts = unpack('Vlo/Vhi', $props[$id]['value']);
            $unix  = (int) (($parts['hi'] * 4294967296 + $parts['lo']) / 10000000) - 11644473600; // 100 ns since 1601-01-01
            if ($unix > 0) {
                return $unix;
            }
        }
        return null;
    }
}

/**
 * HTML wrapped by Outlook into an RTF body (MS-OXRTFEX, "\fromhtml1").
 *
 * The original HTML tags sit in {\*\htmltag ...} groups, the text between them is the HTML text,
 * and what was added for RTF readers only is fenced by \htmlrtf ... \htmlrtf0.
 */
class ReedcrmRtfHtml
{
    /** @var string[] Destinations whose content is never text, whatever the \htmlrtf state */
    const SKIPPED = ['colortbl', 'stylesheet', 'info', 'pict', 'object', 'header', 'footer', 'listtable',
        'listoverridetable', 'rsidtbl', 'generator', 'xmlnstbl', 'themedata', 'datastore', 'latentstyles', 'filetbl', 'revtbl'];

    /** @var array<string, string> Control words standing for a character */
    const SYMBOLS = ['lquote' => "\u{2018}", 'rquote' => "\u{2019}", 'ldblquote' => "\u{201C}", 'rdblquote' => "\u{201D}",
        'bullet' => "\u{2022}", 'endash' => "\u{2013}", 'emdash' => "\u{2014}", 'par' => "\r\n", 'line' => "\r\n", 'tab' => "\t"];

    /** @var array<int, int> \fcharset of a font => Windows code page of its \'hh bytes (0 = the one of the document) */
    const CHARSET_CODEPAGES = [0 => 0, 1 => 0, 2 => 1252, 77 => 10000, 128 => 932, 129 => 949, 130 => 1361, 134 => 936, 136 => 950,
        161 => 1253, 162 => 1254, 163 => 1258, 177 => 1255, 178 => 1256, 186 => 1257, 204 => 1251, 222 => 874, 238 => 1250, 254 => 437, 255 => 850];

    /** @var string Charset of the document (\ansicpg) */
    private $charset = 'Windows-1252';
    /** @var array<int, string> Font number => charset of its \'hh bytes */
    private $fontCharsets = [];
    /** @var string UTF-8 HTML */
    private $out = '';
    /** @var string Content of the htmltag / mhtmltag group being read */
    private $tagBuf = '';
    /** @var string|null Last mhtmltag, waiting for the htmltag that follows it */
    private $mhtml = null;
    /** @var string Text not converted yet, in the charset of the current font */
    private $bytes = '';
    /** @var int Fallback characters still to drop after a \uN */
    private $toSkip = 0;
    /** @var array{skip: bool, htmlrtf: bool, tag: string, uc: int, font: int, fonttbl: bool} State of the current group */
    private $state = ['skip' => false, 'htmlrtf' => false, 'tag' => '', 'uc' => 1, 'font' => -1, 'fonttbl' => false];

    /**
     * HTML of an RTF document.
     *
     * @param  string $rtf RTF document
     * @return string      UTF-8 HTML, '' when the RTF was not made from HTML
     */
    public function extract(string $rtf): string
    {
        $head = substr($rtf, 0, 2048);
        if (strpos($head, '\fromhtml') === false) {
            return '';
        }
        if (preg_match('/\\\\ansicpg(\d+)/', $head, $m)) {
            $this->charset = ReedcrmEmailMessage::codepageCharset((int) $m[1]);
        }

        $stack    = [];
        $newGroup = false; // right after '{': the next control word may name a destination
        $starred  = false; // \* met at the start of the group
        $fontDef  = null;  // font being described in the font table
        $len      = strlen($rtf);
        $i        = 0;
        while ($i < $len) {
            $c = $rtf[$i];
            if ($c === '{') {
                $this->flush();
                $stack[]  = $this->state;
                $newGroup = true;
                $starred  = false;
                $i++;
                continue;
            }
            if ($c === '}') {
                $this->flush();
                $this->closeGroup(array_pop($stack));
                $newGroup = false;
                $i++;
                continue;
            }
            if ($c === "\r" || $c === "\n") {
                $i++;
                continue;
            }
            if ($c !== '\\') {
                $newGroup = false;
                $this->text($c);
                $i++;
                continue;
            }

            $next = $rtf[$i + 1] ?? '';
            if ($next !== '' && ctype_alpha($next)) {
                preg_match('/\G\\\\([a-zA-Z]{1,32})(-?\d{1,10})? ?/', $rtf, $m, 0, $i);
                $i    += strlen($m[0]);
                $word  = $m[1];
                $param = isset($m[2]) ? (int) $m[2] : null; // an unmatched last group is left out of $m

                if ($newGroup) {
                    $newGroup = false;
                    if ($word === 'htmltag' || $word === 'mhtmltag') {
                        $this->state['tag'] = $word === 'htmltag' ? 'html' : 'mhtml';
                        $this->tagBuf       = '';
                        continue;
                    }
                    if ($word === 'fonttbl') {
                        $this->state['fonttbl'] = true;
                        $this->state['skip']    = true;
                        continue;
                    }
                    if ($starred || in_array($word, self::SKIPPED, true)) {
                        $this->state['skip'] = true;
                        continue;
                    }
                }
                if ($this->state['fonttbl']) {
                    if ($word === 'f') {
                        $fontDef = (int) $param;
                    } elseif ($word === 'fcharset' && $fontDef !== null && isset(self::CHARSET_CODEPAGES[(int) $param])) {
                        $codepage = self::CHARSET_CODEPAGES[(int) $param];
                        $this->fontCharsets[$fontDef] = $codepage > 0 ? ReedcrmEmailMessage::codepageCharset($codepage) : $this->charset;
                    }
                    continue;
                }
                if (!$this->state['skip']) {
                    $this->controlWord($word, $param);
                }
                continue;
            }

            // Control symbols
            $i += 2;
            if ($next === '*') {
                $starred = $starred || $newGroup;
                continue;
            }
            $newGroup = false;
            if ($next === "'") {
                $hex = substr($rtf, $i, 2);
                $i  += 2;
                if (ctype_xdigit($hex)) {
                    $this->text(chr((int) hexdec($hex)));
                }
            } elseif ($next === '\\' || $next === '{' || $next === '}') {
                $this->text($next);
            } elseif ($next === '~') {
                $this->write("\u{00A0}");
            } elseif ($next === "\r" || $next === "\n") {
                $this->write("\r\n");
            }
        }
        $this->flush();

        return $this->out . ($this->mhtml ?? '');
    }

    /**
     * End of a group: back to the state of the enclosing one, and output of the HTML tag it held.
     *
     * @param  array{skip: bool, htmlrtf: bool, tag: string, uc: int, font: int, fonttbl: bool}|null $parent State of the enclosing group
     * @return void
     */
    private function closeGroup(?array $parent): void
    {
        $closing = $this->state;
        if ($parent !== null) {
            $this->state = $parent;
        }
        if ($closing['tag'] === 'mhtml') {
            $this->mhtml = $this->tagBuf;
        } elseif ($closing['tag'] === 'html') {
            // Outlook doubles a tag it rewrote (e.g. an image turned into a cid: reference): keep the cid one
            $mhtml      = $this->mhtml;
            $useMhtml   = $mhtml !== null && strpos($mhtml, 'cid:') !== false && strpos($this->tagBuf, 'cid:') === false;
            $this->out .= ($useMhtml && $mhtml !== null) ? $mhtml : $this->tagBuf;
            $this->mhtml = null;
        }
        if ($closing['tag'] !== '' && $this->state['tag'] === '') {
            $this->tagBuf = '';
        }
    }

    /**
     * Control word met outside the font table and the skipped destinations.
     *
     * @param  string   $word  Control word
     * @param  int|null $param Its numeric parameter
     * @return void
     */
    private function controlWord(string $word, ?int $param): void
    {
        if ($word === 'f') {
            $this->flush();
            $this->state['font'] = (int) $param;
        } elseif ($word === 'htmlrtf') {
            $this->flush();
            $this->state['htmlrtf'] = $param !== 0;
        } elseif ($word === 'uc') {
            $this->state['uc'] = max(0, (int) $param);
        } elseif ($word === 'u' && $param !== null) {
            if ($this->emitting()) {
                $this->write((string) mb_chr($param < 0 ? $param + 65536 : $param, 'UTF-8'));
                $this->toSkip = $this->state['uc'];
            }
        } elseif (isset(self::SYMBOLS[$word]) && $this->emitting()) {
            if ($this->toSkip > 0) {
                $this->toSkip--;
            } else {
                $this->write(self::SYMBOLS[$word]);
            }
        }
    }

    /**
     * Byte of text in the charset of the current font. Kept until the next structural token,
     * as a multibyte character spans several \'hh.
     *
     * @param  string $byte Byte
     * @return void
     */
    private function text(string $byte): void
    {
        if (!$this->emitting()) {
            return;
        }
        if ($this->toSkip > 0) {
            $this->toSkip--;
            return;
        }
        $this->bytes .= $byte;
    }

    /**
     * Text already decoded.
     *
     * @param  string $utf8 UTF-8 text
     * @return void
     */
    private function write(string $utf8): void
    {
        if ($this->emitting()) {
            $this->flush();
            $this->sink($utf8);
        }
    }

    /**
     * Decode the pending bytes with the charset of the current font.
     *
     * @return void
     */
    private function flush(): void
    {
        if ($this->bytes === '') {
            return;
        }
        $text        = ReedcrmEmailMessage::convertCharset($this->bytes, $this->fontCharsets[$this->state['font']] ?? $this->charset);
        $this->bytes = '';
        $this->sink($text);
    }

    /**
     * Append decoded text to the HTML tag being read, or to the HTML.
     *
     * @param  string $utf8 UTF-8 text
     * @return void
     */
    private function sink(string $utf8): void
    {
        if ($this->state['tag'] !== '') {
            $this->tagBuf .= $utf8;
            return;
        }
        if ($this->mhtml !== null) {
            $this->out  .= $this->mhtml;
            $this->mhtml = null;
        }
        $this->out .= $utf8;
    }

    /**
     * Whether the text met now belongs to the HTML.
     *
     * @return bool
     */
    private function emitting(): bool
    {
        return !$this->state['skip'] && ($this->state['tag'] !== '' || !$this->state['htmlrtf']);
    }
}
