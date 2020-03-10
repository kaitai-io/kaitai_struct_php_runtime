<?php
namespace Kaitai\Struct\Error;

use Kaitai\Struct\Stream;

class ValidationNotAnyOfError extends ValidationFailedError {
    protected $actual;

    public function __construct($actual, Stream $io, string $srcPath) {
        parent::__construct('not any of the list, got ' . print_r($actual, true), $io, $srcPath);
        $this->actual = $actual;
    }
}
