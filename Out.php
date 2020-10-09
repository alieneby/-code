<?php
    require_once( 'OutMask.php' );
    
    class Out
    {
        /**
         * List of template names. The order is important.
         * @var array of strings (filenames)
         */
        static $_arrMasks = array();
        
        /**
         * Container for values, which the application wants to display.
         * @var associated array of strings
         */
        static $_arrVars = array();
        
        static $_arrIntern = array();
        
        /**
         *
         * @param String $strMasterMask mask name
         */
        static function addMask( $strMasterMask ) {
            $m = new OutMask();
            $m->strMask = $strMasterMask;
            array_push( self::$_arrMasks, $m );
        }
        
        static function getMask( $strMask ) {
            return empty( self::$_arrMasks[ $strMask ] ) ? null : self::$_arrMasks[ $strMask ];
        }
        
        static function addVar( $strK, $strV ) {
            self::$_arrVars[ $strK ] = $strV;
        }
        
        static function getVar( $strK ) {
            return empty( self::$_arrVars[ $strK ] ) ? '' : self::$_arrVars[ $strK ];
        }
        
        private static function displayMask( OutMask $mask ) {
            $nTime = microtime( true );
            $sOut = "";
            
            if ( is_file( $t = Config::$_strAbsPath . "/output/default/" . $mask->strMask ) ) $sOut = $t;
            
            if ( $sOut ) {
                self::$_arrIntern[ 'activeMask' ] = $sOut;
                include $sOut;
                unset( self::$_arrIntern[ 'activeMask' ] );
                Log::always( "Out.display()", $sOut . ' in ' . round( ( microtime( true ) - $nTime ) * 1000, 2 ) . "ms" );
            } else {
                Log::systemError( $mask->strMask );
            }
        }
        
        static function display() {
            foreach ( self::$_arrMasks as $mask ) self::displayMask( $mask );
        }
        
        static public function displayAsString() {
            ob_start();
            Out::display();
            $strHtml = ob_get_contents();
            ob_end_clean();
            return $strHtml;
        }
    }
