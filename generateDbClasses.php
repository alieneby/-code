<?php 
require_once( 'Config.php' );
require_once( Config::$_strAlien . '/Alien.php' );
include_once( Config::$_strAlien . "/DbCodeGenerator.php" );

if ( empty( Config::$_strGeneratedDbClassesPath ) ) {
    $strErr = 'Please set Config::$_strGeneratedDbClassesPath!';
    echo "$strErr\n";
    👽::🖖( $strErr );
}

if ( empty( AlienDB::$_linkId ) ) dbConnector();

$a = new DbCodeGenerator( Config::$_strGeneratedDbClassesPath );

👽::🖖( 'Done' );
