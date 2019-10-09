<?php
require_once 'Config.php';
require_once Config::$_strAlien . '/Alien.php';
include_once Config::$_strAlien . "/DbCodeGenerator.php";

if ( empty( Config::$_strGeneratedDbClassesPath ) ) {
    $strErr = 'Please set Config::$_strGeneratedDbClassesPath!';
    echo "$strErr\n";
    👽::🖖( $strErr );
}

if ( empty( AlienDB::$_linkId ) ) {
    dbConnector();
}

if ( !  is_dir( Config::$_strGeneratedDbClassesPath ) ) {
    if ( ! mkdir( Config::$_strGeneratedDbClassesPath ) ) {
        $strErr = "Can not create directory " . Config::$_strGeneratedDbClassesPath;
        echo "$strErr\n";
        👽::🖖( $strErr );
    }
}
$a = new DbCodeGenerator( Config::$_strGeneratedDbClassesPath );

👽::🖖( 'Done' );
