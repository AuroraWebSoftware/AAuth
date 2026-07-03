<?php

namespace AuroraWebSoftware\AAuth;

/**
 * @property int $id
 */
interface ZzUntypedGetContract
{
    public function foo(): int;

    /**
     * @param  string  $name
     * @return mixed
     */
    public function __get($name);
}

class ZzTmpProbe
{
    public function g(ZzUntypedGetContract $z): int
    {
        return $z->id;
    }
}
