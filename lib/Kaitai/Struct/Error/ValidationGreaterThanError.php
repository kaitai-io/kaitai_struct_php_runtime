<?php
namespace Kaitai\Struct\Error;

use Kaitai\Struct\Stream;

class ValidationGreaterThanError extends ValidationFailedError {
    protected $max;
    protected $actual;

    public function __construct($max, $actual, Stream $io, string $srcPath) {
        parent::__construct('not in range, max ' . print_r($max, true) . ', but got ' . print_r($actual, true), $io, $srcPath);
        $this->max = $max;
        $this->actual = $actual;
    }
}
