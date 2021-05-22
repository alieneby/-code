<?php
    
    class Log
    {
        static $_strScript = 'PHP';
        static $_strStartDate;
        static $_strStartTimestamp;
        static $_strLastLogMessage = '';
        static $_strFirstErrMsg = '';
        static $_nErrors = 0;
        static $_nSystemErrors = 0;
        static $_fDirectLog = 0;
        static $_nPid;
        static $arrLN = array( 'i' => 'Info:  ', 'e' => 'Error: ', 'a' => 'Always:', 's' => 'SystemErr:' );
        public static $_arr = array();
        
        static function init() {
            self::$_strStartTimestamp = microtime( true );
            self::$_strStartDate = gmdate( 'Y-m-d H:i:s' );
            self::$_nPid = ( getmypid() % 1000 );
        }
        
        /**
         * @param $strLevel     string  a=always, i=info, e=error, s=systemerror
         * @param $val          mixed   Message to log (string, array, obj)
         * @param $strMethod    string  Caller
         * @param $strLine      string  Caller JSON_PARTIAL_OUTPUT_ON_ERROR
         * @param $strFile      string  Caller JSON_PARTIAL_OUTPUT_ON_ERROR
         */
        private static function add( $strLevel, $val, $strMethod, $strLine, $strFile ) {
            if ( is_bool( $val ) ) {
                $str = $val ? 'true' : 'false';
            } elseif ( is_array( $val ) || is_object( $val ) ) {
                // JSON_PARTIAL_OUTPUT_ON_ERROR = 512
                // JSON_PRETTY_PRINT = 128
                $str = json_encode( $val, 512 | 128 );
            } else {
                $str = $val;
            }
            $strDuration = sprintf( "%.4f", ( microtime( true ) - Log::$_strStartTimestamp ) );
            if ( ! empty( Config::$_fLogDirectOutput ) ) {
                echo "\n<br />\n$strFile#$strLine $strMethod(): $str\n<br />\n";
            }
    
            $arr = array(
                'l' => $strLevel,
                'd' => $strDuration,
                'f' => basename( $strFile ),
                'm' => $strMethod,
                'n' => $strLine,
                't' => $str );
            
            if ( Log::$_fDirectLog ) {
                $strLine = Log::toLogLineSingle( $arr ) . '\n';
                @file_put_contents( Config::$_strDebugLog, $strLine, FILE_APPEND );
            } else {
                self::$_arr[] = $arr;
            }
        }
        
        public static function info( $val, $strMethod = '', $strLine = '', $strFile = '' ) {
            if ( empty( Config::$_fDebug ) ) return true;
            if ( ! "$strMethod$strLine$strFile" ) {
                list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
            }
            self::add( 'i', $val, $strMethod, $strLine, $strFile );
            return true;
        }
        
        public static function always( $val = '', $strMethod = '', $strLine = '', $strFile = '' ) {
            if ( ! "$strMethod$strLine$strFile" ) {
                list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
            }
            self::add( 'a', $val, $strMethod, $strLine, $strFile );
            return true;
        }
        
        public static function error( $val, $strMethod = '', $strLine = '', $strFile = '' ) {
            self::$_nErrors = self::$_nErrors + 1;
            if ( ! "$strMethod$strLine$strFile" ) {
                list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
            }
            self::add( 'e', $val, $strMethod, $strLine, $strFile );
            return false;
        }
        
        public static function systemError( $val, $strMethod = '', $strLine = '', $strFile = '' ) {
            self::$_nSystemErrors = self::$_nSystemErrors + 1;
            if ( ! "$strMethod$strLine$strFile" ) {
                list( $strMethod, $strLine, $strFile ) = SystemFunctions::getCaller( debug_backtrace( 0, 2 ) );
            }
            $str = ( is_array( $val ) || is_object( $val ) ) ? print_r( $val, true ) : $val;
            error_log( "$strFile#$strLine $strMethod(): " . addslashes( $str ) );
            if ( ! empty( Config::$_fSytemErrorDirectOutput ) ) {
                echo "\n<br />\n$strFile#$strLine $strMethod(): $str\n<br />\n";
            }
            self::add( 's', $val, $strMethod, $strLine, $strFile );
            return false;
        }
        
        
        public static function filter( $strLevels ) {
            $arr = array();
            foreach ( self::$_arr as $strK => $arrE ) {
                if ( stripos( $strLevels, $arrE[ 'l' ] ) !== false ) {
                    $arr[ $strK ] = $arrE;
                }
            }
            return $arr;
        }
        
        /**
         * Generate log strings.
         * EG.
         *      Log::toLogLines();
         * or eg. only errors and system errors:
         *      Log::toLogLines( Log::filter('es') );
         * @param $arr array    (Optional) Same format as Log::$_arr. You can use Log::filter( "e" ).
         * @return array of strings - the log lings
         */
        public static function toLogLines( $arr = null ) {
            if ( $arr === null ) {
                $arr = self::$_arr;
            }
            $arrReturn = array();
            foreach ( $arr as $strK => $arrE ) {
                $arrReturn[] = Log::toLogLineSingle( $arrE );
            }
            return $arrReturn;
        }
    
        private static function toLogLineSingle( $arrE  ) {
            return  $arrE[ 'd' ] . ' '
                    . $arrE[ 'f' ] . ' '
                    . ( $arrE[ 'm' ] ? $arrE[ 'm' ] . '()' : '-' )
                    . ' #' . $arrE[ 'n' ]
                    . ' ' . self::$arrLN[ $arrE[ 'l' ] ] . ' ' . $arrE[ 't' ];
        }
        
        /**
         * @param $arr array    (Optional) Same format as Log::$_arr. You can use Log::filter( "es" ) to get error array
         * @return string Complete debug log as plain text with "\n" return charaters
         */
        public static function toString( $arr = null ) {
            return implode( "\n", Log::toLogLines( $arr ) ) . "\n";
        }
        
        /**
         * Return an unsorted list <ul><li>...</li></ul>
         * @param $arr array    (Optional) Same format as Log::$_arr. You can use Log::filter( "es" ) to get error array
         * @return string Complete debug log as html with "<br />\n"
         */
        public static function toHtml( $arr = null ) {
            $strReturn = "<ul>\n";
            foreach ( Log::toLogLines( $arr ) as $str ) {
                $strReturn .= '<li>' . htmlentities( $str ) . "</li>\n";
            }
            return $strReturn . "</ul>\n";
        }
        
        /**
         * Return the errors as an unsorted list <ul><li>...</li></ul>
         * @return string Error log as html with "<br />\n" or ""
         */
        public static function errorsToHtml() {
            $arr = Log::filter( 'es' ); // error and system-errors
            if ( ! count( $arr ) ) return '';
            
            $strReturn = "<ul>\n";
            foreach ( Log::toLogLines( $arr ) as $str ) {
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
            
            $strUri = isset( $_SERVER[ 'REQUEST_URI' ] ) ? $_SERVER[ 'REQUEST_URI' ] : 'shell';
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
            
            $strUri = isset( $_SERVER[ 'REQUEST_URI' ] ) ? $_SERVER[ 'REQUEST_URI' ] : 'shell';
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