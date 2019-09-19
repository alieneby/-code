<?php
if ( ! isset( Config::$_strAlien ) ) {
    echo "Please include Config.php first!";
    exit( 500 );
}

require_once( Config::$_strAlien . '/SystemFunctions.php' );
require_once( Config::$_strAlien . '/Log.php' );

if ( ! empty( Config::$_DB_DB ) ) {
    require_once( Config::$_strAlien . '/DbFunctions.php' );
}

class 👽 {

    static function init() {
        if ( ! empty( Config::$_DB_DB ) ) {
            if ( ! isset( Config::$_DB_CONNECT_AUTOMATICLY ) || Config::$_DB_CONNECT_AUTOMATICLY ) {
                dbConnector();
            }
        }
    }


    static function 🖖() { return call_user_func_array( "👽::exitNow", func_get_args() ); }

    /**
     * @param string $strLastLogMessage
     * @param int $nHttpErrCode
     * @param string $strOutput
     */
    static function exitNow( $strLastLogMessage = '', $nHttpErrCode = 0, $strOutput = "" ) {
        list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
        $strUri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'shell';

        Log::$_strLastLogMessage = $strLastLogMessage;

        if ( $nHttpErrCode ) {
            if ( $nHttpErrCode >= 200 && $nHttpErrCode < 300 ) {
                header( "HTTP/1.0 $nHttpErrCode" );
            } else {
                Log::error( $strLastLogMessage, $strMethod, $strLine, $strFile );
                header( "HTTP/1.0 $nHttpErrCode $strLastLogMessage" );
            }
        }

        if ( $strOutput ) echo $strOutput;

        if ( ! empty( Config::$_strDebugLog ) ) {
            Log::toLogFile();
        }

        if ( ! empty( Config::$_DB_DEBUG_TABLE ) ) {
            Log::toDatabase();
        }

        exit();
    }
}

👽::init();
class_alias( '👽', 'Alien' );

