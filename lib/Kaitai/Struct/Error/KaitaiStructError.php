<?php
namespace Kaitai\Struct\Error;

class KaitaiStructError extends \RuntimeException {
    protected $srcPath;

    public function __construct(string $msg, string $srcPath) {
        parent::__construct($srcPath . ': ' . $msg);
        $this->srcPath = $srcPath;
    }
}
