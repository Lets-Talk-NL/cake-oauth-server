<?php

namespace OAuthServer\Lib\Utility;

/**
 * OAuthServer plugin array helper class
 */
class Map
{
    /**
     * Compares two arrays by sorting them and
     * using the comparison operator to execute
     * the comparison returning the result
     */
    public static function compareValues(array $map1, array $map2): bool
    {
        sort($map1);
        sort($map2);
        return $map1 === $map2;
    }
}
