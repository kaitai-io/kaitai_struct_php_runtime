<?php
namespace Kaitai\Struct;

class Stream {
    protected $stream;

    const SIGN_MASK_16 = 0x8000;         // (1 << (16 - 1));
    const SIGN_MASK_32 = 0x80000000;     // (1 << (32 - 1));
    const SIGN_MASK_64 = 0x800000000000; // (1 << (64 - 1));

    /**
     * @param resource $stream
     */
    public function __construct($stream) {
        if (PHP_INT_SIZE < 8) {
            throw new \RuntimeException("At least 64-bit platform is required");
        }
        $this->stream = $stream;
        fseek($this->stream, 0, SEEK_SET);
    }

    /**************************************************************************
     * 1. Stream positioning
     **************************************************************************/

    public function isEof(): bool {
        return feof($this->stream);
    }

    /**
     * @TODO: if $pos (int) > PHP_INT_MAX it becomes float in PHP.
     */
    public function seek(int $pos)/*: void */ {
        if ($pos >= $this->size()) {
            throw new \RuntimeException("The position must be < size of the stream");
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
        // strlen(stream_get_contents($stream))
        // return $this->size;
        return fstat($this->stream)['size'];
    }

    /**************************************************************************
     * 2. Integer numbers
     **************************************************************************/

    /**************************************************************************
     * 2.1. Signed
     */

    /**
     * Read 1 byte, signed integer
     */
    public function readS1(): int {
        return unpack("c", $this->readBytes(1))[1];
    }
    
    // ---
    // 2.1.1. Big-endian
    
    public function readS2be(): int {
        return $this->toSigned($this->readU2be(), self::SIGN_MASK_16);
    }

    public function readS4be(): int {
        return $this->toSigned($this->readU4be(), self::SIGN_MASK_32);
    }

    public function readS8be(): int {
        $bytes = $this->readBytes(8);
        throw new \RuntimeException("Not implemented yet");

        // \xf-\x8
        // @TODO

        /*
        if ($bytes === "\xff\xff\xff\xff\xff\xff\xff\xff") {
            return -1;
        }
        $x = $this->readU8be();
         if ($x < 0) {
            throw new \OutOfBoundsException();
        }
        return $this->toSigned($x, self::SIGN_MASK_64);
        */
    }
    
    // --
    // 2.1.2. Little-endian

    public function readS2le(): int {
        return $this->toSigned($this->readU2le(), self::SIGN_MASK_16);
    }
    
    public function readS4le(): int {
        return $this->toSigned($this->readU4le(), self::SIGN_MASK_32);
    }

    public function readS8le(): int {
        // @TODO
        throw new \RuntimeException("Not implemented yet");

        /*
        unless @@big_endian
        def read_s8le
          read_bytes(8).unpack('q')[0]
        end
        else
        def read_s8le
          to_signed(read_u8le, SIGN_MASK_64)
        end
        end
         */
    }

    /**************************************************************************
     * 2.2. Unsigned
     */

    public function readU1(): int {
        return unpack("C", $this->readBytes(1))[1];
    }

    // ---
    // 2.2.1. Big-endian

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
    // 2.2.2. Little-endian

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
     * 3. Floating point numbers
     **************************************************************************/

    // ---
    // 3.1. Big-endian

    public function readF4be(): float {
        $bytes = $this->readBytes(4);

        //read_bytes(4).unpack('g')[0]
        throw new \RuntimeException("Not implemented yet");
    }

    public function readF8be(): float {
        $bytes = $this->readBytes(8);

        //read_bytes(8).unpack('G')[0]
        throw new \RuntimeException("Not implemented yet");
    }

    // ---
    // 3.2. Little-endian

    public function readF4le(): float {
        $bytes = $this->readBytes(4);

        //read_bytes(4).unpack('e')[0]
        throw new \RuntimeException("Not implemented yet");
    }

    public function readF8le(): float {
        $bytes = $this->readBytes(8);

        //read_bytes(8).unpack('E')[0]

        throw new \RuntimeException("Not implemented yet");

    }

    /**************************************************************************
     * 4. Byte arrays
     **************************************************************************/

    public function readBytes(int $numberOfBytes): string {
        //return stream_get_contents($this->stream, $numberOfBytes);
        return fread($this->stream, $numberOfBytes);
    }

    public function readBytesFull(): string {
        return stream_get_contents($this->stream);
        /*
        $bytes = '';
        while (!feof($this->stream)) {
            $bytes .= fread($this->stream, 8192);
        }
        return $bytes;
        */
    }

    public function ensureFixedContents(int $length, string $expectedBytes): string {
        $bytes = $this->readBytes($length);
        if ($bytes !== $expectedBytes) {
            // @TODO: print expected and actual bytes
            throw new \RuntimeException("Expected bytes are not equal to actual bytes");
        }
        return $bytes;
    }

    /**************************************************************************
     * 5. Strings
     **************************************************************************/

    public function readStrEos(string $outputEncoding): string {
        return $this->bytesToEncoding($this->readBytesFull(), $outputEncoding);
    }

    public function readStrByteLimit(int $numberOfBytes, string $outputEncoding): string {
        return $this->bytesToEncoding($this->readBytes($numberOfBytes), $outputEncoding);
    }

    public function readStrz(string $outputEncoding, string $terminator, bool $includeTerminator, bool $consumeTerminator, bool $eosError): string {
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
        return $this->bytesToEncoding($bytes, $outputEncoding);
    }

    /**************************************************************************
     * 6. Byte array processing
     **************************************************************************/

    /**
     * @param string $bytes
     * @param string|int $key
     * @return string
     */
    public function processXorOne(string $bytes, $key): string {
        if (is_string($key)) {
            // unsigned integer
            $key = unpack("C", $key)[1];
        }
        $xored = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $xored .= chr(unpack("C", $bytes[$i])[1] ^ $key);
        }
        return $xored;
    }

    public function processXorMany(string $bytes, string $key): string {
        $keyLength = strlen($key);
        $xored = '';
        for ($i = 0, $j = 0, $n = strlen($bytes); $i < $n; $i++, $j = ($j + 1) % $keyLength) {
            $xored .= chr(unpack("C", $bytes[$i])[1] ^ unpack("C", $key[$j])[1]);
        }
        return $xored;
    }

    public function processRotateLeft($bytes, int $amount, int $groupSize)/*byte */ {
        throw new \RuntimeException("Not implemented yet");
/*
public byte[] ProcessRotateLeft(byte[] data, int amount, int groupSize)
{
    if (amount > 7 || amount < -7) throw new ArgumentException("Rotation of more than 7 cannot be performed.", nameof(amount));
    if (amount < 0) amount += 8; // Rotation of -2 is the same as rotation of +6

    var r = new byte[data.Length];
    switch (groupSize)
    {
        case 1:
            for (var i = 0; i < data.Length; i++)
            {
                var bits = data[i];
                // http://stackoverflow.com/a/812039
                r[i] = (byte) ((bits << amount) | (bits >> (8 - amount)));
            }
            break;
        default:
            throw new NotImplementedException($"Unable to rotate a group of {groupSize} bytes yet");
    }
    return r;
}
 */
    }

    public function processZlib($bytes) /*byte */ {
        throw new \RuntimeException("Not implemented yet");
    }

    /**************************************************************************
     * Internal
     **************************************************************************/

    protected function toSigned(int $x, int $mask): int {
        return ($x & ~$mask) - ($x & $mask);
    }

    protected function bytesToEncoding(string $bytes, string $outputEncoding): string {
        return iconv($this->defaultEncoding(), $outputEncoding, $bytes);
    }

    protected function defaultEncoding(): string {
        //  Encoding should be a compatible superset of ASCII.
        return 'utf-8';
    }
}
