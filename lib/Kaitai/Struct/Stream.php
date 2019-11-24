<?php
namespace Kaitai\Struct;

class Stream {
    protected $stream;

    const SIGN_MASK_16 = 0x8000;         // (1 << (16 - 1));
    const SIGN_MASK_32 = 0x80000000;     // (1 << (32 - 1));
    const SIGN_MASK_64 = 0x800000000000; // (1 << (64 - 1));

    private $bits, $bitsLeft;

    /**
     * @param resource|string $stream
     */
    public function __construct($stream) {
        if (PHP_INT_SIZE !== 8) {
            throw new \RuntimeException("Only 64-bit platform is implemented");
        }
        if (is_string($stream)) {
            $this->stream = fopen('php://memory', 'r+b');
            fwrite($this->stream, $stream);
        } else {
            $this->stream = $stream;
        }
        fseek($this->stream, 0, SEEK_SET);

        $this->alignToByte();
    }

    /**************************************************************************
     * Stream positioning
     **************************************************************************/

    public function isEof(): bool {
        if ($this->bitsLeft > 0) {
            return false;
        }

        // Unfortunately, feof() documentation in PHP is very unclear and,
        // in fact, its semantics follows C++ semantics with "read at least once
        // past the EOF first" => "set EOF flag on stream" => "eof returns true".
        // So, we'll have to emulate the same "one byte lookup" pattern from C++.

        if (fgetc($this->stream) === false) {
            // reached EOF
            return true;
        } else {
            // restore stream position, 1 byte back
            if (fseek($this->stream, -1, SEEK_CUR) !== 0) {
                throw new \RuntimeException("Unable to roll back after reading a byte in isEof");
            }
            return false;
        }
    }

    /**
     * @TODO: if $pos (int) > PHP_INT_MAX it becomes float in PHP.
     */
    public function seek(int $pos)/*: void */ {
        $size = $this->size();
        if ($pos > $size) {
            throw new \RuntimeException("The position ($pos) must be less than the size ($size) of the stream");
        }
        $res = fseek($this->stream, $pos);
        if ($res !== 0) {
            throw new \RuntimeException("Unable to set new position");
        }
    }

    public function pos(): int {
        return ftell($this->stream);
    }

    public function size(): int {
        return fstat($this->stream)['size'];
    }

    /**************************************************************************
     * Integer numbers
     **************************************************************************/

    /**************************************************************************
     * Signed
     */

    /**
     * Read 1 byte, signed integer
     */
    public function readS1(): int {
        return unpack("c", $this->readBytes(1))[1];
    }

    // ---
    // Big-endian

    public function readS2be(): int {
        return $this->decodeSignedInt($this->readU2be(), self::SIGN_MASK_16);
    }

    public function readS4be(): int {
        return $this->decodeSignedInt($this->readU4be(), self::SIGN_MASK_32);
    }

    public function readS8be(): int {
        $bytes = $this->readBytes(8);
        $highDw = unpack('N', substr($bytes, 0, 4))[1];
        $lowDw = unpack('N', substr($bytes, 4))[1];
        return ($highDw << 32) + $lowDw;
    }

    // --
    // Little-endian

    public function readS2le(): int {
        return $this->decodeSignedInt($this->readU2le(), self::SIGN_MASK_16);
    }

    public function readS4le(): int {
        return $this->decodeSignedInt($this->readU4le(), self::SIGN_MASK_32);
    }

    public function readS8le(): int {
        $bytes = $this->readBytes(8);
        $lowDw = unpack('V', substr($bytes, 0, 4))[1];
        $highDw = unpack('V', substr($bytes, 4))[1];
        return ($highDw << 32) + $lowDw;
    }

    /**************************************************************************
     * Unsigned
     */

    public function readU1(): int {
        return unpack("C", $this->readBytes(1))[1];
    }

    // ---
    // Big-endian

    public function readU2be(): int {
        return unpack("n", $this->readBytes(2))[1];
    }

    public function readU4be(): int {
        return unpack("N", $this->readBytes(4))[1];
    }

    public function readU8be(): int {
        return unpack("J", $this->readBytes(8))[1];
    }

    // ---
    // Little-endian

    public function readU2le(): int {
        return unpack("v", $this->readBytes(2))[1];
    }

    public function readU4le(): int {
        return unpack("V", $this->readBytes(4))[1];
    }

    public function readU8le(): int {
        return unpack("P", $this->readBytes(8))[1];
    }

    /**************************************************************************
     * Floating point numbers
     **************************************************************************/

    // ---
    // Big-endian

    /**
     * Single precision floating-point number
     */
    public function readF4be(): float {
        $bits = $this->readU4be();
        return $this->decodeSinglePrecisionFloat($bits);
    }

    /**
     * Double precision floating-point number.
     */
    public function readF8be(): float {
        $bits = $this->readU8be();
        return $this->decodeDoublePrecisionFloat($bits);
    }

    // ---
    // Little-endian

    /**
     * Single precision floating-point number.
     */
    public function readF4le(): float {
        $bits = $this->readU4le();
        return $this->decodeSinglePrecisionFloat($bits);
    }

    /**
     * Double precision floating-point number.
     */
    public function readF8le(): float {
        $bits = $this->readU8le();
        return $this->decodeDoublePrecisionFloat($bits);
    }

    /**************************************************************************
     * Unaligned bit values
     **************************************************************************/

    public function alignToByte()/*: void */ {
        $this->bits = 0;
        $this->bitsLeft = 0;
    }

    public function readBitsInt(int $n): int {
        $bitsNeeded = $n - $this->bitsLeft;
        if ($bitsNeeded > 0) {
            // 1 bit  => 1 byte
            // 8 bits => 1 byte
            // 9 bits => 2 bytes
            $bytesNeeded = intdiv($bitsNeeded - 1, 8) + 1;
            $buf = $this->readBytes($bytesNeeded);
            for ($i = 0; $i < $bytesNeeded; $i++) {
                $b = ord($buf[$i]);
                $this->bits <<= 8;
                $this->bits |= $b;
                $this->bitsLeft += 8;
            }
        }

        // raw mask with required number of 1s, starting from lowest bit
        $mask = (1 << $n) - 1;
        // shift mask to align with highest bits available in "bits"
        $shiftBits = $this->bitsLeft - $n;
        $mask <<= $shiftBits;
        // derive reading result
        $res = ($this->bits & $mask) >> $shiftBits;
        // clear top bits that we've just read => AND with 1s
        $this->bitsLeft -= $n;
        $mask = (1 << $this->bitsLeft) - 1;
        $this->bits &= $mask;

        return $res;
    }

    /**************************************************************************
     * Byte arrays
     **************************************************************************/

    public function readBytes(int $numberOfBytes): string {
        // It is legitimate to ask for 0 bytes in Kaitai Struct API,
        // but PHP's fread() considers this an error, so check &
        // handle this case before fread()
        if ($numberOfBytes == 0) {
            return '';
        }
        $bytes = fread($this->stream, $numberOfBytes);
        $n = strlen($bytes);
        if ($n < $numberOfBytes) {
            throw new \RuntimeException("Requested $numberOfBytes bytes, but got only $n bytes");
        }
        return $bytes;
    }

    public function readBytesFull(): string {
        return stream_get_contents($this->stream);
    }

    public function readBytesTerm($terminator, bool $includeTerminator, bool $consumeTerminator, bool $eosError): string {
        if (is_int($terminator)) {
            $terminator = chr($terminator);
        }
        $bytes = '';
        while (true) {
            if ($this->isEof()) {
                if ($eosError) {
                    throw new \RuntimeException("End of stream reached, but no terminator '$terminator' found");
                }
                break;
            }
            $byte = $this->readBytes(1);
            if ($byte === $terminator) {
                if ($includeTerminator) {
                    $bytes .= $byte;
                }
                if (!$consumeTerminator) {
                    $this->seek($this->pos() - 1);
                }
                break;
            }
            $bytes .= $byte;
        }
        return $bytes;
    }

    public function ensureFixedContents(string $expectedBytes): string {
        $length = strlen($expectedBytes);
        $bytes = $this->readBytes($length);
        if ($bytes !== $expectedBytes) {
            // @TODO: print expected and actual bytes
            throw new \RuntimeException("Expected bytes are not equal to actual bytes");
        }
        return $bytes;
    }

    public static function bytesStripRight(string $bytes, $padByte): string {
        if (is_int($padByte)) {
            $padByte = chr($padByte);
        }
        return rtrim($bytes, $padByte);
    }

    public static function bytesTerminate(string $bytes, $terminator, bool $includeTerminator): string {
        if (is_int($terminator)) {
            $terminator = chr($terminator);
        }
        $newLen = strpos($bytes, $terminator);
        if ($newLen === false) {
            return $bytes;
        } else {
            if ($includeTerminator)
                $newLen++;
            return substr($bytes, 0, $newLen);
        }
    }

    public static function bytesToStr(string $bytes, string $encoding): string {
        return iconv($encoding, 'utf-8', $bytes);
    }

    /**************************************************************************
     * Byte array processing
     **************************************************************************/

    /**
     * @param string $bytes
     * @param string|int $key
     * @return string
     */
    public static function processXorOne(string $bytes, $key): string {
        if (is_string($key)) {
            $key = self::strByteToUint($key);
        }
        $xored = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $xored .= chr(self::strByteToUint($bytes[$i]) ^ $key);
        }
        return $xored;
    }

    public static function processXorMany(string $bytes, string $key): string {
        $keyLength = strlen($key);
        $xored = '';
        for ($i = 0, $j = 0, $n = strlen($bytes); $i < $n; $i++, $j = ($j + 1) % $keyLength) {
            $xored .= chr(self::strByteToUint($bytes[$i]) ^ self::strByteToUint($key[$j]));
        }
        return $xored;
    }

    public static function processRotateLeft(string $bytes, int $amount, int $groupSize): string {
        if ($groupSize !== 1) {
            throw new \RuntimeException("Unable to rotate group of $groupSize bytes yet");
        }
        $rotated = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $byte = self::strByteToUint($bytes[$i]);
            $rotated .= chr(($byte << $amount) | ($byte >> (8 - $amount)));
        }
        return $rotated;
    }

    public static function processZlib(string $bytes): string {
        $uncompressed = @gzuncompress($bytes);
        if (false === $uncompressed) {
            $error = error_get_last();
            error_clear_last();
            throw new \RuntimeException($error['message']);
        }
        return $uncompressed;
    }

    /**************************************************************************
     * Misc runtime
     **************************************************************************/

    /**
     * Performs modulo operation between two integers: dividend `a`
     * and divisor `b`. Divisor `b` is expected to be positive. The
     * result is always 0 <= x <= b - 1.
     */
    public static function mod(int $a, int $b): int {
        return $a - (int)floor($a / $b) * $b;
    }

    public static function byteArrayMin(string $b): int {
        $min = PHP_INT_MAX;
        for ($i = 0, $n = strlen($b); $i < $n; $i++) {
            $value = ord($b[$i]);
            if ($value < $min)
                $min = $value;
        }
        return $min;
    }

    public static function byteArrayMax(string $b): int {
        $max = 0;
        for ($i = 0, $n = strlen($b); $i < $n; $i++) {
            $value = ord($b[$i]);
            if ($value > $max)
                $max = $value;
        }
        return $max;
    }

    /**************************************************************************
     * Internal
     **************************************************************************/

    private static function decodeSignedInt(int $x, int $mask): int {
        return ($x & ~$mask) - ($x & $mask);
    }

    private function decodeSinglePrecisionFloat(int $bits): float {
        $fractionToFloat = function (int $fraction): float {
            $val = 0;
            for ($i = 22, $j = 1; $i >= 0; $i--, $j++) {
                $bit = ((1 << $i) & $fraction) >> $i;
                $val += 2 ** (-$j) * $bit;
            }
            return $val;
        };

        // Sign - 31 bit, one bit
        $sign = ($bits >> 31) == 0 ? 1 : -1;

        // Exponent - [23..30] bits, 8 bits
        $exponent = ($bits >> 23) & 0xff;

        // Fraction/mantissa/significand - [22..0] bits, 23 bits,
        $fraction = $bits & 0x7fffff;

        if (0 === $exponent) {
            if ($fraction === 0) {
                // $exponent === 0, $fraction === 0.
                // We use 0.0 to have ability to return -0.0, the integer 0 does not work.
                return $sign * 0.0;
            }
            // $exponent === 0, $fraction !== 0 => return denormalized number
            return $sign * 2 ** (-126) * $fractionToFloat($fraction);
        } elseif (255 === $exponent) {
            if ($fraction !== 0) {
                // $exponent === 255, $fraction !== 0.
                return NAN;
            }
            // $exponent === 255, $fraction === 0.
            return $sign * INF;
        }

        // $exponent is not either 0 or 255.
        return $sign * 2 ** ($exponent - 127) * (1 + $fractionToFloat($fraction));
    }

    private function decodeDoublePrecisionFloat(int $bits): float {
        $fractionToFloat = function (int $fraction): float {
            $val = 0;
            for ($i = 51, $j = 1; $i >= 0; $i--, $j++) {
                $bit = ((1 << $i) & $fraction) >> $i;
                $val += 2 ** (-$j) * $bit;
            }
            return $val;
        };

        // Sign - 63 bit, one bit
        $sign = ($bits >> 63) == 0 ? 1 : -1;

        // Exponent - [52..62] bits, 11 bits
        $exponent = ($bits >> 52) & 0x7ff;

        // Fraction/mantissa/significand - [51..0] bits, 52 bits,
        $fraction = $bits & 0xfffffffffffff;

        if (0 === $exponent) {
            if ($fraction === 0) {
                // $exponent === 0, $fraction === 0.
                // We use 0.0 to have ability to return -0.0, the integer 0 does not work.
                return $sign * 0.0;
            }
            // $exponent === 0, $fraction !== 0 => return denormalized number
            return $sign * 2 ** (-1022) * $fractionToFloat($fraction);
        } elseif (2047 === $exponent) {
            if ($fraction !== 0) {
                // $exponent === 2047, $fraction !== 0.
                return NAN;
            }
            // $exponent === 2047, $fraction === 0.
            return $sign * INF;
        }

        // $exponent is not either 0 or 2047.
        return $sign * 2 ** ($exponent - 1023) * (1 + $fractionToFloat($fraction));
    }

    private static function strByteToUint(string $byte): int {
        // May be just ord()??
        return unpack("C", $byte)[1];
    }
}
