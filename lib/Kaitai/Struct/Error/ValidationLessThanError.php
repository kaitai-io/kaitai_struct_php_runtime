<?php
namespace Kaitai\Struct\Error;

use Kaitai\Struct\Stream;

class ValidationLessThanError extends ValidationFailedError {
    protected $min;
    protected $actual;

    public function __construct($min, $actual, Stream $io, string $srcPath) {
        parent::__construct('not in range, min ' . print_r($min, true) . ', but got ' . print_r($actual, true), $io, $srcPath);
        $this->min = $min;
        $this->actual = $actual;
    }
}
