<?php
namespace Kaitai\Struct\Error;

class UndecidedEndiannessError extends \RuntimeException {
    public function __construct() {
        parent::__construct('Unable to decide on endianness');
    }
}
