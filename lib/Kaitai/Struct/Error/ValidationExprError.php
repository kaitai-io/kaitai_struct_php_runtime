<?php
namespace Kaitai\Struct\Error;

use Kaitai\Struct\Stream;

class ValidationExprError extends ValidationFailedError {
    protected $actual;

    public function __construct($actual, Stream $io, string $srcPath) {
        parent::__construct('not matching the expression, got ' . print_r($actual, true), $io, $srcPath);
        $this->actual = $actual;
    }
}
