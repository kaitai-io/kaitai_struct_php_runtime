<?php
namespace Kaitai\Struct;

use Kaitai\Struct\Error\EndOfStreamError;
use Kaitai\Struct\Error\KaitaiError;
use Kaitai\Struct\Error\NoTerminatorFoundError;
use Kaitai\Struct\Error\NotSupportedPlatformError;
use Kaitai\Struct\Error\RotateProcessError;
use Kaitai\Struct\Error\ZlibProcessError;

class Stream {
    protected $stream;

    private const SIGN_MASK_16 = 0x8000;         // (1 << (16 - 1));
    private const SIGN_MASK_32 = 0x80000000;     // (1 << (32 - 1));

    private $bitsLeft;
    private $bits;

    /**
     * @param resource|string $stream
     */
    public function __construct($stream) {
        if (PHP_INT_SIZE !== 8) {
            throw new NotSupportedPlatformError("Only 64-bit platform is implemented");
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
                throw new KaitaiError("Unable to roll back after reading a byte in isEof");
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
            throw new KaitaiError("The position ($pos) must be less than the size ($size) of the stream");
        }
        $res = fseek($this->stream, $pos);
        if ($res !== 0) {
            throw new KaitaiError("Unable to set new position");
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
        return self::decodeSignedInt($this->readU2be(), self::SIGN_MASK_16);
    }

    public function readS4be(): int {
        return self::decodeSignedInt($this->readU4be(), self::SIGN_MASK_32);
    }

    public function readS8be(): int {
        // PHP does not support unsigned ints - all integers are signed. So
        // readU8be() actually returns a *signed* 64-bit integer, which is
        // exactly what we want here. See
        // <https://www.php.net/manual/en/function.unpack.php#refsect1-function.unpack-notes>:
        //
        // > **Caution** Note that PHP internally stores integral values as
        // > signed. If you unpack a large unsigned long and it is of the same
        // > size as PHP internally stored values the result will be a negative
        // > number even though unsigned unpacking was specified.
        return $this->readU8be();
    }

    // --
    // Little-endian

    public function readS2le(): int {
        return self::decodeSignedInt($this->readU2le(), self::SIGN_MASK_16);
    }

    public function readS4le(): int {
        return self::decodeSignedInt($this->readU4le(), self::SIGN_MASK_32);
    }

    public function readS8le(): int {
        // See comment above in readS8be()
        return $this->readU8le();
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
        return unpack("G", $this->readBytes(4))[1];
    }

    /**
     * Double precision floating-point number.
     */
    public function readF8be(): float {
        return unpack("E", $this->readBytes(8))[1];
    }

    // ---
    // Little-endian

    /**
     * Single precision floating-point number.
     */
    public function readF4le(): float {
        return unpack("g", $this->readBytes(4))[1];
    }

    /**
     * Double precision floating-point number.
     */
    public function readF8le(): float {
        return unpack("e", $this->readBytes(8))[1];
    }

    /**************************************************************************
     * Unaligned bit values
     **************************************************************************/

    public function alignToByte()/*: void */ {
        $this->bitsLeft = 0;
        $this->bits = 0;
    }

    public function readBitsIntBe(int $n): int {
        $res = 0;

        $bitsNeeded = $n - $this->bitsLeft;
        $this->bitsLeft = -$bitsNeeded & 7; // `-$bitsNeeded mod 8`

        if ($bitsNeeded > 0) {
            // 1 bit  => 1 byte
            // 8 bits => 1 byte
            // 9 bits => 2 bytes
            $bytesNeeded = (($bitsNeeded - 1) >> 3) + 1; // `ceil($bitsNeeded / 8)` (NB: `x >> 3` is `floor(x / 8)`)
            $buf = $this->readBytes($bytesNeeded);
            for ($i = 0; $i < $bytesNeeded; $i++) {
                $res = $res << 8 | ord($buf[$i]);
            }

            $newBits = $res;
            $res = self::zeroFillRightShift($res, $this->bitsLeft) | $this->bits << $bitsNeeded;
            $this->bits = $newBits; // will be masked at the end of the function
        } else {
            $res = self::zeroFillRightShift($this->bits, -$bitsNeeded); // shift unneeded bits out
        }

        $mask = (1 << $this->bitsLeft) - 1; // `bitsLeft` is in range 0..7, so `(1 << 63)` does not have to be considered
        $this->bits &= $mask;

        return $res;
    }

    /**
     * Unused since Kaitai Struct Compiler v0.9+ - compatibility with older versions
     *
     * @deprecated use {@link Stream::readBitsIntBe()} instead
     */
    public function readBitsInt(int $n): int {
        return $this->readBitsIntBe($n);
    }

    public function readBitsIntLe(int $n): int {
        $res = 0;
        $bitsNeeded = $n - $this->bitsLeft;

        if ($bitsNeeded > 0) {
            // 1 bit  => 1 byte
            // 8 bits => 1 byte
            // 9 bits => 2 bytes
            $bytesNeeded = (($bitsNeeded - 1) >> 3) + 1; // `ceil($bitsNeeded / 8)` (NB: `x >> 3` is `floor(x / 8)`)
            $buf = $this->readBytes($bytesNeeded);
            for ($i = 0; $i < $bytesNeeded; $i++) {
                $res |= ord($buf[$i]) << ($i * 8);
            }

            $newBits = self::zeroFillRightShift($res, $bitsNeeded);
            $res = $res << $this->bitsLeft | $this->bits;
            $this->bits = $newBits;
        } else {
            $res = $this->bits;
            $this->bits = self::zeroFillRightShift($this->bits, $n);
        }

        $this->bitsLeft = -$bitsNeeded & 7; // `-$bitsNeeded mod 8`

        $mask = self::getMaskOnes($n);
        $res &= $mask;
        return $res;
    }

    private static function getMaskOnes(int $n): int {
        // 1. (1 << 63) === PHP_INT_MIN (and yes, it is negative, because PHP uses signed 64-bit ints on 64-bit system),
        //    so (1 << 63) - 1 gets converted to float and loses precision (leading to incorrect result)
        // 2. (1 << 64) - 1 works fine, because (1 << 64) === 0 (it overflows) and -1 is exactly what we want
        //    (`php -r 'var_dump(decbin(-1));'` => string(64) "111...11")
        $bit = 1 << $n;
        return $bit === PHP_INT_MIN ? ~$bit : $bit - 1;
    }


    /**************************************************************************
     * Byte arrays
     **************************************************************************/

    public function readBytes(int $numberOfBytes): string {
        // It is legitimate to ask for 0 bytes in Kaitai Struct API,
        // but PHP's fread() considers this an error, so check and
        // handle this case before calling fread()
        if ($numberOfBytes == 0) {
            return '';
        }
        $bytes = fread($this->stream, $numberOfBytes);
        $n = strlen($bytes);
        if ($n < $numberOfBytes) {
            throw new EndOfStreamError($numberOfBytes, $n);
        }
        return $bytes;
    }

    public function readBytesFull(): string {
        return stream_get_contents($this->stream);
    }

    public function readBytesTerm($term, bool $includeTerm, bool $consumeTerm, bool $eosError): string {
        if (is_int($term)) {
            $term = chr($term);
        }
        $r = '';
        while (true) {
            $c = fgetc($this->stream);
            if ($c === false) {
                if ($eosError) {
                    throw new NoTerminatorFoundError($term);
                }
                break;
            }
            if ($c === $term) {
                if ($includeTerm) {
                    $r .= $c;
                }
                if (!$consumeTerm) {
                    $this->seek($this->pos() - 1);
                }
                break;
            }
            $r .= $c;
        }
        return $r;
    }

    public function readBytesTermMulti(string $term, bool $includeTerm, bool $consumeTerm, bool $eosError): string {
        $unitSize = strlen($term);

        // PHP's fread() considers asking for 0 bytes an error, so check and
        // handle this case before calling fread()
        if ($unitSize === 0) {
            return '';
        }

        $r = '';
        while (true) {
            $c = fread($this->stream, $unitSize);
            if ($c === false) {
                $c = '';
            }
            if (strlen($c) < $unitSize) {
                if ($eosError) {
                    throw new NoTerminatorFoundError($term);
                }
                $r .= $c;
                break;
            }
            if ($c === $term) {
                if ($includeTerm) {
                    $r .= $c;
                }
                if (!$consumeTerm) {
                    $this->seek($this->pos() - $unitSize);
                }
                break;
            }
            $r .= $c;
        }
        return $r;
    }

    /**
     * @deprecated Unused since Kaitai Struct Compiler v0.9+ - compatibility with older versions
     */
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

    public static function bytesTerminate(string $bytes, $term, bool $includeTerm): string {
        if (is_int($term)) {
            $term = chr($term);
        }
        $newLen = strpos($bytes, $term);
        if ($newLen === false) {
            return $bytes;
        } else {
            if ($includeTerm)
                $newLen++;
            return substr($bytes, 0, $newLen);
        }
    }

    public static function bytesTerminateMulti(string $bytes, string $term, bool $includeTerm): string {
        $unitSize = strlen($term);
        $searchIndex = strpos($bytes, $term);
        while (true) {
            if ($searchIndex === false) {
                return $bytes;
            }
            $mod = $searchIndex % $unitSize;
            if ($mod === 0) {
                return substr($bytes, 0, $searchIndex + ($includeTerm ? $unitSize : 0));
            }
            $searchIndex = strpos($bytes, $term, $searchIndex + ($unitSize - $mod));
        }
    }

    public static function bytesToStr(string $bytes, string $encoding): string {
        return iconv($encoding, 'utf-8', $bytes);
    }

    public static function substring(string $string, int $from, int $to): string {
        return iconv_substr($string, $from, $to - $from);
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
            $key = ord($key);
        }
        $xored = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $xored .= chr(ord($bytes[$i]) ^ $key);
        }
        return $xored;
    }

    public static function processXorMany(string $bytes, string $key): string {
        $keyLength = strlen($key);
        $xored = '';
        for ($i = 0, $j = 0, $n = strlen($bytes); $i < $n; $i++, $j = ($j + 1) % $keyLength) {
            $xored .= chr(ord($bytes[$i]) ^ ord($key[$j]));
        }
        return $xored;
    }

    public static function processRotateLeft(string $bytes, int $amount, int $groupSize): string {
        if ($groupSize !== 1) {
            throw new RotateProcessError("Unable to rotate group of $groupSize bytes yet");
        }
        $rotated = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $byte = ord($bytes[$i]);
            $rotated .= chr(($byte << $amount) | ($byte >> (8 - $amount)));
        }
        return $rotated;
    }

    public static function processZlib(string $bytes): string {
        $uncompressed = @gzuncompress($bytes);
        if (false === $uncompressed) {
            $error = error_get_last();
            error_clear_last();
            throw new ZlibProcessError($error['message']);
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
        // See https://graphics.stanford.edu/~seander/bithacks.html#VariableSignExtend
        return ($x ^ $mask) - $mask;
    }

    // From https://stackoverflow.com/a/14428473, modified
    private static function zeroFillRightShift(int $a, int $b): int {
        $res = $a >> $b;
        if ($a >= 0 || $b === 0) return $res;
        return $res & (PHP_INT_MAX >> ($b - 1));
    }
}
