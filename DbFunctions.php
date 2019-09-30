<?php
if ( ! function_exists( 'dbQuery' ) && ! empty ( Config::$_DB_DB ) ) {
    Log::info( "mysqli", __METHOD__, __LINE__, __FILE__ );

    class AlienDB {
        static $_linkId = 0;
        static $_strDbErrMsg = '';
        static $_dbTimeQueryEnd = 0;
        static $_DB_LAST_INSERT_ID = 0;
        static $_DB_AFFECTED_ROWS = 0;
        static $_DB_LAST_SINGLE_STR = '';
        static $_FILE_BASENAME;
        static $_strGeneratedDbClassesPath; // basename of directory eg. 'dbGenated'

        static function init() {
            self::$_FILE_BASENAME = basename( __FILE__ );

            self::$_strGeneratedDbClassesPath
                = empty( Config::$_strGeneratedDbClassesPath )
                ? '/-/'
                : '/' . basename( realpath( Config::$_strGeneratedDbClassesPath ) ) . '/';
        }
    }

    AlienDB::init();


    /**
     */
    function dbConnector() {
        Log::info( "started", __METHOD__, __LINE__, __FILE__ );

        if ( AlienDB::$_linkId ) {
            return AlienDB::$_linkId;
        }

        AlienDB::$_linkId = mysqli_connect(
            Config::$_DB_HOST,
            Config::$_DB_USER,
            Config::$_DB_PWD,
            Config::$_DB_DB,
            empty( Config::$_DB_PORT ) ? 0 : Config::$_DB_PORT
        );

        if ( AlienDB::$_linkId ) {
            Log::info( "db connected", __METHOD__, __LINE__, __FILE__ );
            mysqli_set_charset( AlienDB::$_linkId, "utf8" );
            //mysqli_query( AlienDB::$_linkId, "SET SESSION sql_mode='TRADITIONAL'");            
        } else {
            Log::system_error( "Can not connect to database", __METHOD__, __LINE__, __FILE__ );
        }
        return AlienDB::$_linkId;
    }

    /**
     * Execute SQL query and return result of mysqli_query().
     * Do also some time log, debug and error handling things.
     * @param string $strQuery
     * @param bool fDebug    true add sql to debug container, false = do not log sql.
     * @param resource $myLinkId
     * @return array Result of SQL
     */
    function dbQuery( $strQuery, $fDebug = true, $myLinkId = 0 ) {
        AlienDB::$_DB_LAST_INSERT_ID = AlienDB::$_DB_AFFECTED_ROWS = 0;

        $myLinkId = $myLinkId ? $myLinkId : AlienDB::$_linkId;

        if ( ! $myLinkId ) {
            return Log::system_error( "No database link!", __METHOD__, __LINE__, __FILE__ );
        }

        $strQuery = trim( $strQuery );

        list( $strMethod, $strLine, $strFile, $strCaller ) = dbQueryCaller();

        $fErr = false;
        $cmd = stripos( $strQuery, 'elect' ) == 1
            ? 's'
            : ( stripos( $strQuery, 'nsert' ) == 1
                ? 'i'
                : ( stripos( $strQuery, 'pdate' ) == 1
                    ? 'u'
                    : ( stripos( $strQuery, 'elete' ) == 1
                        ? 'd'
                        : ( stripos( $strQuery, 'eplace' ) == 1
                            ? 'r'
                            : "" ) ) ) );

        $t1 = microtime( true );

        if ( $fDebug ) Log::info( $strQuery, $strMethod, $strLine, $strFile );

        $return = mysqli_query( $myLinkId, $strQuery . " /* $strCaller */" );

        AlienDB::$_dbTimeQueryEnd = microtime( true );
        $strSeconds = sprintf( "%.4f", ( microtime( true ) - $t1 ) );

        if ( mysqli_errno( $myLinkId ) ) {
            $fErr = true;
            if ( empty( Config::$_DB_DB ) ) Log::system_error( "PLEASE SET Config::_DB* values!!!", $strMethod, $strLine, $strFile );
            else {
                AlienDB::$_strDbErrMsg = '[' . mysqli_errno( $myLinkId ) . '] ' . mysqli_error( $myLinkId );
                Log::system_error( $strQuery . "<br>\n" . AlienDB::$_strDbErrMsg, $strMethod, $strLine, $strFile );
            }
        }

        if ( ! $fErr ) {
            AlienDB::$_strDbErrMsg = '';
            if ( ( $cmd == 'i' ) && $return ) {
                AlienDB::$_DB_LAST_INSERT_ID = mysqli_insert_id( $myLinkId );
                AlienDB::$_DB_AFFECTED_ROWS = mysqli_affected_rows( $myLinkId );
            }

            if ( $cmd == "s" && $return ) {
                $n = @mysqli_num_rows( $return );
                if ( $n == 1 && mysqli_num_fields( $return ) == 1 ) {

                    list( AlienDB::$_DB_LAST_SINGLE_STR ) = mysqli_fetch_array( $return, MYSQLI_NUM );
                    mysqli_data_seek( $return, 0 );
                    Log::info( "[value:" . AlienDB::$_DB_LAST_SINGLE_STR . "] found $n db rec in $strSeconds sec", $strMethod, $strLine, $strFile );
                } else {
                    Log::info( "found $n db rec in $strSeconds sec", $strMethod, $strLine, $strFile );
                }

            } elseif ( $cmd && strpos( "iudr", $cmd ) !== false && $return ) {
                AlienDB::$_DB_AFFECTED_ROWS = @mysqli_affected_rows( $myLinkId );
                Log::info( "modified " . @mysqli_affected_rows( $myLinkId ) . " db rec in $strSeconds sec", $strMethod, $strLine, $strFile );

            } else {
                AlienDB::$_DB_AFFECTED_ROWS = @mysqli_affected_rows( $myLinkId );
                Log::info( "$strSeconds sec", $strMethod, $strLine, $strFile );
            }
        }
        return $return;
    }

    function dbInsertId() {
        return AlienDB::$_DB_LAST_INSERT_ID;
    }

    function dbAffectedRows() {
        return AlienDB::$_DB_AFFECTED_ROWS;
    }

    function dbEscStr( $str ) {
        return mysqli_real_escape_string( AlienDB::$_linkId, $str );
    }

    function dbUnEscStr( $str ) { return stripslashes( $str ); }

    /**
     * Execute SQL query and return first row as array (default is MYSQLI_ASSOC).
     * @param string $strQuery
     * @param int $linkID
     * @param $resultType (optional) Possible values are MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH
     * @return array                    Result of SQL (Perhaps empty, but never false or null).
     */
    function dbQueryOne( $strQuery, $linkID = 0, $resultType = MYSQLI_ASSOC ) {
        if ( strpos( $strQuery, " limit 0, 1" ) === false ) {
            $strQuery .= " limit 0, 1";
        }
        $result = dbQuery( $strQuery, true, $linkID );
        if ( $result && $row = mysqli_fetch_array( $result, $resultType ) ) {
            dbFreeResult( $result );
            return $row;
        } else {
            return array();
        }
    }

    /**
     * Return the first row as indexed array
     * @param string $strQuery 
     * @return array Only the first row
     */
    function dbQueryOneList( $strQuery, $linkID = 0 ) {
        return dbQueryOne( $strQuery, $linkID, MYSQLI_NUM );
    }

    /**
     * Execute SQL query and return first data string of first row.
     * @param string $strQuery
     * @return string Result of SQL
     */
    function dbQueryOneStr( $strQuery, $linkID = 0 ) {
        AlienDB::$_DB_LAST_SINGLE_STR = '';
        if ( strpos( $strQuery, " limit 0, 1" ) === false ) {
            $strQuery .= " limit 0, 1";
        }
        $result = dbQuery( $strQuery, true, $linkID );
        if ( $result ) mysqli_free_result( $result );
        return AlienDB::$_DB_LAST_SINGLE_STR;
    }

    /**
     * Execute SQL query and return result in one string
     * @param string $strQuery
     * @return string Result of SQL
     */
    function dbQueryOneStrSeparated( $strQuery, $strColSep = ',', $strRowSep = ',' ) {
        if ( stripos( $strQuery, "limit " ) === false ) $strQuery .= " limit 0, 500";
        $str = '';
        $result = dbQuery( $strQuery );
        if ( $result ) {
            while ( $row = mysqli_fetch_row( $result ) ) {
                $str .= ( $str ? $strRowSep : '' ) . implode( $strColSep, $row );
            }
            dbFreeResult( $result );
        }
        return $str;

    }

    /**
     * Execute SQL query and return result in a simple array
     * @param string $strQuery
     * @return array All found rows, but only the first column.
     */
    function dbQueryAllOneColumn( $strQuery ) {
        if ( stripos( $strQuery, "limit " ) === false ) $strQuery .= " limit 0, 500";
        $arr = array();
        $result = dbQuery( $strQuery );
        if ( $result ) {
            while ( $row = mysqli_fetch_row( $result ) ) {
                $arr[] = $row[0];
            }
            dbFreeResult( $result );
        }
        return $arr;
    }


    /**
     * Execute SQL query and return array
     * @param string $strQuery
     * @return array
     */
    function dbQueryAllOneArray( $strQuery ) {
        if ( stripos( $strQuery, "limit " ) === false ) $strQuery .= " limit 0, 500";
        $arr = array();
        $result = dbQuery( $strQuery );
        if ( $result ) {
            while ( $row = mysqli_fetch_row( $result ) ) {
                $arr[] = $row;
            }
            dbFreeResult( $result );
        }
        return $arr;
    }

    function dbQueryCaller() {
        foreach ( debug_backtrace( false ) as $arr ) {
            $strFile = $arr['file'];
            if ( $strFile != __FILE__ && ! strpos( $strFile, AlienDB::$_strGeneratedDbClassesPath ) ) {
                $strBasename = basename( $strFile );
                $strMethod
                    = ( empty( $arr['class'] ) ? '' : $arr['class'] . $arr['type'] )
                    . ( empty( $arr['function'] ) ? '' : $arr['function'] );

                return array( $strMethod, $arr['line'], $strBasename, "$strBasename#" . $arr['line'] . "" );
            }
        }
        return array( '', '', '', '' );
    }

    /**
     * Clear result and calc time since query execution
     *
     * @param int $result
     */
    function dbFreeResult( $result ) {
        if ( ! $result ) return;
        mysqli_free_result( $result );
        $strSeconds = sprintf( "%.4f", ( microtime( true ) - AlienDB::$_dbTimeQueryEnd ) );
        Log::info( "db result loaded in $strSeconds sec" );
    }

    function dbErrno( $myLinkId = null ) {
        return mysqli_errno( $$myLinkId ? $myLinkId : AlienDB::$_linkId );
    }

    function dbError( $myLinkId = null ) {
        return mysqli_error( $myLinkId ? $myLinkId : AlienDB::$_linkId );
    }

    /**
     * @param resource $result
     * @param int $result_type
     * @return array
     */
    function dbFetchArray( $result, $result_type = MYSQLI_BOTH ) {
        return mysqli_fetch_array( $result, $result_type );
    }

    /**
     * @param resource $result
     * @return array
     */
    function dbFetchRow( $result ) {
        return mysqli_fetch_row( $result );
    }

    /**
     * @param resource $result
     * @return array
     */
    function dbFetchAssoc( $result ) {
        return mysqli_fetch_assoc( $result );
    }

    /**
     * @param resource $result
     * @return int
     */
    function dbNumFields( $result ) {
        return mysqli_num_fields( $result );
    }

    /**
     * @param resource $result
     * @return int
     */
    function dbNumRows( $result ) {
        return mysqli_num_rows( $result );
    }

    /**
     * @param resource $result
     * @param int $row_number
     * @return bool
     */
    function dbDataSeek( $result, $row_number ) {
        return mysqli_data_seek( $result, $row_number );
    }

    /**
     * @param resource $myLinkId
     * @return bool
     */
    function dbClose( $myLinkId = NULL ) {
        return mysqli_close( $myLinkId );
    }
}
