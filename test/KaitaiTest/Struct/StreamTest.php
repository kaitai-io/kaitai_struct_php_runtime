<?php
namespace KaitaiTest\Struct;

use Kaitai\Struct\Stream;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase {
    const SINGLE_EPS = 0.0000001;
    const DOUBLE_EPS = 0.0000001;

    public function testStreamPositioning_FileHandle() {
        $this->checkStreamPositioning(
            fopen(__DIR__ .'/_files/fixed_struct.bin', 'rb'),
            148
        );
    }

    public function testStreamPositioning_MemoryHandle() {
        $handle = $this->memoryHandle();
        $s = 'abc';
        $n = 200;
        fwrite($handle, str_repeat($s, $n));
        $fileSize = strlen($s) * $n;
        $this->checkStreamPositioning($handle, $fileSize);
    }

    public function testStreamPositioning_String() {
        $s = str_repeat('abc', 200);
        $this->checkStreamPositioning($s, strlen($s));
    }

    public function testS1() {
        $bytes = "\x80\xff\x00\x7f\xfa\x0f\xad\xe5\x22\x11";
        $stream = new Stream($bytes);

        $this->assertEquals(-128, $stream->readS1());

        $stream->seek(1);
        $this->assertEquals(-1, $stream->readS1());

        $stream->seek(2);
        $this->assertEquals(0, $stream->readS1());

        $stream->seek(3);
        $this->assertEquals(127, $stream->readS1());

        $stream->seek(4);
        $this->assertEquals(-6, $stream->readS1());

        $stream->seek(5);
        $this->assertEquals(15, $stream->readS1());

        $stream->seek(6);
        $this->assertEquals(-83, $stream->readS1());

        $stream->seek(7);
        $this->assertEquals(-27, $stream->readS1());

        $stream->seek(8);
        $this->assertEquals(34, $stream->readS1());

        $stream->seek(9);
        $this->assertEquals(17, $stream->readS1());
    }

    public function testS2be() {
        $bytes = "\x80\x00"
            . "\xff\xff"
            . "\x00\x00"
            . "\x7f\xff";
        $stream = new Stream($bytes);

        $this->assertEquals(-32768, $stream->readS2be());

        $stream->seek(2);
        $this->assertEquals(-1, $stream->readS2be());

        $stream->seek(4);
        $this->assertEquals(0, $stream->readS2be());

        $stream->seek(6);
        $this->assertEquals(32767, $stream->readS2be());
    }

    public function testS4be() {
        $bytes = "\x80\x00\x00\x00"
            . "\xff\xff\xff\xff"
            . "\x00\x00\x00\x00"
            . "\x7f\xff\xff\xff";

        $stream = new Stream($bytes);

        $this->assertEquals(-2147483648, $stream->readS4be());

        $stream->seek(4);
        $this->assertEquals(-1, $stream->readS4be());

        $stream->seek(8);
        $this->assertEquals(0, $stream->readS4be());

        $stream->seek(12);
        $this->assertEquals(2147483647, $stream->readS4be());
    }

    public function testS8be() {
        $bytes = "\x80\x00\x00\x00\x00\x00\x00\x00"
            . "\xff\xff\xff\xff\xff\xff\xff\xff"
            . "\x00\x00\x00\x00\x00\x00\x00\x00"
            . "\x7f\xff\xff\xff\xff\xff\xff\xff";
        $stream = new Stream($bytes);

        $this->assertEquals(-9223372036854775808, $stream->readS8be());

        $stream->seek(8);
        $this->assertEquals(-1, $stream->readS8be());
        
        $stream->seek(16);
        $this->assertEquals(0, $stream->readS8be());
        
        $stream->seek(24);
        $this->assertEquals(9223372036854775807, $stream->readS8be());
    }

    public function testS2le() {
        $bytes = "\x00\x80"
            . "\xff\xff"
            . "\x00\x00"
            . "\xff\x7f";
        $stream = new Stream($bytes);

        $this->assertEquals(-32768, $stream->readS2le());

        $stream->seek(2);
        $this->assertEquals(-1, $stream->readS2le());

        $stream->seek(4);
        $this->assertEquals(0, $stream->readS2le());

        $stream->seek(6);
        $this->assertEquals(32767, $stream->readS2le());
    }

    public function testS4le() {
        $bytes = "\x00\x00\x00\x80"
            . "\xff\xff\xff\xff"
            . "\x00\x00\x00\x00"
            . "\xff\xff\xff\x7f";
        $stream = new Stream($bytes);

        $this->assertEquals(-2147483648, $stream->readS4le());

        $stream->seek(4);
        $this->assertEquals(-1, $stream->readS4le());

        $stream->seek(8);
        $this->assertEquals(0, $stream->readS4le());

        $stream->seek(12);
        $this->assertEquals(2147483647, $stream->readS4le());
    }

    public function testS8le() {
        $bytes = "\x00\x00\x00\x00\x00\x00\x00\x80"
            . "\xff\xff\xff\xff\xff\xff\xff\xff"
            . "\x00\x00\x00\x00\x00\x00\x00\x00"
            . "\xff\xff\xff\xff\xff\xff\xff\x7f";
        $stream = new Stream($bytes);

        $this->assertEquals(-9223372036854775808, $stream->readS8le());

        $stream->seek(8);
        $this->assertEquals(-1, $stream->readS8le());

        $stream->seek(16);
        $this->assertEquals(0, $stream->readS8le());

        $stream->seek(24);
        $this->assertEquals(9223372036854775807, $stream->readS8le());
    }

    public function testU1() {
        $bytes = "\x80\xff\x00\x7f\xfa\x0f\xad\xe5\x22\x11";
        $stream = new Stream($bytes);
        $this->assertEquals(128, $stream->readU1());

        $stream->seek(1);
        $this->assertEquals(255, $stream->readU1());

        $stream->seek(2);
        $this->assertEquals(0, $stream->readU1());

        $stream->seek(3);
        $this->assertEquals(127, $stream->readU1());

        $stream->seek(4);
        $this->assertEquals(250, $stream->readU1());

        $stream->seek(5);
        $this->assertEquals(15, $stream->readU1());

        $stream->seek(6);
        $this->assertEquals(173, $stream->readU1());

        $stream->seek(7);
        $this->assertEquals(229, $stream->readU1());

        $stream->seek(8);
        $this->assertEquals(34, $stream->readU1());

        $stream->seek(9);
        $this->assertEquals(17, $stream->readU1());
    }

    public function dataForU2_LeBe() {
        return [
            [
                "\x00\x00"
                . "\x31\x12"
                . "\xff\xff",
                'readU2le'
            ],
            [
                "\x00\x00"
                . "\x12\x31"
                . "\xff\xff",
                'readU2be'
            ],
        ];
    }

    /**
     * @dataProvider dataForU2_LeBe
     */
    public function testU2_LeBe(string $bytes, string $fn) {
        $stream = new Stream($bytes);
        $read = [$stream, $fn];
        $this->assertEquals(0, call_user_func($read));

        $stream->seek(2);
        $this->assertEquals(4657, call_user_func($read));

        $stream->seek(4);
        $this->assertEquals(65535, call_user_func($read));
    }

    public function dataForU4_LeBe() {
        return [
            [
                "\x00\x00\x00\x00"
                . "\x00\x12\x00\x0f"
                . "\xff\xff\xff\xff",
                'readU4le'
            ],
            [
                "\x00\x00\x00\x00"
                . "\x0f\x00\x12\x00"
                . "\xff\xff\xff\xff",
                'readU4be'
            ],
        ];
    }

    /**
     * @dataProvider dataForU4_LeBe
     */
    public function testU4_LeBe(string $bytes, string $fn) {
        $stream = new Stream($bytes);
        $read = [$stream, $fn];
        $this->assertEquals(0, call_user_func($read));

        $stream->seek(4);
        $this->assertEquals(251662848, call_user_func($read));

        $stream->seek(8);
        $this->assertEquals(4294967295, call_user_func($read));
    }

    public function dataForU8_LeBe() {
        return [
            [
                "\x00\x00\x00\x00\x00\x00\x00\x00"
                . "\x00\x00\x00\x12\x00\xf0\x00\x00"
                . "\xff\xff\xff\xff\xff\xff\xff\xff", // 2^64 - 1
                'readU8le'
            ],
            [
                "\x00\x00\x00\x00\x00\x00\x00\x00"
                . "\x00\x00\xf0\x00\x12\x00\x00\x00"
                . "\xff\xff\xff\xff\xff\xff\xff\xff", // 2^64 - 1
                'readU8be'
            ],
        ];
    }

    /**
     * @dataProvider dataForU8_LeBe
     */
    public function testU8_LeBe(string $bytes, string $fn) {
        $stream = new Stream($bytes);
        $read = [$stream, $fn];

        $this->assertEquals(0, call_user_func($read));

        $stream->seek(8);
        $this->assertEquals(263883092656128, call_user_func($read));

        $stream->seek(16);
        // PHP does not support the unsigned integers, so to represent the values > 2^63-1 we
        // need to use signed integers, which have the same internal representation as unsigned.
        // In this case it is 2^64 - 1
        $this->assertEquals(-1, call_user_func($read));
    }


    public function testReadF4be() {
        $bytes = "\xc0\x49\x0f\xdb";
        // 1100 0000 0100 1001 0000 1111 1101 1011
        $stream = new Stream($bytes);
        $this->assertEquals(-3.141592653589793, $stream->readF4be(), '', self::SINGLE_EPS);
        // @TODO: test NAN, -INF, INF, -0.0, 0.0
        /*
        NaN 0x7FC00000.
        INF 0x7F800000.
        -INF 0xFF800000.
        */
    }

    public function testReadF8be() {
        $this->markTestIncomplete();
        // @TODO: test NAN, -INF, INF, -0.0, 0.0
    }

    public function testReadF4le() {
        $bytes = "\xdb\x0f\x49\xc0";
        // 1101 1011 0000 1111 0100 1001 1100 0000
        $stream = new Stream($bytes);
        $this->assertEquals(-3.141592653589793, $stream->readF4le(), '', self::SINGLE_EPS);
        // @TODO: test NAN, -INF, INF, -0.0, 0.0
    }

    public function testReadF8le() {
        $this->markTestIncomplete();
        // @TODO: test NAN, -INF, INF, -0.0, 0.0
    }

    public function testReadBytes_Сonsistently() {
        $bytes = "\x03\xef\xa4\xb9";
        $stream = new Stream($bytes);
        $this->assertEquals("\x03\xef", $stream->readBytes(2));
        $this->assertEquals("\xa4", $stream->readBytes(1));
        $this->assertEquals("\xb9", $stream->readBytes(1));
    }

    public function testReadBytes_Seek() {
        $bytes = "\x03\xef\xa4\xb9";
        $stream = new Stream($bytes);

        $stream->seek(1);
        $this->assertEquals("\xef\xa4", $stream->readBytes(2));

        $stream->seek(0);
        $this->assertEquals("\x03\xef", $stream->readBytes(2));

        $stream->seek(3);
        $this->assertEquals("\xb9", $stream->readBytes(1));
    }

    public function testReadBytesFull() {
        $bytes = "\x03\xef\xa4\xb9";
        $stream = new Stream($bytes);

        $this->assertEquals($bytes, $stream->readBytesFull());
        $this->assertEquals('', $stream->readBytesFull());

        $stream->seek(0);
        $this->assertEquals($bytes, $stream->readBytesFull());
        $this->assertEquals('', $stream->readBytesFull());

        $stream->seek(2);
        $this->assertEquals("\xa4\xb9", $stream->readBytesFull());
        $this->assertEquals('', $stream->readBytesFull());
    }

    public function testEnsureFixedContents() {
        $bytes = "\x3c\x3f\x70\x68\x70"; // "<?php"
        $stream = new Stream($bytes);
        $this->assertEquals(
            $bytes,
            $stream->ensureFixedContents($bytes)
        );
        try {
            $stream->ensureFixedContents($bytes);
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertEquals('Requested ' . strlen($bytes) . ' bytes, but got only 0 bytes', $e->getMessage());
        }
    }

    public function testProcessXorOne() {
        $stream = $this->stream();

        $bytes = "\xab\x48\xf1\x04";

        $xored = $stream->processXorOne($bytes, "\x3f"); // 63 int
        $this->assertEquals("\x94\x77\xce\x3b", $xored);

        $xored = $stream->processXorOne($bytes, 63);
        $this->assertEquals("\x94\x77\xce\x3b", $xored);
    }

    public function testProcessXorMany() {
        $stream = $this->stream();
        $bytes = "\xab\x48\xf1\x04";
        $key = "\x3f\x2d\xa5";
        $xored = $stream->processXorMany($bytes, $key);
        $this->assertEquals("\x94\x65\x54\x3b", $xored);
    }

    public function testProcessRotateLeft() {
        $stream = $this->stream();
        $bytes = "\x17\x22\xc9\x04\x06\x13";
        $rotated = $stream->processRotateLeft($bytes, 3, 1);
        $this->assertEquals("\xb8\x11\x4e\x20\x30\x98", $rotated);
    }

    public function testProcessZlib() {
        $stream = $this->stream();
        $string = "Compress me";
        $compressed = gzcompress($string);
        $uncompressed = $stream->processZlib($compressed);
        $this->assertEquals($string, $uncompressed);
    }

    private function memoryHandle() {
        return fopen("php://memory", "r+b");
    }

    private function checkStreamPositioning($stream, $fileSize) {
        $stream = new Stream($stream);

        $this->assertEquals($fileSize, $stream->size());
        $this->assertEquals(0, $stream->pos());
        $this->assertFalse($stream->isEof());

        $pos = 123;
        $this->assertNull($stream->seek($pos));
        $this->assertEquals($pos, $stream->pos());
        $this->assertFalse($stream->isEof());

        $this->assertSeekCallFailsForPos($stream, $fileSize + 3);

        $pos = $fileSize;
        $this->assertNull($stream->seek($pos));
        $this->assertTrue($stream->isEof());
        $this->assertEquals($pos, $stream->pos());
    }

    private function assertSeekCallFailsForPos(Stream $stream, $pos) {
        try {
            $this->assertNull($stream->seek($pos));
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertRegExp("~The position \\($pos\\) must be less than the size \\(\\d+\\) of the stream~s", $e->getMessage());
        }
    }

    private function stream() {
        $handle = $this->memoryHandle();
        return new Stream($handle);
    }
}
