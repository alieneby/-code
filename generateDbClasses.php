<?php 
require_once( 'Config.php' );
require_once( Config::$_strAlien . '/Alien.php' );
include_once( Config::$_strAlien . "/DbCodeGenerator.php" );

$a = new DbCodeGenerator( "./db/" );


👽::🖖( 
    $strLastLogMessage = 'DbCodeGenerator done', 
    $nHttpErrCode = 200, 
    $strOutput = "DbCodeGenerator done\n",
    __METHOD__, __LINE__, __FILE__
);
