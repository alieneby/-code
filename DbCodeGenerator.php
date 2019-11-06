<?php

require_once( 'Alien.php' );
require_once( 'DbGenUtils.php' );

class CodeGenColumn {

    /**
     * @var String Name of column in database
     */
    var $_strNameDB;

    /**
     * @var String Name of column in php class
     */
    var $_strNamePhp;

    /**
     * @var String comment
     */
    var $_strComment;

    /**
     * @var flags of fields from table (e.g. "PRI")
     */
    var $_strKey;

    /**
     * @var array enum values of fields from table
     */
    var $_arrAllowedValues;

    /**
     * @var types of fields from table
     */
    var $_strType;

    /**
     * @var flags if not null or null allowed
     */
    var $_strNull;

    /**
     * @var extra infos
     */
    var $_strExtra;

    /**
     * @var default value
     */
    var $_strDefault;
    var $_strDefaultPhp;
    var $_strDefaultSql;

    var $_nDoEscape;
    var $_nIsNumeric;
}

class CodeGenTable {
    var $_arrColumns = array();
    /**
     * @var String Name of table in database
     */
    var $_strNameDB;

    /**
     * @var String Name of table in php class
     */
    var $_strNamePhp;

    /**
     * @var String Name of table in php class
     */
    var $_strAutoIncrCol;
}

/**
 */
class DbCodeGenerator {
    var $_strPath = ''; // with slash at the end
    var $_arrTables = array();


    /**
     * @var bool debug mode on or off
     */
    var $_debug = false;

    /**
     * @var String Table name prefix
     */
    var $_strTablePrefix = "wzw_";

    /**
     * @var String Column name prefix
     */
    var $_strColumnPrefix = "";

    /**
     * @var string source code
     */
    var $code;

    /**
     * constructor
     * stores all required mysql access data
     *
     * @param string $strPath dbgen/genfiles path absolute
     */
    function DbCodeGenerator( $strPath = "./genfiles/" ) {

        if ( ! AlienDB::$_linkId ) {
            Log::error( 'No database connection', __METHOD__, __LINE__, __FILE__ );
            return false;
        }

        if ( strlen( $strPath ) > 1 && substr( $strPath, -1 ) != "/" ) $strPath .= "/";

        $this->_strPath = $strPath;
        $this->_strTablePrefix = Config::$_DB_PREFIX;
        $this->_debug = true;

        Log::always( 'Database connection successful.', __METHOD__, __LINE__, __FILE__ );
        Log::always( 'getting database structure', __METHOD__, __LINE__, __FILE__ );

        if ( ! $this->getDBStructure( Config::$_DB_DB ) ) {
            Log::error( 'Generation stopped', __METHOD__, __LINE__, __FILE__ );
        }

        if ( ! $this->delOldFiles() ) {
            Log::error( 'Generation stopped', __METHOD__, __LINE__, __FILE__ );
        }

        Log::always( 'source code creation starts...', __METHOD__, __LINE__, __FILE__ );

        $this->createCode();
        Log::always( 'source code creation finished', __METHOD__, __LINE__, __FILE__ );
    }

    /**
     * Delete all previous generated files.
     */
    function delOldFiles() {
        Log::info( 'started', __METHOD__, __LINE__, __FILE__ );
        $tb = new CodeGenTable();
        $fReturn = true;
        foreach ( $this->_arrTables as $tb ) {
            $strFilename = $this->_strPath . $tb->_strNamePhp . ".php";
            if ( is_file( $strFilename ) ) {
                Log::always( "unlink $strFilename", __METHOD__, __LINE__, __FILE__ );
                if ( unlink( $strFilename ) ) {
                    Log::always( "unlink $strFilename", __METHOD__, __LINE__, __FILE__ );
                } else {
                    $fReturn = false;
                    Log::error( "Can not unlink $strFilename", __METHOD__, __LINE__, __FILE__ );
                }
            }
        }
        return $fReturn;
    }

    /**
     * connect to the database and get the table structure
     */
    function getDBStructure( $strDatabase ) {

        Log::info( "Database successfully selected: $strDatabase", __METHOD__, __LINE__, __FILE__ );

        $result = mysqli_query( AlienDB::$_linkId, "show tables" );
        if ( ! $result ) {
            return Log::error( 'Error getting table names: ' . mysqli_error(), __METHOD__, __LINE__, __FILE__ );
        }

        while ( $row = mysqli_fetch_row( $result ) ) {
            $tb = new CodeGenTable();
            $tb->_strNameDB = $row[0];
            $tb->_strNamePhp = ucfirst( $this->rename( $row[0], $this->_strTablePrefix ) );
            if ( is_numeric( substr( $tb->_strNamePhp, 0, 1 ) ) ) {
                $tb->_strNamePhp = $this->_strTablePrefix . $tb->_strNamePhp;
            }
            $this->_arrTables[] = $tb;
        }
        mysqli_free_result( $result );

        foreach ( $this->_arrTables as $tb ) {
            $this->getTableStructure( $tb );
        }
        return true;
    }

    function getGeneratedDefaultValue( $codeGenColumn ) {
        $t = self::getType( $codeGenColumn->_strType );
        echo " t=$t<br>";

        if ( $codeGenColumn->_strExtra == "auto_increment" ) {
            return array( 'null', "null" );
        }

        if ( isset( $codeGenColumn->_strDefault ) ) {
            echo $codeGenColumn->_strNameDB . " def is set<br>\n";
            if ( $codeGenColumn->_nIsNumeric ) {
                return array( $codeGenColumn->_strDefault, $codeGenColumn->_strDefault );
            } elseif ( strtoupper( $codeGenColumn->_strDefault ) == 'CURRENT_TIMESTAMP' ) {
                return array( "'CURRENT_TIMESTAMP'", 'NOW()' );
            } else {
                return array( "'" . $codeGenColumn->_strDefault . "'", "'" . $codeGenColumn->_strDefault . "'" );
            }
        }

        if ( $codeGenColumn->_strNull == 'YES' ) {
            //echo $codeGenColumn->_strNameDB . " null<br>\n";
            return array( "null", "NULL" );
        }

        if ( strpos( $t, "enum" ) !== false ) {
            $str = Alien::strStringBetween( $codeGenColumn->_strType, "'", "'" );
            return array( "'$str'", "'$str'" );

        }
        if ( strpos( $t, "int" ) !== false || $t == "decimal" || $t == "float" || $t == "real" || $t == "double" ) {
            //echo $codeGenColumn->_strNameDB . " 0<br>\n";
            return array( 0, 0 );
        }
        if ( strpos( $t, "text" ) !== false || strpos( $t, "char" ) !== false || strpos( $t, "blob" ) !== false || strpos( $t, "binary" ) !== false ) {
            return array( "''", "''" );
        }

        if ( $t == "datetime" || $t == "timestamp" ) {
            return array( "'0000-00-00 00:00:00'", "'0000-00-00 00:00:00'" );
        }
        if ( $t == "date" ) {
            return array( "'0000-00-00'", "'0000-00-00'" );
        }
        if ( $t == "time" ) {
            return array( "'00:00:00'", "'00:00:00'" );
        }
        if ( $t == "year" ) {
            return array( "'0000'", "'0000'" );
        }
        return array( "''", "''" );
    }

    /**
     * connect to the database and get the table structure
     *
     * @access private
     */
    function getTableStructure( CodeGenTable $tb ) {
        $result = mysqli_query( AlienDB::$_linkId, "EXPLAIN `" . $tb->_strNameDB . "`;" );
        if ( ! $result ) return $this->debug( "EXPLAIN ERROR " . $tb->_strNameDB );
        $this->debug( mysqli_num_rows( $result ) . ' data fields have been found.' );
        echo "<pre>";
        while ( $row = mysqli_fetch_assoc( $result ) ) {
            print_r( $row );
            $codeGenColumn = new CodeGenColumn();
            $codeGenColumn->_strNameDB = $row['Field'];
            $codeGenColumn->_strNamePhp = $this->rename( $row['Field'], $this->_strColumnPrefix );
            $codeGenColumn->_strKey = $row['Key'];
            $codeGenColumn->_strType = $row['Type'];
            $codeGenColumn->_strNull = $row['Null'];
            $codeGenColumn->_strExtra = $row['Extra'];
            $codeGenColumn->_strDefault = $row['Default'];
            $codeGenColumn->_strDefaultPhp = 'set later';
            $codeGenColumn->_strDefaultSql = 'set later';;
            $codeGenColumn->_nDoEscape = 1;
            $codeGenColumn->_nIsNumeric = 0;
            $codeGenColumn->_arrAllowedValues = array();

            $t = self::getType( $codeGenColumn->_strType );
            if ( strpos( $t, "int" ) !== false || $t == "decimal" || $t == "float" || $t == "real" || $t == "double" ) {
                $codeGenColumn->_nDoEscape = 0;
                $codeGenColumn->_nIsNumeric = 0;
            }

            if ( $row['Extra'] == 'auto_increment' ) {
                $tb->_strAutoIncrCol = $row['Field'];
            }

            if ( strpos( $row['Type'], "enum" ) !== false ) {
                $values = substr( $row['Type'], 5, strlen( $row['Type'] ) - 6 );
                // echo "values=$values";
                $arr = array();
                foreach ( explode( ",", $values ) as $v ) {
                    if ( strlen( $v ) > 1 ) $arr[] = substr( $v, 1, strlen( $v ) - 2 );
                }
                $codeGenColumn->_arrAllowedValues = $arr;
            }


            list( $codeGenColumn->_strDefaultPhp, $codeGenColumn->_strDefaultSql )
                = $this->getGeneratedDefaultValue( $codeGenColumn );

            print_r( $codeGenColumn );
            $tb->_arrColumns[] = $codeGenColumn;
        }
        echo "</pre>";
        mysqli_free_result( $result );

        $result = mysqli_query( AlienDB::$_linkId, "select COLUMN_NAME, COLUMN_COMMENT from information_schema.COLUMNS where  TABLE_NAME='" . $tb->_strNameDB . "';" );
        if ( $result ) {
            while ( $row = mysqli_fetch_assoc( $result ) ) {
                foreach ( $tb->_arrColumns as $key => $codeGenColumn ) {
                    if ( $codeGenColumn->_strNameDB == $row['COLUMN_NAME'] ) {
                        $codeGenColumn->_strComment = $row['COLUMN_COMMENT'];
                    }
                }
            }
            mysqli_free_result( $result );
        }

    }

    /**
     * simply code creation for all fields with their corresponding apidoc
     */
    function createCode() {
        foreach ( $this->_arrTables as $tb ) {
            $this->createFileContent( $tb );
            //  echo "<Hr><pre>"; print_r(htmlentities($this->code));echo "</pre>";
            $this->writeFile( $tb );
        }
    }

    /**
     * simply code creation for all fields with their corresponding apidoc
     */
    function createFileContent( CodeGenTable $tb ) {

        $this->code = array();
        //	1st) header
        $this->code[] = '<?php';
        //$this->code[] = "include_once 'DbGen.php';";
        $this->code[] = '/**';
        $this->code[] = ' * DO NOT CHANGE THIS FILE MANUALY!!!!!!!!!!!!';
        $this->code[] = ' * Created on ' . date( 'd.m.Y' );
        $this->code[] = ' * $Id$';
        $this->code[] = ' */';
        //	2nd) class header
        //$this->code[] = 'class '.$tb->_strNamePhp ." extends DbGen {";
        $this->code[] = 'class ' . $tb->_strNamePhp . " {";

        $this->code[] = '    /**';
        $this->code[] = '    * Database table';
        $this->code[] = '    * @access public';
        $this->code[] = '    */';
        $this->code[] = '    public static $TABLENAME ="' . $tb->_strNameDB . '";';
        $this->code[] = "    public static \$DBAUTOINCR = '" . $tb->_strAutoIncrCol . "';";
        $this->code[] = '';
        $this->code[] = '    public $_arrayObjects;';

        $this->code[] = '    /**';
        $this->code[] = '    * Not used! It is for YOU to add, fill this member.';
        $this->code[] = '    * E.g. you can put here related objects into it.';
        $this->code[] = '    * @access public';
        $this->code[] = '    */';
        $this->code[] = '';
        $this->code[] = '    /* @var $_loadDbObj ' . $tb->_strNamePhp . ' */';
        $this->code[] = '    public $_loadDbObj;';
        //	3rd) private vars
        $codeGenColumn = new CodeGenColumn();
        foreach ( $tb->_arrColumns as $codeGenColumn ) {
            /*
            $strDefault = "null";
            $t = self::getType( $codeGenColumn->_strType );
            if ( ! isset( $codeGenColumn->_strDefault ) ) {
                $strDefault = "null" ;
            } elseif ( strpos($t,"int")!==false || $t=="decimal" || $t=="float" || $t=="real" || $t=="double") {
                $strDefault = $codeGenColumn->_strDefault;
            } else {
                $strDefault = "'" . $codeGenColumn->_strDefault . "'" ;
            }
            */
            $this->code[] = '    /**';
            if ( $codeGenColumn->_strComment ) $this->code[] = '    * ' . $codeGenColumn->_strComment;
            if ( $codeGenColumn->_nIsNumeric ) {
                $this->code[] = "    * @var numeric \t" . $codeGenColumn->_strType . " \t" . $codeGenColumn->_strNameDB;
            } else {
                $this->code[] = "    * @var string  \t" . $codeGenColumn->_strType . " \t" . $codeGenColumn->_strNameDB;
            }
            $this->code[] = '    * @access public';
            $this->code[] = '    */';
            $this->code[] = '    public $_' . $codeGenColumn->_strNamePhp . ';';
            $this->code[] = '    static $_' . strtoupper( $codeGenColumn->_strNamePhp ) . '_DEFAULT = ' . $codeGenColumn->_strDefaultPhp . ';';
            if ( count( $codeGenColumn->_arrAllowedValues ) ) {
                $s = "";
                foreach ( $codeGenColumn->_arrAllowedValues as $value ) {
                    if ( $s ) $s .= ", ";
                    $s .= "\"$value\"";
                    $this->code[] = '    static $_' . strtoupper( $codeGenColumn->_strNamePhp . "_" . DbGenUtils::onlyAlphaNumeric( $value ) ) . "= '$value';";
                }
                $this->code[] = '    static $_' . $codeGenColumn->_strNamePhp . '_values = array(' . $s . ');';
            }

            $this->code[] = '';
        }

        $this->code[] = '    /**';
        $this->code[] = '    * @var array DB-PHP-Mapping';
        $this->code[] = '    */';
        $s = $sConstructorExample = $sConstructorExample2 = $sConstructorExample3 = "";
        foreach ( $tb->_arrColumns as $codeGenColumn ) {
            $s .= ( $s ? ', ' : '' )
                . "'" . $codeGenColumn->_strNameDB . "'=>'_" . $codeGenColumn->_strNamePhp . "'";
            $sConstructorExample .= ( $sConstructorExample ? ', ' : '' ) . '$' . $codeGenColumn->_strNamePhp;
            $sConstructorExample2 .= ( $sConstructorExample2 ? ', ' : '' ) . '"_' . $codeGenColumn->_strNamePhp . '" => "val"';
            $sConstructorExample3 .= ( $sConstructorExample2 ? ', ' : '' ) . '"' . $codeGenColumn->_strNameDB . '" => "val"';
        }
        $this->code[] = "    static \$_MAPPING = array( $s );";
        $this->code[] = '';

        //	clear
        $this->code[] = '    /**';
        $this->code[] = '    * Set defaults';
        $this->code[] = '    */';
        $this->code[] = '    function clear() {';
        $this->code[] = '        $emptyObj = new '.$tb->_strNamePhp.'();';
        $this->code[] = '        $this->_arrayObjects = array(); ';
        $this->code[] = '        unset( $this->_loadDbObj );';
        $this->code[] = '        foreach ( self::$_MAPPING as $strMember ) $this->$strMember = $emptyObj->$strMember;';
        $this->code[] = '    }';
        $this->code[] = '';

        $strConstruct = '';
        foreach ( $tb->_arrColumns as $codeGenColumn ) {
            if ( $codeGenColumn->_strDefault && $codeGenColumn->_strDefault ) {
                $t = self::getType( $codeGenColumn->_strType );
                if ( $t == "float" || $t == "double" || strpos( $t, "int" ) !== false || $t == "decimal" ) {
                    if ( 0 + $codeGenColumn->_strDefault <> 0 ) {
                        $strConstruct .= '        $this->_' . $codeGenColumn->_strNamePhp . '=' . $codeGenColumn->_strDefault . ";\n";
                    }
                } elseif ( strpos( $t, "enum" ) !== false || strpos( $t, "char" ) !== false || strpos( $t, "text" ) !== false || strpos( $t, "blob" ) !== false ) {
                    $strConstruct .= '        $this->_' . $codeGenColumn->_strNamePhp . "='" . $codeGenColumn->_strDefault . "';\n";
                }
            }
        }

        $this->code[] = '
    /**
    * Three ways to call:
    * new ' . $tb->_strNamePhp . '( ' . $sConstructorExample . ' )
    * new ' . $tb->_strNamePhp . '( array(' . $sConstructorExample2 . ' )
    * new ' . $tb->_strNamePhp . '( array(' . $sConstructorExample3 . ' )
    * First way, you need all parameters!
    * Second + third way number of parameters are optional.
    */
    public function __construct() {
' . $strConstruct . '
        $arrArgs = func_get_args();
        $i = func_num_args();
        if ( ! $i || ! isset( $arrArgs ) || ! isset( $arrArgs[0] ) ) return;
        if ( $i==1 && is_array( $arrArgs[0] ) ) {
            foreach ( $arrArgs[0] as $k => $v ) {
                if ( isset( self::$_MAPPING[$k] ) ) {
                    $m = self::$_MAPPING[$k];
                    $this->$m = $v;
                } elseif (in_array($k, self::$_MAPPING)) {
                    $this->$k = $v;
                } else $this->_arrayObjects["construct-error-$k"] = $v;
            }
        } elseif ( $i == count( self::$_MAPPING ) ) {
            $u=0;
            foreach (self::$_MAPPING as $strMember) {
                $this->$strMember = $arrArgs[$u++];
            }
        } else $this->_arrayObjects["construct-error"] = $arrArgs;
    }
';

        // generate column names as String, eg: col1,col2,col3
        $this->code[] = '    /**';
        $this->code[] = '    * All database columns. Usefull in select part of a sql query. It adds the tablename to every column name.';
        $this->code[] = '    * e.g. select     t1.col1, t1.col2, t3.col3     from table t1 ';
        $this->code[] = '    * @param $strTablename string      (optional) Name or shortname of table (optional)';
        $this->code[] = '    * @param $arrDBCols    array       (optional) Columnnames';
        $this->code[] = '    * @return string               All database columns listed in one string (comma + space seperated)';
        $this->code[] = '	 */';
        $this->code[] = '    static function dbColNames( $strTablename = "", $arrDBCols = "") {';
        $this->code[] = '        if ( ! is_array( $arrDBCols ) ) $arrDBCols = array_keys( self::$_MAPPING );';
        $this->code[] = '        $strReturn = "";';
        $this->code[] = '        foreach( $arrDBCols as $strCol ) {';
        $this->code[] = '            $strReturn .= ( $strReturn ? "," : "" ) . ( $strTablename ? "`$strTablename`.`$strCol` \'$strTablename.$strCol\'" : "`$strCol`" );';
        $this->code[] = '        }';
        $this->code[] = '        return $strReturn;';
        $this->code[] = '    }';
        $this->code[] = '';

        // getter for tablename
        $this->code[] = '    /**';
        $this->code[] = '    * @return string database table name';
        $this->code[] = '	 */';
        $this->code[] = '    public function dbTbName() { return self::$TABLENAME; }';
        $this->code[] = '    public function getStaticMember($strName) { return self::$$strName; }';
        $this->code[] = '';

        // generate set part as String, eg: col1,col2,col3
        $this->code[] = '    /**';
        $this->code[] = '    * Build a string with col1=value1, col2=value2,...';
        $this->code[] = '    * @return string    "set" part of an update sql query. Eg: dbColumn1=object.member1, dbColumn2=object.member2, ...';
        $this->code[] = '    */';
        $this->code[] = '    function dbSetCols() {';
        $this->code[] = '        $s = "";';
        $this->code[] = '        $fOnlyModified = isset( $this->_loadDbObj );';
        $this->code[] = '        foreach ( self::$_MAPPING as $strDbCol => $strMember ) {';
        $this->code[] = '           if ( $fOnlyModified && $this->$strMember !== $this->_loadDbObj->$strMember  )  {';
        $this->code[] = '               $s .= ( $s ? \', \' : \'\' ) . "`$strDbCol`=\'" . dbEscStr( $this->$strMember ) . "\'";';
        $this->code[] = '           } elseif ( ! $fOnlyModified ) {';
        $this->code[] = '               if ( isset( $this->$strMember ) ) {';
        $this->code[] = '                   $s .= ( $s ? \', \' : \'\' ) . "`$strDbCol`=\'" . dbEscStr( $this->$strMember ) . "\'";';
        $this->code[] = '               } else {';
        $this->code[] = '                   $s .= ( $s ? \', \' : \'\' ) . "`$strDbCol`=\'\'";';
        $this->code[] = '               } ';
        $this->code[] = '           }';
        $this->code[] = '        }';
        $this->code[] = '        return $s;';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';

        // generate set part as String, eg: col1,col2,col3
        $this->code[] = '    /**';
        $this->code[] = '    * Read all member variables from $_REQUEST';
        $this->code[] = '    * Format of variable: <classname>-<membername> ALL IN LOWERCASE!!!!!';
        $this->code[] = '    * @param $strSuffix string Append this string to the key-variablename (usefull in loops, to add many obj to an array)';
        $this->code[] = '    */';
        $this->code[] = '    function readRequest( $strSuffix = "" ) {';
        $this->code[] = '        $this->fromArray( $strSuffix, $_REQUEST );';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';

        // generate set part as String, eg: col1,col2,col3
        $this->code[] = '    /**';
        $this->code[] = '    * Put all object members into the array';
        $this->code[] = '    * Format of key-variable: <lowercase classname><suffix>-<membername>';
        $this->code[] = '    * @param $strSuffix string    Append this string to the key-variablename (useful in loops, to add many obj to an array)';
        $this->code[] = '    * @param $arr       array     Add object members into this array.';
        $this->code[] = '    * @return array';
        $this->code[] = '    */';
        $this->code[] = '    function toArray( $strSuffix = "", &$arr = array() ) {';
        $this->code[] = '        $s = strtolower( __CLASS__ ) . $strSuffix . \'-\';';
        $this->code[] = '        foreach ( self::$_MAPPING as $strMember ) {';
        $this->code[] = '            if ( isset( $this->$strMember ) ) $arr[ $s.substr( $strMember, 1 ) ] = $this->$strMember;';
        $this->code[] = '            else unset( $arr[ $s . substr( $strMember, 1 ) ] );';
        $this->code[] = '        }';
        $this->code[] = '        return $arr;';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';


        // generate set part as String, eg: col1,col2,col3
        $this->code[] = '    /**';
        $this->code[] = '    * Read all object members from $arr';
        $this->code[] = '    * Format of variable: <lowercase classname><suffix>-<membername>';
        $this->code[] = '    * @param string $strSuffix     Append this string to the key-variablename (usefull in loops, to add many obj to an array)';
        $this->code[] = '    * @param array $arr            Object members will be filled from that array.';
        $this->code[] = '    */';
        $this->code[] = '    function fromArray( $strSuffix="", &$arr ) {';
        $this->code[] = '        if ( ! $arr || ! is_array( $arr ) ) { $this->clear(); return; }';
        $this->code[] = '        $s = strtolower( __CLASS__ ) . $strSuffix . \'-\';';
        $this->code[] = '        foreach ( self::$_MAPPING as $strMember ) {';
        $this->code[] = '            $str = $s . substr( $strMember, 1 );';
        $this->code[] = '            if ( isset( $arr[ $str ] ) ) $this->$strMember = $arr[ $str ]; else $this->$strMember = "";';
        $this->code[] = '        }';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';


        // generate set part as String, eg: col1,col2,col3
        $this->code[] = '    /**';
        $this->code[] = '    * Read all object members from $row.';
        $this->code[] = '    * @param $row           array     Row should be a return value of mysqli_fetch_assoc();';
        $this->code[] = '    * @param $strColPrefix  string    (Optional)';
        $this->code[] = '    */';
        $this->code[] = '    function fromDBRow( &$row, $strColPrefix = "") {';
        $this->code[] = '        if ( ! $row || ! is_array( $row ) ) { $this->clear(); return; }';
        $this->code[] = '        if ( $strColPrefix ) $strColPrefix .= ".";';
        $this->code[] = '        foreach ( self::$_MAPPING as $strDbCol => $strMember ) {';
        $this->code[] = '            if ( isset( $row[ $strColPrefix . $strDbCol ] ) ) $this->$strMember = $row[ $strColPrefix . $strDbCol ];';
        $this->code[] = '            else $this->$strMember = "";';
        $this->code[] = '        }';
        $this->code[] = '    }';
        $this->code[] = '';

        // generate set part as String, eg: col1,col2,col3
        $this->code[] = '    /**';
        $this->code[] = '    * Read all object members from $row. $row should be a return value of mysqli_fetch_assoc();';
        $this->code[] = '    * @param string $strWhere';
        $this->code[] = '    * @param boolean $fCopy';
        $this->code[] = '    * @return ' . $tb->_strNamePhp;
        $this->code[] = '    */';
        $this->code[] = '    static function dbLoadSingle( $strWhere, $fCopy = false ) {';
        $this->code[] = '        $obj = new ' . $tb->_strNamePhp . '();';
        $this->code[] = '        $obj->dbLoad( $strWhere, $fCopy );';
        $this->code[] = '        return $obj;';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '    /**';
        $this->code[] = '    * Make a copy of object and store it in this->_loadDbObj. Needed to identify value changes.';
        $this->code[] = '    */';
        $this->code[] = '    function setLoadDbObj() { $this->_loadDbObj = unserialize( serialize( $this ) ); }';
        $this->code[] = '';
        $this->code[] = '    /**';
        $this->code[] = '    * Are members modified since setLoadDbObj() / dbLoadSingle()?';
        $this->code[] = '    * @return boolean';
        $this->code[] = '    */';
        $this->code[] = '    function isModified() {';
        $this->code[] = '        return isset( $this->_loadDbObj ) ? $this->toString() !== $this->_loadDbObj->toString() : true;';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '    function dbLoad( $strWhere, $fCopy = false ) {';
        $this->code[] = '        $this->_arrayObjects = array(); ';
        $this->code[] = '        $arrDummy = dbQueryOne( ' . $tb->_strNamePhp . '::selectSQL( $strWhere ) );';
        $this->code[] = '        $this->fromDBRow( $arrDummy );';
        $this->code[] = '        if ( $fCopy ) $this->setLoadDbObj(); else  unset( $this->_loadDbObj );';
        $this->code[] = '        return count( $arrDummy ) > 0;';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '    /**';
        $this->code[] = '    * check if all members have allowed values. ';
        $this->code[] = '    * return array of strings (error text strings] ';
        $this->code[] = '    */';
        $this->code[] = '    function check() {';
        $this->code[] = '        $arrErr = array();';
        $strNumberCheck = "";
        $strToBigCheck = "";
        $strEnumCheck = "";
        $strEmptyCheck = "";
        foreach ( $tb->_arrColumns as $codeGenColumn ) {
            $t = self::getType( $codeGenColumn->_strType );
            if ( $codeGenColumn->_strKey != "PRI" && $codeGenColumn->_strNull != "YES" && ( ! isset( $codeGenColumn->strDefault ) || ! $codeGenColumn->strDefault ) ) {
                $strEmptyCheck .= ( $strEmptyCheck ? ', ' : '' ) . '\'_' . $codeGenColumn->_strNamePhp . '\'';
            }
            if ( strpos( $t, "enum" ) !== false ) {
                $strEnumCheck .= ( $strEnumCheck ? ', ' : '' ) . '\'_' . $codeGenColumn->_strNamePhp . '\'=>self::$_' . $codeGenColumn->_strNamePhp . '_values';
            } else if ( $t == "int" || $t == "tinyint" || $t == "decimal" || $t == "smallint" || $t == "bigint" ) {
                $strNumberCheck .= ( $strNumberCheck ? ', ' : '' ) . '\'_' . $codeGenColumn->_strNamePhp . '\'';
            } else if ( $t == "float" || $t == "double" ) {
                $this->code[] = "		if(!DbGenUtils::isFloat(\$this->_" . $codeGenColumn->_strNamePhp . ")) {";
                $this->code[] = "			\$arrErr[\"" . $tb->_strNamePhp . "-" . $codeGenColumn->_strNamePhp
                    . "\"]=\"NO FLOAT/DOUBLE\";";
                $this->code[] = "		}";
            }
            $l = self::getLengthNumber( $codeGenColumn->_strType );
            if ( $l && DbGenUtils::isNumber( $l )
                && ( strpos( $t, "int" ) !== false || $t == "decimal"
                    || strpos( $t, "char" ) !== false || strpos( $t, "text" ) !== false
                    || strpos( $t, "blob" ) !== false ) ) {
                $strToBigCheck .= ( $strToBigCheck ? ', ' : '' ) . '\'_' . $codeGenColumn->_strNamePhp . '\'=>' . $l;
            }
        }
        if ( $strNumberCheck ) $this->code[] = '        $this->checkNumber( array(' . $strNumberCheck . '), $arrErr);';
        if ( $strToBigCheck ) $this->code[] = '        $this->checkToBig( array(' . $strToBigCheck . '), $arrErr);';
        if ( $strEnumCheck ) $this->code[] = '        $this->checkEnum( array(' . $strEnumCheck . '), $arrErr);';
        if ( $strEmptyCheck ) $this->code[] = '        $this->checkEmpty( array(' . $strEmptyCheck . '), $arrErr);';
        $this->code[] = '        return $arrErr;';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';

        if ( $strEmptyCheck ) {
            $this->code[] = '    function checkEmpty( $arrMembers, $arrErr ) {';
            $this->code[] = '        foreach ( $arrMembers as $strMember ) {';
            $this->code[] = '            if ( ! isset( $this->$strMember ) || \'\' . $this->$strMember == \'\' ) $arrErr[ "' . $tb->_strNamePhp . '-" . substr( $strMember, 1 ) ] = "must have a value";';
            $this->code[] = '        }';
            $this->code[] = '    }';
        }
        if ( $strNumberCheck ) {
            $this->code[] = '    function checkNumber( $arrMembers, $arrErr ) {';
            $this->code[] = '        foreach ( $arrMembers as $strMember ) {';
            $this->code[] = '            if ( ! DbGenUtils::isNumber( $this->$strMember ) ) $arrErr[ "' . $tb->_strNamePhp . '-" . substr( $strMember, 1 ) ] = "NO NUMBER";';
            $this->code[] = '        }';
            $this->code[] = '    }';
        }
        if ( $strToBigCheck ) {
            $this->code[] = '    function checkToBig( $arrMembers, $arrErr ) {';
            $this->code[] = '        foreach ( $arrMembers as $strMember => $nLen ) {';
            $this->code[] = '          if ( isset( $this->$strMember ) && strlen( "" . $this->$strMember ) > $nLen ) $arrErr["' . $tb->_strNamePhp . '-" . substr( $strMember, 1 ) ] = "TO BIG";';
            $this->code[] = '        }';
            $this->code[] = '    }';
        }
        if ( $strEnumCheck ) {
            $this->code[] = '    function checkEnum( $arrMembers, $arrErr ) {';
            $this->code[] = '        foreach ( $arrMembers as $strMember => $arrValues ) {';
            $this->code[] = '            if ( isset( $this->$strMember ) && $this->$strMember && ! in_array( $this->$strMember, $arrValues ) ) $arrErr[ "' . $tb->_strNamePhp . '-" . substr( $strMember, 1 ) ] = "BAD VALUE";';
            $this->code[] = '        }';
            $this->code[] = '    }';
        }
        $this->code[] = '';

        $this->code[] = '    /**';
        $this->code[] = '    * @return string SQL Query for an insert command';
        $this->code[] = '    */';
        $this->code[] = '    function insertSQL() {';
        if ( $tb->_strAutoIncrCol ) {
            $phpN = '';
            foreach ( $tb->_arrColumns as $codeGenColumn ) {
                if ( $tb->_strAutoIncrCol == $codeGenColumn->_strNameDB ) {
                    $phpN = $codeGenColumn->_strNamePhp;
                    break;
                }
            }
            $neededComma = count( $tb->_arrColumns ) > 1 ? ',' : '';
            $strAutoCol = "\".(\$this->_" . $phpN . "? '`" . $tb->_strAutoIncrCol . "`$neededComma ' : '').\"";
            $strAutoVal = "\".(\$this->_" . $phpN . "? (0+\$this->_" . $phpN . ").'$neededComma' : '').\"";
        } else $strAutoCol = $strAutoVal = '';
        $s = "";
        $s2 = "";
        foreach ( $tb->_arrColumns as $codeGenColumn ) {
            if ( $tb->_strAutoIncrCol == $codeGenColumn->_strNameDB ) continue;
            $default = $codeGenColumn->_strDefault;

            $strComma = $s2 ? ",\"\n\t\t.\"" : "";
            $s .= "$strComma `" . $codeGenColumn->_strNameDB . "`";

            if ( $codeGenColumn->_strDefaultSql === 'NULL' ) {
                $s2 .= "$strComma \" .(isset(\$this->_" . $codeGenColumn->_strNamePhp . ")? \"'\".dbEscStr(\$this->_" . $codeGenColumn->_strNamePhp . ").\"'\":\"null\").\"";
            } elseif ( $codeGenColumn->_strDefault === 'CURRENT_TIMESTAMP' ) {
                $s2 .= "$strComma \" .(isset(\$this->_" . $codeGenColumn->_strNamePhp . ")? \"'\".dbEscStr(\$this->_" . $codeGenColumn->_strNamePhp . ").\"'\":\"NOW()\").\"";
            } else {
                $s2 .= "$strComma '\" .(isset(\$this->_" . $codeGenColumn->_strNamePhp . ")? dbEscStr(\$this->_" . $codeGenColumn->_strNamePhp . "): " . $tb->_strNamePhp . '::$_' . strtoupper( $codeGenColumn->_strNamePhp ) . "_DEFAULT) . \"'";
            }
        }
        $this->code[] = "        return  \"insert into `" . $tb->_strNameDB . "` ($strAutoCol$s) values ($strAutoVal$s2)\";";
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';

        $this->code[] = '    /**';
        $this->code[] = '    * @param $strWhere string    Where part without "where", eg "col=123" ';
        $this->code[] = '    * @return string SQL Query for an update command';
        $this->code[] = '    */';
        $this->code[] = '    function updateSQL( $strWhere = "" ) {';
        $s2 = $w = $strSinglePrimKey = $strSinglePrimKey2 = "";
        foreach ( $tb->_arrColumns as $codeGenColumn ) {
            if ( $codeGenColumn->_strKey == "PRI" ) {
                if ( $w ) {
                    $w .= " and ";
                    $strSinglePrimKey = '';
                } else $strSinglePrimKey = "`" . ( $strSinglePrimKey2 = $codeGenColumn->_strNameDB ) . "`";
                $w .= "`" . $codeGenColumn->_strNameDB . "`='\" . dbEscStr( \$this->_" . $codeGenColumn->_strNamePhp . " ) .\"'";
            }
        }
        $this->code[] = '        if ( ! $strWhere ) $strWhere = "' . $w . '";';
        $this->code[] = "        return \"update `" . $tb->_strNameDB . "` set \" . \$this->dbSetCols() . ' where '.\$strWhere;";
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';
        $this->code[] = '    static function dbLoadMany( $strWhere, $fCopy = false, $strSelectColumns = "" ) {';
        $this->code[] = '        if ( stripos( $strWhere, "limit " ) === false ) $strWhere .= " limit 0, 500";';
        $this->code[] = '        $arr = array();';
        $this->code[] = '        $result = dbQuery( ' . $tb->_strNamePhp . '::selectSQL( $strWhere, $strSelectColumns ) );';
        $this->code[] = '        if ( $result ) {';
        $this->code[] = '           while ( $row = mysqli_fetch_assoc( $result ) ) {';
        $this->code[] = '               $o = new ' . $tb->_strNamePhp . '();';
        $this->code[] = '               $o->fromDBRow( $row );';
        $this->code[] = '               if ( $fCopy ) $o->setLoadDbObj();';
        if ( $strSinglePrimKey ) $this->code[] = "               \$arr[ '' . \$row[ '$strSinglePrimKey2' ] ] = \$o;";
        else                     $this->code[] = "               \$arr[] = \$o;";
        $this->code[] = '           } ';
        $this->code[] = '           dbFreeResult( $result );';
        $this->code[] = '        };';
        $this->code[] = '        return $arr;';
        $this->code[] = '    }';
        $this->code[] = '';


        $this->code[] = '    /**';
        $this->code[] = '    * @param string $strWhere  Where part without "where", eg "col=123" ';
        $this->code[] = '    * @param string $strSelect Select database columns,eg "id, col3" ';
        $this->code[] = '    * @return string   SQL Query for a select command with all columns';
        $this->code[] = '    */';
        $this->code[] = '    static function selectSQL( $strWhere, $strSelect = "" ) {';
        if ( $strSinglePrimKey ) $this->code[] = '        if ( ( (string) ( (int) $strWhere ) ) == ( (string) $strWhere ) ) $strWhere = "' . $strSinglePrimKey . '=" . intval( $strWhere );';
        $this->code[] = "        return \"select \" . ( \$strSelect ? \$strSelect : self::dbColNames() ) . \" from `" . $tb->_strNameDB . "`\" . ( \$strWhere ? ' where ' : '' ) . \$strWhere;";
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';
        $this->code[] = '    /**';
        $this->code[] = '    * @param string $strWhere  Where part without "where", eg "col=123" ';
        $this->code[] = '    * @return string   SQL Query for a delete command';
        $this->code[] = '    */';
        $this->code[] = "    function deleteSQL( \$strWhere = '' ) {";
        $this->code[] = '        if ( ! $strWhere ) $strWhere = "' . $w . '";';
        $this->code[] = "        return \"delete from `" . $tb->_strNameDB . "` where \" . \$strWhere;";
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';
        $this->code[] = '    /**';
        $this->code[] = '    * @return string All members in one string. Format: [name:value],[name:value],..';
        $this->code[] = '    */';
        $this->code[] = '    function toString() {';
        $this->code[] = '        $str = "";';
        $this->code[] = '        foreach ( self::$_MAPPING as $strMember ) $str .= ( $str ? ", ":"" ) . "[$strMember:" . ( isset( $this->$strMember ) ? $this->$strMember : "" ) . "]";';
        $this->code[] = '        return $str;';
        $this->code[] = '    }';
        $this->code[] = '';
        $this->code[] = '';


        //	last) footer
        $this->code[] = '}';
        $this->code[] = '/** this class was automatically generated by class-gen **/';
        $this->code[] = '';

        $this->code = implode( "\n", $this->code );
    }

    static function getLengthNumber( &$strFieldInfo ) {
        $pos1 = strpos( $strFieldInfo, "(" );
        $pos2 = strpos( $strFieldInfo, ")" );
        if ( $pos1 === false || $pos2 === false ) return false;
        return substr( $strFieldInfo, $pos1 + 1, $pos2 - $pos1 - 1 );
    }

    static function getType( &$strFieldInfo ) {
        $pos1 = strpos( $strFieldInfo, "(" );
        if ( $pos1 === false ) return $strFieldInfo;
        return substr( $strFieldInfo, 0, $pos1 );
    }

    /**
     * writes the data to file
     *
     * @param string filename
     * @param string content
     */
    function writeFile( CodeGenTable $tb ) {
        $strFilename = $this->_strPath . $tb->_strNamePhp . ".php";
        $fh = fopen( $strFilename, 'w' );
        if ( $fh ) {
            fwrite( $fh, $this->code );
            fclose( $fh );
            echo "$strFilename created<br/>";

        } else echo "CAN NOT WRITE $strFilename";
    }

    /**
     * this function renames a given mysql name
     * the normally given mysql name is with a lot underscores, in php i need
     * the camelcase
     * e.g.: acl_user_id => aclUserId
     *
     * @param string $strMySqlName string
     * @param string $strPrefix like get, set or is
     * @return string output camel-case foramtted string
     */
    function rename( $strMySqlName, $strPrefix = '' ) {
        if ( $strPrefix && strpos( strtolower( $strMySqlName ), strtolower( $strPrefix ) ) === 0 ) {
            $strMySqlName = substr( $strMySqlName, strlen( $strPrefix ) );
        }
        $strMySqlName = preg_replace('/[^a-zA-Z0-9]/', '_', trim( $strMySqlName ) );
        $arrParts = explode( '_', $strMySqlName );
        $strReturn = '';

        foreach ( $arrParts as $p => $strPart ) {
            //	the first letter had to be lowercase
            if ( ! $strReturn ) {
                $strReturn .= strtolower( $strPart );
            } else {
                $strReturn .= ucfirst( strtolower( $strPart ) );
            }
        }
        //echo count($arrParts)."rename($strMySqlName)=$strReturn, ";
        return $strReturn;
    }

    /**
     * debug stuff to console output
     */
    function debug( $str ) {
        if ( $this->_debug ) {
            echo 'debug: ' . $str . "\n";
        }
    }
}

?>
