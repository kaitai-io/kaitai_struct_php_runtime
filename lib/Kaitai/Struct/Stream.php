<?php
namespace Kaitai\Struct;

class Stream {
    protected $stream;

    const SIGN_MASK_16 = 0x8000;         // (1 << (16 - 1));
    const SIGN_MASK_32 = 0x80000000;     // (1 << (32 - 1));
    const SIGN_MASK_64 = 0x800000000000; // (1 << (64 - 1));

    /**
     * @param string|resource $stream
     */
    public function __construct($stream) {
        if (PHP_INT_SIZE < 8) {
            throw new \RuntimeException("At least 64-bit platform is required");
        }
        if (is_string($stream)) {
            $this->stream = fopen($stream, 'rb');
        } else {
            $this->stream = $stream;
        }
        fseek($this->stream, 0, SEEK_SET);
        //$size = strlen(stream_get_contents($stream));
        //$this->size = fstat($this->stream)['size'];
    }

    /**************************************************************************
     * 1. Stream positioning
     **************************************************************************/

    public function eof(): bool {
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
        return unpack("c", $this->readNBytes(1))[1];
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
        $bytes = $this->readNBytes(8);

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
        return unpack("C", $this->readNBytes(1))[1];
    }

    // ---
    // 2.2.1. Big-endian

    public function readU2be(): int {
        return unpack("n", $this->readNBytes(2))[1];
    }

    public function readU4be(): int {
        return unpack("N", $this->readNBytes(4))[1];
    }

    public function readU8be(): int {
        return unpack("J", $this->readNBytes(8))[1];
    }

    // ---
    // 2.2.2. Little-endian

    public function readU2le(): int {
        return unpack("v", $this->readNBytes(2))[1];
    }

    public function readU4le(): int {
        return unpack("V", $this->readNBytes(4))[1];
    }

    public function readU8le(): int {
        return unpack("P", $this->readNBytes(8))[1];
    }

    /**************************************************************************
     * 3. Floating point numbers
     **************************************************************************/

    // ---
    // 3.1. Big-endian

    public function readF4be(): float {
        $bytes = $this->readNBytes(4);

        //read_bytes(4).unpack('g')[0]

    }

    public function readF8be(): float {
        $bytes = $this->readNBytes(8);

        //read_bytes(8).unpack('G')[0]

    }

    // ---
    // 3.2. Little-endian

    public function readF4le(): float {
        $bytes = $this->readNBytes(4);

        //read_bytes(4).unpack('e')[0]

    }

    public function readF8le(): float {
        $bytes = $this->readNBytes(8);

        //read_bytes(8).unpack('E')[0]


    }

    /**************************************************************************
     * 4. Byte arrays
     **************************************************************************/

    public function readBytes(int $count)/*byte[] */ {

    }

    public function readBytesFull()/*byte */ {

    }

    // ensure_fixed_contents??

    /**************************************************************************
     * 5. Strings
     **************************************************************************/

    public function readStrEos(string $encoding)/*string */ {

    }

    public function readStrByteLimit(int $length, string $encoding)/*string */ {

    }

    public function readStrz(string $encoding, int $terminator, bool $includeTerminator, bool $consumeTerminator, bool $eosError) /*string */ {

    }

    /**************************************************************************
     * 6. Byte array processing
     **************************************************************************/

    public function processXor($bytes, int $key)/*byte */ {

    }

    //public function processXor(byte[] value, byte[] key)/*byte */ {}
    public function processRotateLeft($bytes, int $amount, int $groupSize)/*byte */ {

    }

    public function processZlib($bytes) /*byte */ {

    }

    protected function readNBytes(int $n): string {
        fseek($this->stream, $this->pos(), SEEK_SET);
        return fread($this->stream, $n);
    }

    protected function toSigned(int $x, int $mask): int {
        return ($x & ~$mask) - ($x & $mask);
    }
}
