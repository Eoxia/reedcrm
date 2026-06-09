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
     * @var array[] Each: ['filename'=>string,'mime'=>string,'data'=>string,'cid'=>string,'inline'=>bool]
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
        $ole = new ReedcrmOle2Reader($bytes);
        $msg = new self();
        $root = 0;

        $msg->subject   = trim((string) $ole->prop($root, '0037'));
        $msg->fromName  = trim((string) $ole->prop($root, '0C1A'));
        $msg->fromEmail = trim((string) ($ole->prop($root, '5D01') ?: $ole->prop($root, '0C1F')));

        $to = trim((string) $ole->prop($root, '0E04'));
        $cc = trim((string) $ole->prop($root, '0E03'));
        $msg->to = $to !== '' ? array_map('trim', explode(';', $to)) : [];
        $msg->cc = $cc !== '' ? array_map('trim', explode(';', $cc)) : [];

        $html = $ole->prop($root, '1013'); // PR_BODY_HTML (binary)
        $text = $ole->prop($root, '1000'); // PR_BODY (unicode)
        if ($html !== null && $html !== '') {
            $msg->htmlBody = self::toUtf8($html);
        }
        if ($text !== null) {
            $msg->textBody = (string) $text;
        }

        // Date: FILETIME PR_MESSAGE_DELIVERY_TIME (0E06) or PR_CLIENT_SUBMIT_TIME (0039)
        $msg->date = $ole->topFiletime(['0E06', '0039']);

        // Attachments
        foreach ($ole->children($root) as $c) {
            if (stripos($c['name'], '__attach_version1.0') !== 0) {
                continue;
            }
            $idx  = $c['idx'];
            $name = $ole->prop($idx, '3707') ?: $ole->prop($idx, '3704'); // long / short filename
            $data = $ole->propRaw($idx, '3701');                          // PR_ATTACH_DATA_BIN
            $mime = $ole->prop($idx, '370E');                             // PR_ATTACH_MIME_TAG
            $cid  = trim((string) $ole->prop($idx, '3712'));              // PR_ATTACH_CONTENT_ID
            if ($data === null) {
                continue;
            }
            $msg->attachments[] = [
                'filename' => trim((string) $name) !== '' ? trim((string) $name) : 'attachment',
                'mime'     => trim((string) $mime),
                'data'     => $data,
                'cid'      => $cid,
                'inline'   => $cid !== '',
            ];
        }

        return $msg;
    }

    /* ===================================================================== */
    /*  .eml  (RFC822 / MIME)                                                 */
    /* ===================================================================== */

    /**
     * Parse a MIME .eml from its raw bytes.
     *
     * @param  string $bytes Raw file content
     * @return self
     */
    public static function fromEml(string $bytes): self
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
            $ts = strtotime($headers['date']);
            $msg->date = $ts !== false ? $ts : null;
        }

        self::walkMimePart($headers, $body, $msg);

        return $msg;
    }

    /**
     * Recursively walk a MIME part, filling bodies and attachments.
     *
     * @param  array  $headers Part headers (lowercased keys)
     * @param  string $body    Part body
     * @param  self   $msg     Accumulator
     * @return void
     */
    private static function walkMimePart(array $headers, string $body, self $msg): void
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
                self::walkMimePart(self::parseHeaders($ph), $pb, $msg);
            }
            return;
        }

        $encoding = strtolower(trim($headers['content-transfer-encoding'] ?? '7bit'));
        $decoded  = self::decodeBody($body, $encoding);
        $charset  = self::headerParam($headers['content-type'] ?? '', 'charset') ?: 'UTF-8';

        $disp     = strtolower($headers['content-disposition'] ?? '');
        $filename = self::decodeHeader(self::headerParam($headers['content-disposition'] ?? '', 'filename')
            ?: self::headerParam($headers['content-type'] ?? '', 'name'));
        $cid      = trim((string) ($headers['content-id'] ?? ''), " <>\t");
        $isAttach = strpos($disp, 'attachment') === 0 || $filename !== '';

        if (!$isAttach && $mime === 'text/html') {
            $msg->htmlBody = self::convertCharset($decoded, $charset);
        } elseif (!$isAttach && $mime === 'text/plain') {
            $msg->textBody = self::convertCharset($decoded, $charset);
        } else {
            $msg->attachments[] = [
                'filename' => $filename !== '' ? $filename : 'attachment',
                'mime'     => $mime,
                'data'     => $decoded,
                'cid'      => $cid,
                'inline'   => $cid !== '' || strpos($disp, 'inline') === 0,
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
     * @return array
     */
    private static function parseHeaders(string $raw): array
    {
        $raw     = preg_replace("/\n[ \t]+/", ' ', $raw); // unfold
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
     * Extract a parameter (e.g. boundary, charset, filename) from a header value.
     *
     * @param  string $header Header value
     * @param  string $param  Parameter name
     * @return string
     */
    private static function headerParam(string $header, string $param): string
    {
        if (preg_match('/;\s*' . preg_quote($param, '/') . '\s*=\s*"([^"]*)"/i', $header, $m)) {
            return $m[1];
        }
        if (preg_match('/;\s*' . preg_quote($param, '/') . '\s*=\s*([^;\s]+)/i', $header, $m)) {
            return $m[1];
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
        $parts = preg_split('/(?:^|\n)--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\n/', $body);
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
            return base64_decode(preg_replace('/\s+/', '', $body)) ?: '';
        }
        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($body);
        }
        return $body;
    }

    /**
     * Decode an RFC2047 encoded header (=?charset?B/Q?...?=).
     *
     * @param  string $value Raw header value
     * @return string UTF-8
     */
    private static function decodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_decode_mimeheader')) {
            $prev = mb_internal_encoding();
            mb_internal_encoding('UTF-8');
            $out = mb_decode_mimeheader($value);
            mb_internal_encoding($prev);
            return $out;
        }
        return $value;
    }

    /**
     * Convert a string from a charset to UTF-8.
     *
     * @param  string $s       Input
     * @param  string $charset Source charset
     * @return string
     */
    private static function convertCharset(string $s, string $charset): string
    {
        $charset = strtoupper(trim($charset)) ?: 'UTF-8';
        if ($charset === 'UTF-8' || $charset === 'US-ASCII') {
            return $s;
        }
        $out = @iconv($charset, 'UTF-8//IGNORE', $s);
        return $out !== false ? $out : $s;
    }

    /**
     * Best-effort conversion of MAPI HTML body bytes to UTF-8.
     *
     * @param  string $s Body bytes
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
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value);
        return array_values(array_filter(array_map('trim', $parts), 'strlen'));
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
    /** @var array[] index => directory entry */
    private $dir = [];
    /** @var string */
    private $miniStream = '';

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
        return unpack('v', substr($this->data, $o, 2))[1];
    }

    /**
     * @param  int $o Offset
     * @return int
     */
    private function u32(int $o): int
    {
        return unpack('V', substr($this->data, $o, 4))[1];
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
     * @return string
     */
    private function readChain(int $start): string
    {
        $out = '';
        $s   = $start;
        $g   = 0;
        while ($s >= 0 && $s < 0xFFFFFFFA && $g++ < 2000000) {
            $out .= $this->sector($s);
            $s    = $this->fat[$s] ?? 0xFFFFFFFE;
        }
        return $out;
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
        while ($s >= 0 && $s < 0xFFFFFFFA && $g++ < 2000000) {
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
        $this->sectorSize     = 1 << $this->u16(30);
        $this->miniSectorSize = 1 << $this->u16(32);
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
        $ds = $firstDifat;
        $g  = 0;
        while ($ds < 0xFFFFFFFA && $g++ < 100000) {
            $sec = $this->sector($ds);
            $cnt = intdiv($this->sectorSize, 4);
            for ($i = 0; $i < $cnt - 1; $i++) {
                $v = unpack('V', substr($sec, $i * 4, 4))[1];
                if ($v < 0xFFFFFFFE) {
                    $difat[] = $v;
                }
            }
            $ds = unpack('V', substr($sec, ($cnt - 1) * 4, 4))[1];
        }
        foreach ($difat as $fs) {
            $sec = $this->sector($fs);
            $cnt = intdiv($this->sectorSize, 4);
            for ($i = 0; $i < $cnt; $i++) {
                $this->fat[] = unpack('V', substr($sec, $i * 4, 4))[1];
            }
        }

        $dirData = $this->readChain($firstDir);
        $n       = intdiv(strlen($dirData), 128);
        for ($i = 0; $i < $n; $i++) {
            $e       = substr($dirData, $i * 128, 128);
            $nameLen = unpack('v', substr($e, 64, 2))[1];
            $name    = $nameLen > 0 ? mb_convert_encoding(substr($e, 0, max(0, $nameLen - 2)), 'UTF-8', 'UTF-16LE') : '';
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

        $root             = $this->dir[0];
        $this->miniStream = substr($this->readChain($root['start']), 0, $root['size']);

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
     * @param  int   $idx Node index
     * @param  int[] $acc Accumulator
     * @return void
     */
    private function siblings(int $idx, array &$acc): void
    {
        if ($idx < 0 || $idx >= 0xFFFFFFFA || !isset($this->dir[$idx])) {
            return;
        }
        $this->siblings($this->dir[$idx]['left'], $acc);
        $acc[] = $idx;
        $this->siblings($this->dir[$idx]['right'], $acc);
    }

    /**
     * Children entries of a storage entry.
     *
     * @param  int $storageIdx Storage index
     * @return array[]
     */
    public function children(int $storageIdx): array
    {
        $acc   = [];
        $child = $this->dir[$storageIdx]['child'] ?? 0xFFFFFFFE;
        $this->siblings($child, $acc);
        return array_map(fn($i) => $this->dir[$i] + ['idx' => $i], $acc);
    }

    /**
     * Raw bytes of a stream entry.
     *
     * @param  array $entry Directory entry
     * @return string
     */
    public function streamData(array $entry): string
    {
        if ($entry['size'] < $this->miniCutoff && $entry['type'] == 2) {
            return $this->readMiniChain($entry['start'], $entry['size']);
        }
        return substr($this->readChain($entry['start']), 0, $entry['size']);
    }

    /**
     * Decoded MAPI string/binary property under a storage, by 4-hex tag id.
     *
     * @param  int    $storageIdx Storage index
     * @param  string $tagId      Property id (e.g. '0037')
     * @return string|null
     */
    public function prop(int $storageIdx, string $tagId): ?string
    {
        foreach ($this->children($storageIdx) as $c) {
            if (stripos($c['name'], '__substg1.0_' . $tagId) === 0) {
                $raw  = $this->streamData($c);
                $type = strtoupper(substr($c['name'], -4));
                if ($type === '001F') {
                    return mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
                }
                if ($type === '001E') {
                    return mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
                }
                return $raw;
            }
        }
        return null;
    }

    /**
     * Raw (undecoded) bytes of a binary property under a storage.
     *
     * @param  int    $storageIdx Storage index
     * @param  string $tagId      Property id
     * @return string|null
     */
    public function propRaw(int $storageIdx, string $tagId): ?string
    {
        foreach ($this->children($storageIdx) as $c) {
            if (stripos($c['name'], '__substg1.0_' . $tagId) === 0) {
                return $this->streamData($c);
            }
        }
        return null;
    }

    /**
     * Read a FILETIME property from the top-level __properties stream and return a unix timestamp.
     *
     * @param  string[] $tagIds Property ids to try in order (e.g. ['0E06','0039'])
     * @return int|null
     */
    public function topFiletime(array $tagIds): ?int
    {
        $props = null;
        foreach ($this->children(0) as $c) {
            if (strcasecmp($c['name'], '__properties_version1.0') === 0) {
                $props = $this->streamData($c);
                break;
            }
        }
        if ($props === null) {
            return null;
        }
        $headerLen = 32; // top-level message property header
        for ($o = $headerLen; $o + 16 <= strlen($props); $o += 16) {
            $type = unpack('v', substr($props, $o, 2))[1];
            $id   = unpack('v', substr($props, $o + 2, 2))[1];
            if ($type !== 0x0040) { // PT_SYSTIME
                continue;
            }
            $idHex = strtoupper(sprintf('%04X', $id));
            if (!in_array($idHex, array_map('strtoupper', $tagIds), true)) {
                continue;
            }
            $lo = unpack('V', substr($props, $o + 8, 4))[1];
            $hi = unpack('V', substr($props, $o + 12, 4))[1];
            $ft = $hi * 4294967296 + $lo; // 100ns since 1601-01-01
            $unix = (int) ($ft / 10000000) - 11644473600;
            if ($unix > 0) {
                return $unix;
            }
        }
        return null;
    }
}
