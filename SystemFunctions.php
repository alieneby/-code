<?php

class SystemFunctions {
    /**
     * What method has executed your method?
     * @return array of 3 strings: method, line number, file name
     */
    static function getCaller() {
        $arrBacktrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
        $arrLast = array_pop( $arrBacktrace );
        $strLine = empty( $arrLast['line'] ) ? '' : $arrLast['line'];
        $strFile = empty( $arrLast['file'] ) ? '' : basename( $arrLast['file'] );
        $strFunction = empty( $arrLast['function'] ) ? '' : basename( $arrLast['function'] );
        $strMethod = $strFunction;
        if ( ! empty( $arrLast['class'] ) ) {
            $strMethod = $arrLast['class'] 
                . ( empty( $arrLast['type'] ) ? '.' : $arrLast['type'] ) 
                . $strFunction;        
        } 
        return array( $strMethod, $strLine, $strFile );
    }
}