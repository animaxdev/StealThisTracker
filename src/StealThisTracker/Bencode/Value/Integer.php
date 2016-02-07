<?php

/**
 * Decoded bencode integer, representing a number.
 *
 * @package StealThisTracker
 * @subpackage Bencode
 */
class StealThisTracker_Bencode_Value_Integer extends StealThisTracker_Bencode_Value_Abstract
{
    /**
     * Intializing the object with its parsed value.
     *
     * @throws StealThisTracker_Bencode_Error_InvalidType In the value is not an integer.
     * @param integer $value
     */
    public function __construct( $value )
    {
        if ( !( is_numeric( $value ) && is_int( ( $value + 0 ) ) ) )
        {
            throw new StealThisTracker_Bencode_Error_InvalidType( "Invalid integer value: $value" );
        }
        $this->value = intval( $value );
    }

    /**
     * Convert the object back to a bencoded string when used as string.
     */
    public function __toString()
    {
        return "i" . $this->value . "e";
    }

    /**
     * Represent the value of the object as PHP scalar.
     */
    public function represent()
    {
        return $this->value;
    }
}