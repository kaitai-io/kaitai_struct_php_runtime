<?php
namespace Kaitai\Struct\Error;

class EndOfStreamError extends KaitaiError {
    protected $bytesReq;
    protected $bytesAvail;

    public function __construct(int $bytesReq, int $bytesAvail) {
        parent::__construct('Requested ' . $bytesReq . ' bytes, but only ' . $bytesAvail . ' bytes available');
        $this->bytesReq = $bytesReq;
        $this->bytesAvail = $bytesAvail;
    }
}
