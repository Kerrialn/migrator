<?php

namespace KerrialNewham\Migrator\Helper;

class Strings
{
    public static function toCamelCase(string $string): string
    {
        $i = ["-", "_"];
        $string = preg_replace('/([a-z])([A-Z])/', "\\1 \\2", $string);
        $string = preg_replace('@[^a-zA-Z0-9\-_ ]+@', '', (string) $string);
        $string = str_replace($i, ' ', $string);
        $string = str_replace(' ', '', ucwords(strtolower($string)));
        return strtolower(substr($string, 0, 1)) . substr($string, 1);
    }

}
