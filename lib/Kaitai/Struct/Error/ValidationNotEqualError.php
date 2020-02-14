<?php
namespace Kaitai\Struct\Error;

use Kaitai\Struct\Stream;

class ValidationNotEqualError extends ValidationFailedError {
    protected $expected;
    protected $actual;

    public function __construct($expected, $actual, Stream $io, string $srcPath) {
        parent::__construct('not equal, expected ' . print_r($expected, true) . ', but got ' . print_r($actual, true), $io, $srcPath);
        $this->expected = $expected;
        $this->actual = $actual;
    }
}
