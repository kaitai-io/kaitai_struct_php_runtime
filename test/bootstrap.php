<?php
require __DIR__ . '/../vendor/autoload.php';

function d(...$args) {
    var_dump(...$args);
    die();
}
