<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace AS2;

class Utils
{
    /**
     * @param  string  $content
     *
     * @return string
     */
    public static function canonicalize($content)
    {
        return str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $content);
    }

    /**
     * @param  string  $mic
     *
     * @return string
     */
    public static function normalizeMic($mic)
    {
        $parts = explode(',', $mic, 2);
        $parts[1] = strtolower(trim(str_replace('-', '', $parts[1])));

        return implode(',', $parts);
    }

    /**
     * Return decoded base64, if it is not a base64 string, returns false.
     *
     * @param  string  $data
     *
     * @return bool|string
     */
    public static function decodeBase64($data)
    {
        return base64_decode($data, true);
    }

    /**
     * Decode string if it is base64 encoded, if not, return the original string.
     *
     * @param  string  $data
     *
     * @return string
     */
    public static function normalizeBase64($data)
    {
        $decoded = self::decodeBase64($data);
        if ($decoded && base64_encode($decoded) === str_replace(["\r", "\n"], '', $data)) {
            return $decoded;
        }

        return $data;
    }

    /**
     * Parses an HTTP message into an associative array.
     *
     * The array contains the "headers" key containing an associative array of header
     * array values, and a "body" key containing the body of the message.
     *
     * @param  string  $message  HTTP request or response to parse
     * @param  string  $EOL  End of line
     *
     * @return array
     */
    public static function parseMessage($message, $EOL = "\n")
    {
        if (empty($message)) {
            throw new \InvalidArgumentException('Invalid message');
        }

        // $firstLinePos = strpos($message, "\n");
        // $firstLine = $firstLinePos === false ? $message : substr($message, 0, $firstLinePos);

        // // /** @noinspection NotOptimalRegularExpressionsInspection */
        // if (! preg_match('%^[^\s]+[^:]*:%', $firstLine)) {
        //     $headers = [];
        //     $body = $message;
        //     return compact('headers', 'body');
        // }

        // // messages as returned by many mail servers
        // $headersEOL = $EOL;

        // // find an empty line between headers and body
        // // default is set new line
        // if (strpos($message, $EOL.$EOL)) {
        //     [$headers, $body] = explode($EOL.$EOL, $message, 2);
        //     // next is the standard new line
        // } elseif ($EOL !== "\r\n" && strpos($message, "\r\n\r\n")) {
        //     [$headers, $body] = explode("\r\n\r\n", $message, 2);
        //     $headersEOL = "\r\n"; // Headers::fromString will fail with incorrect EOL
        //     // next is the other "standard" new line
        // } elseif ($EOL !== "\n" && strpos($message, "\n\n")) {
        //     [$headers, $body] = explode("\n\n", $message, 2);
        //     $headersEOL = "\n";
        //     // at last resort find anything that looks like a new line
        // } else {
        //     // [$headers, $body] = preg_split("%([\r\n]+)\\1%U", $message, 2);
        //     $headers = '';
        //     $body = $message;
        // }

        // $headers = self::parseHeaders($headers, $headersEOL);

        // return compact('headers', 'body');
        $message = trim($message);
        $firstLinePos = strpos($message, "\n");
        if ($firstLinePos === false || $firstLinePos <= 3) {
            // even if $message looks like header, I will set it to body
            return [
                'headers' => [],
                'body' => trim($message)
            ];
        }
        $pos2LF = strpos($message, "\n\n");
        $pos2CRLF = strpos($message, "\r\n\r\n");
        if ($pos2LF === false) {
            // $pos2CRLF has value
            $EOL = "\r\n";
        } else if ($pos2CRLF === false) {
            // $pos2LF  has value
            $EOL = "\n";
        } else {
            // both have value , check who has the lowest position
            $EOL = $pos2LF < $pos2CRLF ? "\n" : "\r\n";
        }

        // Split headers and body
        [$headers, $body] = array_pad(explode($EOL . $EOL, $message, 2), 2, '');

        $parsedHeaders = [];

        // !!prevent below string from getting 3 headers( we need 2):
        // Date: Thu, 8 May 2025 19:50:31 +0800 (CST)
        // Content-Type: multipart/report; report-type=disposition-notification; 
        //     boundary="----=_Part_95_1990814047.1746705031593

        $headers = preg_replace('/;\s*\r?\n\s*/', ';', $headers);
        // $headers =  preg_replace('/\r\n/', "\n", $headers);
        foreach (explode($EOL, trim($headers)) as $headerLine) {
            if (strlen($headerLine) < 4) {
                // avoid getting ":" header things
                continue;
            }
            [$name, $value] = array_pad(explode(':', $headerLine, 2),2,'');
            $parsedHeaders[trim($name)] = trim($value);
        }

        return [
            'headers' => $parsedHeaders,
            'body' => trim((string) $body) // Cast to empty string if null
        ];


    }

    public static function parseHeaders($headers, $oel = "\n")
    {
        $lines = preg_split('/(\\r?\\n)/', $headers, -1, PREG_SPLIT_DELIM_CAPTURE);
        // $lines = explode($oel, $headers);
        $headers = [];

        foreach ($lines as $line) {
            if (strpos($line, ':')) {
                $parts = explode(':', $line, 2);
                $key = trim($parts[0]);
                $value = isset($parts[1]) ? trim($parts[1]) : '';
                $headers[$key][] = $value;
            } elseif (str_contains($line, 'boundary=')) {
                foreach ($headers as $k => &$v) {
                    if (strtolower($k) === 'content-type') {
                        $v[0] .= $line;

                        break;
                    }
                }
            }
        }

        return $headers;
    }

    /**
     * Parse an array of header values containing ";" separated data into an
     * array of associative arrays representing the header key value pair
     * data of the header. When a parameter does not contain a value, but just
     * contains a key, this function will inject a key with a '' string value.
     *
     * @param  array|string  $header  header to parse into components
     *
     * @return array returns the parsed header values
     */
    public static function parseHeader($header)
    {
        static $trimmed = "'\" \t\n\r\0\x0B";
        $params = [];
        foreach (self::normalizeHeader($header) as $val) {
            $part = [];
            foreach (preg_split('/;(?=([^"]*"[^"]*")*[^"]*$)/', $val) as $kvp) {
                $m = explode('=', $kvp, 2);
                if (isset($m[1])) {
                    $part[trim($m[0], $trimmed)] = trim($m[1], $trimmed);
                } else {
                    $part[] = trim($m[0], $trimmed);
                }
            }
            if ($part) {
                $params[] = $part;
            }
        }

        return $params;
    }

    /**
     * Converts an array of header values that may contain comma separated
     * headers into an array of headers with no comma separated values.
     *
     * @param  array|string  $header  header to normalize
     *
     * @return array returns the normalized header field values
     */
    public static function normalizeHeader($header)
    {
        if (!\is_array($header)) {
            return array_map('trim', explode(',', $header));
        }
        $result = [];
        foreach ($header as $value) {
            foreach ((array) $value as $v) {
                if (!str_contains($v, ',')) {
                    $result[] = $v;

                    continue;
                }
                foreach (preg_split('/,(?=([^"]*"[^"]*")*[^"]*$)/', $v) as $vv) {
                    $result[] = trim($vv);
                }
            }
        }

        return $result;
    }

    /**
     * Converts an array of header values that may contain comma separated
     * headers into a string representation.
     *
     * @param  string[][]  $headers
     * @param  string  $eol
     *
     * @return string
     */
    public static function normalizeHeaders($headers, $eol = "\r\n")
    {
        $result = '';
        foreach ($headers as $name => $values) {
            $values = implode(', ', (array) $values);
            if ($name === 'Content-Type') {
                // some servers don't support "x-"
                $values = str_replace('x-pkcs7-', 'pkcs7-', $values);
            }
            $result .= $name . ': ' . $values . $eol;
        }

        return $result;
    }

    /**
     * Encode a given string in base64 encoding and break lines
     * according to the maximum line length.
     *
     * @param  string  $str
     * @param  int  $lineLength
     * @param  string  $lineEnd
     *
     * @return string
     */
    public static function encodeBase64($str, $lineLength = 64, $lineEnd = "\r\n")
    {
        $lineLength -= ($lineLength % 4);

        return rtrim(chunk_split(base64_encode($str), $lineLength, $lineEnd));
    }

    /**
     * Generate Unique Message Id
     * TODO: uuid4.
     *
     * @return string
     */
    public static function generateMessageID($partner = null)
    {
        if ($partner instanceof PartnerInterface) {
            $partner = $partner->getAs2Id();
        }

        return date('Y-m-d')
            . '-' .
            uniqid('', true)
            . '@' .
            ($partner ? strtolower($partner) . '.' : '')
            .
            str_replace(' ', '', php_uname('n'));
    }

    /**
     * Generate random string.
     *
     * @param  int  $length
     * @param  string  $charList
     *
     * @return string
     * @throws \Exception
     */
    public static function random($length = 10, $charList = '0-9a-z')
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $charList = count_chars(
            preg_replace_callback(
                '#.-.#',
                static function (array $m) {
                    return implode('', range($m[0][0], $m[0][2]));
                },
                $charList
            ),
            3
        );
        $chLen = \strlen($charList);

        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be greater than zero.');
        }

        if ($chLen < 2) {
            throw new \InvalidArgumentException('Character list must contain as least two chars.');
        }

        $res = '';
        for ($i = 0; $i < $length; $i++) {
            $res .= $charList[random_int(0, $chLen - 1)];
        }

        return $res;
    }

    /**
     * Checks if the string is valid for UTF-8 encoding.
     *
     * @param  string  $s
     *
     * @return bool
     */
    public static function checkEncoding($s)
    {
        return $s === self::fixEncoding($s);
    }

    /**
     * Removes invalid code unit sequences from UTF-8 string.
     *
     * @param  string  $s
     *
     * @return bool
     */
    public static function fixEncoding($s)
    {
        // removes xD800-xDFFF, x110000 and higher
        return htmlspecialchars_decode(htmlspecialchars($s, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES);
    }

    /**
     * Verify if the content is binary.
     */
    public static function isBinary($str): bool
    {
        $str = str_ireplace(["\t", "\n", "\r"], ['', '', ''], $str);

        return \is_string($str) && ctype_print($str) === false;
    }
}
