<?php
namespace Kaitai\Struct\Error;

use \Kaitai\Struct\Stream;

/**
 * Common ancestor for all validation failures. Stores pointer to
 * KaitaiStream IO object which was involved in an error.
 */
class ValidationFailedError extends KaitaiStructError {
    protected $io;

    public function __construct(string $msg, Stream $io, string $srcPath) {
        parent::__construct('at pos ' . $io->pos() . ': validation failed: ' . $msg, $srcPath);
        $this->io = $io;
    }
}
