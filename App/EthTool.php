<?php
namespace App;

/**
 * Etherium tools functions class
 *
 * @package App
 */
class EthTool
{
    /**
     * Convert hexadecimal "0x..." string representation of Etherium value to floating point
     *
     * @param string $hexVal
     * @return float
     */
    public static function hex2eth($hexVal)
    {
        $ret = 0.0;
        $hexBlockSize = 4;
        $hexBlockMultiplier = 65536.0;

        $hexVal = substr($hexVal, 2);
        $hexLen = strlen($hexVal);

        $firstPartialSize = $hexLen % $hexBlockSize;
        if ($firstPartialSize) {
            $ret += hexdec(substr($hexVal, 0, $firstPartialSize));
        }

        for ($pos = $firstPartialSize; $pos < $hexLen; $pos += $hexBlockSize) {
            $ret = $ret * $hexBlockMultiplier + hexdec(substr($hexVal, $pos, $hexBlockSize));
        }

        $ret *= 1e-18;

        return $ret;
    }

    /**
     * Get current datetime in Eth default format (just datetime with UTC)
     *
     * @param int|null $ts
     * @return string
     */
    public static function getDatetime($ts = null)
    {
        return isset($ts) ? gmdate('Y-m-d H:i:s', $ts) : gmdate('Y-m-d H:i:s');
    }

}
