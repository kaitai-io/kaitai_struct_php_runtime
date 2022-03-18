<?php
namespace KaitaiTest\Struct;

use Kaitai\Struct\Error\EndOfStreamError;
use Kaitai\Struct\Error\KaitaiError;
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

        $this->assertSame(-128, $stream->readS1());

        $stream->seek(1);
        $this->assertSame(-1, $stream->readS1());

        $stream->seek(2);
        $this->assertSame(0, $stream->readS1());

        $stream->seek(3);
        $this->assertSame(127, $stream->readS1());

        $stream->seek(4);
        $this->assertSame(-6, $stream->readS1());

        $stream->seek(5);
        $this->assertSame(15, $stream->readS1());

        $stream->seek(6);
        $this->assertSame(-83, $stream->readS1());

        $stream->seek(7);
        $this->assertSame(-27, $stream->readS1());

        $stream->seek(8);
        $this->assertSame(34, $stream->readS1());

        $stream->seek(9);
        $this->assertSame(17, $stream->readS1());
    }

    public function testS2be() {
        $bytes = "\x80\x00"
            . "\xff\xff"
            . "\x00\x00"
            . "\x7f\xff";
        $stream = new Stream($bytes);

        $this->assertSame(-32768, $stream->readS2be());

        $stream->seek(2);
        $this->assertSame(-1, $stream->readS2be());

        $stream->seek(4);
        $this->assertSame(0, $stream->readS2be());

        $stream->seek(6);
        $this->assertSame(32767, $stream->readS2be());
    }

    public function testS4be() {
        $bytes = "\x80\x00\x00\x00"
            . "\xff\xff\xff\xff"
            . "\x00\x00\x00\x00"
            . "\x7f\xff\xff\xff";

        $stream = new Stream($bytes);

        $this->assertSame(-2147483648, $stream->readS4be());

        $stream->seek(4);
        $this->assertSame(-1, $stream->readS4be());

        $stream->seek(8);
        $this->assertSame(0, $stream->readS4be());

        $stream->seek(12);
        $this->assertSame(2147483647, $stream->readS4be());
    }

    public function testS8be() {
        $bytes = "\x80\x00\x00\x00\x00\x00\x00\x00"
            . "\xff\xff\xff\xff\xff\xff\xff\xff"
            . "\x00\x00\x00\x00\x00\x00\x00\x00"
            . "\x7f\xff\xff\xff\xff\xff\xff\xff";
        $stream = new Stream($bytes);

        $this->assertSame(-9223372036854775807 - 1, $stream->readS8be());

        $stream->seek(8);
        $this->assertSame(-1, $stream->readS8be());
        
        $stream->seek(16);
        $this->assertSame(0, $stream->readS8be());
        
        $stream->seek(24);
        $this->assertSame(9223372036854775807, $stream->readS8be());
    }

    public function testS2le() {
        $bytes = "\x00\x80"
            . "\xff\xff"
            . "\x00\x00"
            . "\xff\x7f";
        $stream = new Stream($bytes);

        $this->assertSame(-32768, $stream->readS2le());

        $stream->seek(2);
        $this->assertSame(-1, $stream->readS2le());

        $stream->seek(4);
        $this->assertSame(0, $stream->readS2le());

        $stream->seek(6);
        $this->assertSame(32767, $stream->readS2le());
    }

    public function testS4le() {
        $bytes = "\x00\x00\x00\x80"
            . "\xff\xff\xff\xff"
            . "\x00\x00\x00\x00"
            . "\xff\xff\xff\x7f";
        $stream = new Stream($bytes);

        $this->assertSame(-2147483648, $stream->readS4le());

        $stream->seek(4);
        $this->assertSame(-1, $stream->readS4le());

        $stream->seek(8);
        $this->assertSame(0, $stream->readS4le());

        $stream->seek(12);
        $this->assertSame(2147483647, $stream->readS4le());
    }

    public function testS8le() {
        $bytes = "\x00\x00\x00\x00\x00\x00\x00\x80"
            . "\xff\xff\xff\xff\xff\xff\xff\xff"
            . "\x00\x00\x00\x00\x00\x00\x00\x00"
            . "\xff\xff\xff\xff\xff\xff\xff\x7f";
        $stream = new Stream($bytes);

        $this->assertSame(-9223372036854775807 - 1, $stream->readS8le());

        $stream->seek(8);
        $this->assertSame(-1, $stream->readS8le());

        $stream->seek(16);
        $this->assertSame(0, $stream->readS8le());

        $stream->seek(24);
        $this->assertSame(9223372036854775807, $stream->readS8le());
    }

    public function testU1() {
        $bytes = "\x80\xff\x00\x7f\xfa\x0f\xad\xe5\x22\x11";
        $stream = new Stream($bytes);
        $this->assertSame(128, $stream->readU1());

        $stream->seek(1);
        $this->assertSame(255, $stream->readU1());

        $stream->seek(2);
        $this->assertSame(0, $stream->readU1());

        $stream->seek(3);
        $this->assertSame(127, $stream->readU1());

        $stream->seek(4);
        $this->assertSame(250, $stream->readU1());

        $stream->seek(5);
        $this->assertSame(15, $stream->readU1());

        $stream->seek(6);
        $this->assertSame(173, $stream->readU1());

        $stream->seek(7);
        $this->assertSame(229, $stream->readU1());

        $stream->seek(8);
        $this->assertSame(34, $stream->readU1());

        $stream->seek(9);
        $this->assertSame(17, $stream->readU1());
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
        $this->assertSame(0, $read());

        $stream->seek(2);
        $this->assertSame(4657, $read());

        $stream->seek(4);
        $this->assertSame(65535, $read());
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
        $this->assertSame(0, $read());

        $stream->seek(4);
        $this->assertSame(251662848, $read());

        $stream->seek(8);
        $this->assertSame(4294967295, $read());
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

        $this->assertSame(0, $read());

        $stream->seek(8);
        $this->assertSame(263883092656128, $read());

        $stream->seek(16);
        // PHP does not support the unsigned integers, so to represent the values > 2^63-1 we
        // need to use signed integers, which have the same internal representation as unsigned.
        // In this case it is 2^64 - 1
        $this->assertSame(-1, $read());
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

    public function testReadBytes_Consistently() {
        $bytes = "\x03\xef\xa4\xb9";
        $stream = new Stream($bytes);
        $this->assertSame("\x03\xef", $stream->readBytes(2));
        $this->assertSame("\xa4", $stream->readBytes(1));
        $this->assertSame("\xb9", $stream->readBytes(1));
    }

    public function testReadBytes_Seek() {
        $bytes = "\x03\xef\xa4\xb9";
        $stream = new Stream($bytes);

        $stream->seek(1);
        $this->assertSame("\xef\xa4", $stream->readBytes(2));

        $stream->seek(0);
        $this->assertSame("\x03\xef", $stream->readBytes(2));

        $stream->seek(3);
        $this->assertSame("\xb9", $stream->readBytes(1));
    }

    public function testReadBytesFull() {
        $bytes = "\x03\xef\xa4\xb9";
        $stream = new Stream($bytes);

        $this->assertSame($bytes, $stream->readBytesFull());
        $this->assertSame('', $stream->readBytesFull());

        $stream->seek(0);
        $this->assertSame($bytes, $stream->readBytesFull());
        $this->assertSame('', $stream->readBytesFull());

        $stream->seek(2);
        $this->assertSame("\xa4\xb9", $stream->readBytesFull());
        $this->assertSame('', $stream->readBytesFull());
    }

    public function testEnsureFixedContents() {
        $bytes = "\x3c\x3f\x70\x68\x70"; // "<?php"
        $stream = new Stream($bytes);
        $this->assertSame(
            $bytes,
            $stream->ensureFixedContents($bytes)
        );
        try {
            $stream->ensureFixedContents($bytes);
            $this->fail();
        } catch (EndOfStreamError $e) {
            $this->assertSame('Requested ' . strlen($bytes) . ' bytes, but only 0 bytes available', $e->getMessage());
        }
    }

    public function testProcessXorOne() {
        $stream = $this->stream();

        $bytes = "\xab\x48\xf1\x04";

        $xored = $stream::processXorOne($bytes, "\x3f"); // 63 int
        $this->assertSame("\x94\x77\xce\x3b", $xored);

        $xored = $stream::processXorOne($bytes, 63);
        $this->assertSame("\x94\x77\xce\x3b", $xored);
    }

    public function testProcessXorMany() {
        $stream = $this->stream();
        $bytes = "\xab\x48\xf1\x04";
        $key = "\x3f\x2d\xa5";
        $xored = $stream::processXorMany($bytes, $key);
        $this->assertSame("\x94\x65\x54\x3b", $xored);
    }

    public function testProcessRotateLeft() {
        $stream = $this->stream();
        $bytes = "\x17\x22\xc9\x04\x06\x13";
        $rotated = $stream::processRotateLeft($bytes, 3, 1);
        $this->assertSame("\xb8\x11\x4e\x20\x30\x98", $rotated);
    }

    public function testProcessZlib() {
        $stream = $this->stream();
        $string = "Compress me";
        $compressed = gzcompress($string);
        $uncompressed = $stream::processZlib($compressed);
        $this->assertSame($string, $uncompressed);
    }

    private function memoryHandle() {
        return fopen("php://memory", "r+b");
    }

    private function checkStreamPositioning($stream, $fileSize) {
        $stream = new Stream($stream);

        $this->assertSame($fileSize, $stream->size());
        $this->assertSame(0, $stream->pos());
        $this->assertFalse($stream->isEof());

        $pos = 123;
        $this->assertNull($stream->seek($pos));
        $this->assertSame($pos, $stream->pos());
        $this->assertFalse($stream->isEof());

        $this->assertSeekCallFailsForPos($stream, $fileSize + 3);

        $pos = $fileSize;
        $this->assertNull($stream->seek($pos));
        $this->assertTrue($stream->isEof());
        $this->assertSame($pos, $stream->pos());
    }

    private function assertSeekCallFailsForPos(Stream $stream, $pos) {
        try {
            $this->assertNull($stream->seek($pos));
            $this->fail();
        } catch (KaitaiError $e) {
            $this->assertRegExp("~The position \\($pos\\) must be less than the size \\(\\d+\\) of the stream~s", $e->getMessage());
        }
    }

    private function stream() {
        $handle = $this->memoryHandle();
        return new Stream($handle);
    }
}
