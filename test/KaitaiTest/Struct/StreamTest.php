<?php
namespace KaitaiTest\Struct;

use Kaitai\Struct\Stream;

class StreamTest extends \PHPUnit_Framework_TestCase {
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

    public function testS1() {
        $handle = $this->memoryHandle();
        fwrite($handle, "\x80\xFF\x00\x7F\xFA\x0F\xAD\xE5\x22\x11");
        $stream = new Stream($handle);
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
        $handle = $this->memoryHandle();
        fwrite(
            $handle,
            "\x80\x00"
            . "\xff\xff"
            . "\x00\x00"
            . "\x7f\xff"
        );
        $stream = new Stream($handle);
        $this->assertEquals(-32768, $stream->readS2be());

        $stream->seek(2);
        $this->assertEquals(-1, $stream->readS2be());

        $stream->seek(4);
        $this->assertEquals(0, $stream->readS2be());

        $stream->seek(6);
        $this->assertEquals(32767, $stream->readS2be());
    }

    public function testS4be() {
        $handle = $this->memoryHandle();
        fwrite(
            $handle,
            "\x80\x00\x00\x00"
            . "\xff\xff\xff\xff"
            . "\x00\x00\x00\x00"
            . "\x7f\xff\xff\xff"
        );

        $stream = new Stream($handle);
        $this->assertEquals(-2147483648, $stream->readS4be());

        $stream->seek(4);
        $this->assertEquals(-1, $stream->readS4be());

        $stream->seek(8);
        $this->assertEquals(0, $stream->readS4be());

        $stream->seek(12);
        $this->assertEquals(2147483647, $stream->readS4be());
    }

    public function testS8be() {
        $this->markTestIncomplete();
        /*
        $handle = $this->memoryHandle();
        fwrite(
            $handle,
            "\x80\x00\x00\x00\x00\x00\x00\x00"
            . "\xff\xff\xff\xff\xff\xff\xff\xff"
        );

        $stream = new Stream($handle);
        $this->assertEquals(-9223372036854775808, $stream->readS8be());

        // @TODO: fix
        $stream->seek(8);
        $this->assertEquals(-1, $stream->readS8be());
        */
    }

    public function testS2le() {
        $handle = $this->memoryHandle();
        fwrite(
            $handle,
            "\x00\x80"
            . "\xff\xff"
            . "\x00\x00"
            . "\xff\x7f"
        );
        $stream = new Stream($handle);
        $this->assertEquals(-32768, $stream->readS2le());

        $stream->seek(2);
        $this->assertEquals(-1, $stream->readS2le());

        $stream->seek(4);
        $this->assertEquals(0, $stream->readS2le());

        $stream->seek(6);
        $this->assertEquals(32767, $stream->readS2le());
    }

    public function testS4le() {
        $handle = $this->memoryHandle();
        fwrite(
            $handle,
            "\x00\x00\x00\x80"
            . "\xff\xff\xff\xff"
            . "\x00\x00\x00\x00"
            . "\xff\xff\xff\x7f"
        );

        $stream = new Stream($handle);
        $this->assertEquals(-2147483648, $stream->readS4le());

        $stream->seek(4);
        $this->assertEquals(-1, $stream->readS4le());

        $stream->seek(8);
        $this->assertEquals(0, $stream->readS4le());

        $stream->seek(12);
        $this->assertEquals(2147483647, $stream->readS4le());
    }

    public function testS8le() {
        $this->markTestIncomplete();
    }

    public function testU1() {
        $handle = $this->memoryHandle();
        fwrite($handle, "\x80\xFF\x00\x7F\xFA\x0F\xAD\xE5\x22\x11");
        $stream = new Stream($handle);
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
        $handle = $this->memoryHandle();
        fwrite($handle, $bytes);

        $stream = new Stream($handle);
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
        $handle = $this->memoryHandle();
        fwrite($handle, $bytes);

        $stream = new Stream($handle);
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
        $handle = $this->memoryHandle();
        fwrite($handle, $bytes);

        $stream = new Stream($handle);
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
        $this->markTestIncomplete();
    }

    public function testReadF8be() {
        $this->markTestIncomplete();
    }

    public function testReadF4le() {
        $this->markTestIncomplete();
    }

    public function testReadF8le() {
        $this->markTestIncomplete();
    }

    public function testReadBytes() {
        $this->markTestIncomplete();
    }

    public function testReadStrEos() {
        $this->markTestIncomplete();
    }

    public function testReadStrByteLimit() {
        $this->markTestIncomplete();
    }

    public function testReadStrz() {
        $this->markTestIncomplete();
    }

    public function testProcessXor() {
        $this->markTestIncomplete();
    }

    public function testProcessRotateLeft() {
        $this->markTestIncomplete();
    }

    public function testProcessZlib() {
        $this->markTestIncomplete();
    }

    public function testReadBytesFull() {
        $this->markTestIncomplete();
    }

    private function memoryHandle() {
        return fopen("php://memory", "r+b");
    }

    private function checkStreamPositioning($stream, $fileSize) {
        $stream = new Stream($stream);

        $this->assertEquals($fileSize, $stream->size());
        $this->assertEquals(0, $stream->pos());
        $this->assertFalse($stream->eof());

        $pos = 123;
        $this->assertNull($stream->seek($pos));
        $this->assertEquals($pos, $stream->pos());
        $this->assertFalse($stream->eof());

        $this->assertSeekCallFailsForPos($stream, $fileSize);
        $this->assertSeekCallFailsForPos($stream, $fileSize + 3);

        $pos = $fileSize - 1;
        $this->assertNull($stream->seek($pos));
        $this->assertFalse($stream->eof());
        $this->assertEquals($pos, $stream->pos());
    }

    private function assertSeekCallFailsForPos(Stream $stream, $pos) {
        try {
            $this->assertNull($stream->seek($pos));
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertEquals("The position must be < size of the stream", $e->getMessage());
        }
    }
}
