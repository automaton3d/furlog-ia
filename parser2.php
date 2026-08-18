<?php
// parser.php

function decode($str) {
    $r = decode16($str);
    $r = str_replace([
        "Ò¡", "Ò©", "Ò­", "Ò³", "Òº", "Ò¢", "Òª", "Ò´", "Ò£", "yy", "Ò§", "Ò?"
    ], [
        "á", "é", "í", "ó", "ú", "â", "ê", "ô", "ã", "õ", "ç", "Â"
    ], $r);
    return $r;
}

function decode16($str) {
    if (substr($str, 0, 1) === '"') {
        $str = substr($str, 1, -2);
    }

    $bytes = [];
    for ($i = 0; $i < strlen($str); $i++) {
        $bytes[] = ord($str[$i]) & 0xFF;
    }

    return mb_convert_encoding(pack('C*', ...$bytes), 'UTF-8', 'UTF-8');
}
