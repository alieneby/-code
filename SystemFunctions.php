<?php

class SystemFunctions {
    /**
     * What method has executed your method?
     * @return array of 3 strings: method, line number, file name
     */
    static function getCaller( $arrBacktrace ) {
        //print_r( $arrBacktrace );
        if ( count( $arrBacktrace ) != 2 ) return array( __METHOD__, __LINE__, __FILE__ );

        $strLine = empty( $arrBacktrace[0]['line'] ) ? '' : $arrBacktrace[0]['line'];
        $strFile = empty( $arrBacktrace[1]['file'] ) ? '' : basename( $arrBacktrace[1]['file'] );
        $strFunction = empty( $arrBacktrace[1]['function'] ) ? '' : basename( $arrBacktrace[1]['function'] );
        $strMethod = $strFunction;
        if ( ! empty( $arrBacktrace[1]['class'] ) ) {
            $strMethod = $arrBacktrace[1]['class']
                . ( empty( $arrBacktrace[1]['type'] ) ? '.' : $arrBacktrace[1]['type'] )
                . $strFunction;        
        } 
        return array( $strMethod, $strLine, $strFile );
    }
}
