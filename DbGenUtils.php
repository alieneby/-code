<?php

class DbGenUtils {

    public static $_strAlphaNumeric = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    public static $_strNumber = '-.1234567890';
    public static $_strEmail = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-._+@';

    static function filterQuotesAndBrackets( $str ) {
        return str_replace( $arrDummy1 = array( '"', "'", '<', '>' ), '', $str );
    }

    static function only( $strAllowedChars, $str ) {
        $strReturn = "";
        $l = strlen( $str );
        for ( $i = 0; $i < $l; $i++ ) {
            $c = substr( $str, $i, 1 );
            if ( strpos( $strAllowedChars, $c ) !== false ) $strReturn .= $c;
        }
        return $strReturn;
    }

    static function isOnly( $strAllowedChars, $str ) {
        $strReturn = "";
        $l = strlen( $str );
        for ( $i = 0; $i < $l; $i++ ) {
            $c = substr( $str, $i, 1 );
            if ( strpos( $strAllowedChars, $c ) === false ) return false;
        }
        return true;
    }

    /**
     * Example: onlyInt('223,023,,123,00,0,Haus,2K,-2,1.3,-', ',') =>  223,23,123,0,0,-2
     * return string
     */
    static function onlyInt( $str, $strSeparator = '' ) {
        if ( ! $strSeparator || strpos( $str, $strSeparator ) === false ) {
            return ( ( (string) ( (int) $str ) ) == ( (string) $str ) ) ? (int) $str : '';
        }
        $strReturn = '';
        foreach ( $arr = explode( $strSeparator, $str ) as $strSub ) {
            $strSub = self::onlyInt( $strSub );
            if ( $strSub !== '' ) $strReturn .= ( $strReturn ? $strSeparator : '' ) . $strSub;
        }
        return $strReturn;
    }

    static function isInt( $str, $strSeparator = '' ) {
        return ( $str && self::onlyInt( $str, $strSeparator ) );
    }

    static function isEmail( &$str ) {
        $nPos1 = strpos( $str, '<' );
        if ( $nPos1 !== false ) {
            $nPos2 = strpos( $str, '>', $nPos1 );
            if ( $nPos2 ) {
                $str = substr( $str, $nPos1 + 1, $nPos2 - $nPos1 - 1 );
            }
        }
        if ( filter_var( str_replace( '+', '', $str ), FILTER_VALIDATE_EMAIL ) )
            return true;
        else
            return false;
    }

    static function onlyEmail( $str ) {
        $nP1 = strpos( $str, '<' );
        $nP2 = strpos( $str, '>' );
        if ( $nP1 && $nP1 < $nP2 ) $str = substr( $str, $nP1, $nP2 - $nP1 );
        return self::only( self::$_strEmail, $str );
    }

    static function onlyNumber( $str, $strAdditionals = "" ) {
        return self::only( self::$_strNumber . $strAdditionals, $str );
    }

    static function onlyAlphaNumeric( $str, $strAdditionals = "" ) {
        return self::only( self::$_strAlphaNumeric . $strAdditionals, $str );
    }

    static function isAlphaNumeric( $str, $strAdditionals = "" ) {
        return self::isOnly( self::$_strAlphaNumeric . $strAdditionals, $str );
    }

    static function isNumber( $str, $strAdditionals = "" ) {
        return self::isOnly( self::$_strNumber . $strAdditionals, $str );
    }

    static function isFloat( $str ) {
        return self::isOnly( self::$_strNumber . ".", $str );
    }


}

?>
