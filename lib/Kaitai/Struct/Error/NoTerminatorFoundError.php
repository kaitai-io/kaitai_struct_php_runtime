<?php
namespace Kaitai\Struct\Error;

class NoTerminatorFoundError extends \RuntimeException {
    protected $terminator;

    public function __construct(string $terminator) {
        parent::__construct("End of stream reached, but no terminator '$terminator' found");
        $this->terminator = $terminator;
    }
}
