<?php
if ( ! isset( Config::$_strAlien ) ) {
    echo "Please include Config.php first!";
    exit( 500 );
}

require_once ( Config::$_strAlien . '/SystemFunctions.php' );
require_once ( Config::$_strAlien . '/Log.php' );

if ( ! empty( Config::$_DB_DB ) ) {
    require_once ( Config::$_strAlien . '/DbFunctions.php' );
}

class 👽 {
    static $_db_link;

    static function init() {       
        if ( ! empty( Config::$_DB_DB ) ) self::$_db_link = dbConnector();
    }


    static function 🖖() { return call_user_func_array("👽::exitNow", func_get_args()); }

    /**
     * @param string $strLastLogMessage
     * @param int $nHttpErrCode
     * @param string $strOutput
     */
    static function exitNow( $strLastLogMessage = '', $nHttpErrCode = 0, $strOutput = "" ) {
        list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller();
        $strUri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'shell';

        Log::$_strLastLogMessage = $strLastLogMessage;

        if ( $nHttpErrCode && ( $nHttpErrCode < 200  || $nHttpErrCode > 299 ) ) {
            Log::error( $strLastLogMessage, $strMethod, $strLine, $strFile );
            header( "HTTP/1.0 $nHttpErrCode $strLastLogMessage" );
            header( "Content-Type: text/plain" );
            echo $strOutput ? $strOutput : "$nHttpErrCode $strLastLogMessage";
        } 

        if ( ! empty( Config::$_DB_DEBUG_TABLE ) ) {
            Log::toDatabase();
        } 

        if ( ! empty( Config::$_strDebugLog ) ) {
            Log::toLogFile();
        } 

        if ( $strOutput ) echo $strOutput;

        exit();
    }

}

use function sleep as wait;

👽::init();
class_alias( '👽', 'Alien' );
//class_alias( '👽::Log', 'AlienLog' );


