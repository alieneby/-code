<?php

class Log {
    static $_strScript = 'PHP';
    static $_strStartDate;
    static $_strStartTimestamp;
    static $_strLastLogMessage = '';
    static $_nErrors = 0;
    static $_nSystemErrors = 0;
    static $_nPid;
    static $arrLN = array( 'i' => 'Info:  ', 'e' => 'Error: ', 'a' => 'Always:', 's' => 'SystemErr:' );
    public static $_arr = array();

    static function init() {
        self::$_strStartTimestamp = microtime( true );
        self::$_strStartDate = gmdate( 'Y-m-d H:i:s' );
        self::$_nPid = ( getmypid() % 1000 );
    }

    private static function add( $strLevel, $str, $strMethod, $strLine, $strFile ) {
        $strDuration = sprintf( "%.4f", ( microtime( true ) - Log::$_strStartTimestamp ) );
        if ( ! empty( Config::$_fLogDirectOutput ) ) {
            echo "\n<br />\n$strFile#$strLine $strMethod(): $str\n<br />\n";
        }
        self::$_arr[] = array( 'l' => $strLevel, 'd' => $strDuration, 'f' => basename( $strFile ),
            'm' => $strMethod, 'n' => $strLine, 't' => $str );
    }

    public static function info( $str, $strMethod = '', $strLine = '', $strFile = '' ) {
        if ( empty( Config::$_fDebug ) ) return;
        if ( ! "$strMethod$strLine$strFile" ) {
            list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
        }
        self::add( 'i', $str, $strMethod, $strLine, $strFile );
        return true;
    }

    public static function always( $str, $strMethod = '', $strLine = '', $strFile = '' ) {
        if ( ! "$strMethod$strLine$strFile" ) {
            list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
        }
        self::add( 'a', $str, $strMethod, $strLine, $strFile );
        return true;
    }

    public static function error( $str, $strMethod = '', $strLine = '', $strFile = '' ) {
        self::$_nErrors = self::$_nErrors + 1;
        if ( ! "$strMethod$strLine$strFile" ) {
            list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0,2 ) );
        }
        self::add( 'e', $str, $strMethod, $strLine, $strFile );
        return false;
    }

    public static function systemError( $str, $strMethod = '', $strLine = '', $strFile = '' ) {
        self::$_nSystemErrors = self::$_nSystemErrors + 1;
        if ( ! "$strMethod$strLine$strFile" ) {
            list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
        }
        error_log( "$strFile#$strLine $strMethod(): " . addslashes( $str ) );
        if ( ! empty( Config::$_fSytemErrorDirectOutput ) ) {
            echo "\n<br />\n$strFile#$strLine $strMethod(): $str\n<br />\n";
        }
        self::add( 's', $str, $strMethod, $strLine, $strFile );
        return false;
    }

    public static function filter( $strLevels ) {
        $arr = array();
        foreach ( self::$_arr as $strK => $arrE ) {
            if ( stripos( $strLevels, $arrE['l'] ) !== false ) {
                $arr[ $strK ] = $arrE;
            }
        }
        return $arr;
    }

    /**
     * Generate log strings.
     * @param array log values. See Log::add()
     * @return array of strings - the log lings
     */
    public static function toLogLines( $arr = null ) {
        if ( ! $arr ) {
            $arr = self::$_arr;
        }
        $arrReturn = array();
        foreach ( $arr as $strK => $arrE ) {
            $arrReturn[] = $arrE['d'] . ' '
                . $arrE['f'] . '#' . $arrE['n'] . ' '
                . ( $arrE['m'] ? $arrE['m'] . '()' : '-' )
                . ' ' . self::$arrLN[ $arrE['l'] ] . ' ' . $arrE['t'];
        }
        return $arrReturn;
    }

    /**
     * @return string Complete debug log as plain text with "\n" return charaters
     */
    public static function toString() {
        return implode( "\n", Log::toLogLines() ) . "\n";
    }

    /**
     * @return string Complete debug log as html with "<br />\n"
     */
    public static function toHtml() {
        $strReturn = "<ul>\n";
        foreach ( Log::toLogLines() as $str ) {
            $strReturn .= '<li>' . htmlentities( $str ) . "</li>\n";
        }
        return $strReturn . "</ul>\n";
    }

    /**
     * Save log to file
     */
    public static function toLogFile() {
        if ( empty( Config::$_strDebugLog ) ) {
            return Log::info( 'Not set Config::$_strDebugLog. So no debug log files' );
        }

        $strUri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'shell';
        $arrErr = Log::filter( 'es' ); // errors and system errors

        $str = "\n\n----------------- START " . ' at ' . gmdate( 'Y-m-d H:i:s' ) . " ----------------------\n";
        $str .= implode( "\n", Log::toLogLines( Log::$_arr ) );

        if ( count( $arrErr ) ) {
            $str .= "\n------------------------------------------\n" . implode( "\n", Log::toLogLines( $arrErr ) );
        }

        $str .= "\n==========================================\n"
            . "Prog.:      " . Log::$_strScript . " \n"
            . "GMT:        " . Log::$_strStartDate . "\n"
            . "Total time: " . sprintf( "%.4f", ( microtime( true ) - Log::$_strStartTimestamp ) ) . " sec\n"
            . "URL:        " . $strUri . "\n"
            . "Errors:     " . count( $arrErr ) . "\n"
            . "ProcessID:  " . Log::$_nPid . "\n"
            . "Result Msg: " . Log::$_strLastLogMessage . "\n"
            . "==========================================\n\n";

        if ( @file_put_contents( Config::$_strDebugLog, $str, FILE_APPEND ) ) {
            return true;
        } else {
            return Log::systemError( Config::$_strDebugLog . ' is not writeable!' );
        }
    }

    /**
     * Save log to file
     */
    public static function toDatabase() {
        if ( empty( Config::$_DB_DEBUG_TABLE ) ) {
            return Log::info( 'Not set Config::$_DB_DEBUG_TABLE. So no debugging into database' );
        }

        if ( empty( AlienDB::$_linkId ) ) {
            return Log::systemError( 'No database connection. Check Config for DB settings!' );
        }

        $strUri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'shell';
        $strTotalTime = sprintf( "%.4f", ( microtime( true ) - Log::$_strStartTimestamp ) );

        $strStats
            = "Prog.:      " . Log::$_strScript . " \n"
            . "GMT:        " . Log::$_strStartDate . "\n"
            . "Total time: " . $strTotalTime . " sec\n"
            . "URL:        " . $strUri . "\n"
            . "Errors:     " . self::$_nErrors . "\n"
            . "SystemErr:  " . self::$_nSystemErrors . "\n"
            . "ProcessID:  " . Log::$_nPid . "\n"
            . "Result Msg: " . Log::$_strLastLogMessage . "\n";

        if ( self::$_nErrors ) {
            $arrErr = Log::filter( 'es' ); // errors and system errors
            $strErr = implode( "\n", Log::toLogLines( $arrErr ) );
        } else {
            $strErr = '';
        }

        $strDebgugTrace = implode( "\n", Log::toLogLines( Log::$_arr ) );

        $strSql = "INSERT INTO `" . Config::$_DB_PREFIX . Config::$_DB_DEBUG_TABLE . "` "
            . "(`gmt`, `seconds`, `script`, `last_log_msg`, `errors`, `system_errors`, `log_trace`, `stats`, `error_msg`) VALUES ("
            . "'" . dbEscStr( Log::$_strStartDate )
            . "', '" . $strTotalTime
            . "', '" . dbEscStr( Log::$_strScript )
            . "', '" . dbEscStr( Log::$_strLastLogMessage )
            . "', '" . ( 0 + self::$_nErrors )
            . "', '" . ( 0 + self::$_nSystemErrors )
            . "', '" . dbEscStr( $strDebgugTrace )
            . "', " . ( $strStats ? "'" . dbEscStr( $strStats ) . "'" : "null" )
            . ", " . ( $strErr ? "'" . dbEscStr( $strErr ) . "'" : "null" )
            . ")";

        dbQuery( $strSql );

        if ( AlienDB::$_strDbErrMsg ) {
            Log::always( AlienDB::$_strDbErrMsg );
            if ( stripos( AlienDB::$_strDbErrMsg, 'table' ) ) {
                self::createTable();
                dbQuery( $strSql );
            }
        }
        return AlienDB::$_strDbErrMsg ? false : true;
    }

    private static function createTable() {
        Log::info( 'started' );
        dbQuery( "CREATE TABLE IF NOT EXISTS `" . Config::$_DB_PREFIX . Config::$_DB_DEBUG_TABLE . "` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `gmt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `seconds` float UNSIGNED NOT NULL DEFAULT '0',
            `script` varchar(30) NOT NULL DEFAULT '',
            `last_log_msg` varchar(256) NOT NULL,
            `errors` int(11) UNSIGNED NOT NULL DEFAULT '0',
            `system_errors` int(11) UNSIGNED NOT NULL DEFAULT '0',
            `log_trace` text,
            `stats` text,
            `error_msg` text,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8" );
    }
}

Log::init();
