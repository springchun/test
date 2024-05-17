<?php

namespace FpDbTest;

use mysqli;

class Database implements DatabaseInterface
{
    private mysqli    $mysqli;
    private \stdClass $skip;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->skip   = new \stdClass();
    }

    public function buildQuery(string $query, array $args = []): string
    {
        if (strpos($query, '?') !== false) {
            $offset = -1;
            $query  = preg_replace_callback('#(\?[\w\#]?)|(\{[^\}]+\})#', function ($argv) use ($args, &$offset) {
                $offset++;
                $type  = $argv[0];
                $param = $args[$offset] ?? null;
                if (str_starts_with($type, '?')) {
                    $type = substr($type, 1);
                    return $this->quote($param, $type);
                } else {
                    if ($param === $this->skip) {
                        return '';
                    } else {
                        return $this->buildQuery(
                            substr($type, 1, -1),
                            [
                                $param
                            ]
                        );
                    }
                }
            }, $query);
        }
        return $query;
    }

    public function skip()
    {
        return $this->skip;
    }

    protected function quote(mixed $str, string $type = 's')
    {
        $type = strtoupper($type);
        if ($type === 'A') {
            if (array_is_list($str)) {
                foreach ($str as $k => $v) {
                    $str[$k] = $this->quote($v, 'S');
                }
            } else {
                foreach ($str as $k => $v) {
                    $str[$k] = $this->quote($k, '#').' = '.$this->quote($v, 'S');
                }
            }
            return implode(', ', $str);
        }
        if (is_array($str)) {
            foreach ($str as $k => $v) {
                $str[$k] = $this->quote($v, $type);
            }
            return implode(', ', $str);
        } else {
            if ($str === null) {
                return 'NULL';
            }
            switch (strtoupper($type)) {
                case 'D':
                    return intval($str);
                case 'F':
                    return floatval($str);
                case '#':
                    return "`{$str}`";
                default:
                    return is_string(
                        $str
                    )? "'{$this->mysqli->real_escape_string($str)}'":
                        $str;
            }
        }
    }
}
