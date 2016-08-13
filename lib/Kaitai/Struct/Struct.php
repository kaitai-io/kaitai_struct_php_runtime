<?php
namespace Kaitai\Struct;

abstract class Struct {
    protected $_io;
    protected $_parent;
    protected $_root;

    public function __construct(Stream $io, Struct $parent = null, Struct $root = null) {
        $this->_io = $io;
        $this->_parent = $parent;
        $this->_root = $root;
    }

    public static function fromFile($filePathOrStream): Struct {
        return new static(new Stream($filePathOrStream));
    }

    public function __get($name) {
        if ($name[0] !== '_' && property_exists($this, $name)) {
            return $this->$name;
        }
        throw new \RuntimeException("Cannot access the property '" . get_class($this) . '::' . $name . "'");
    }

    public function _parent(): Struct {
        return $this->_parent;
    }

    public function _root(): Struct {
        return $this->_root;
    }
}
