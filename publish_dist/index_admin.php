<?php
// 
// $Id: index_admin.php 9465 2002-04-24 07:38:20Z jhe $
//
// Created on: <09-Nov-2000 14:52:40 ce>
//
// This source file is part of eZ publish, publishing software.
//
// Copyright (C) 1999-2001 eZ Systems.  All rights reserved.
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, US
//

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT"); 
header("Cache-Control: no-cache, must-revalidate"); 
header("Pragma: no-cache");

// Find out, where our files are.
if ( ereg( "(.*/)([^\/]+\.php)$", $SCRIPT_FILENAME, $regs ) )
{
    $siteDir = $regs[1];
    $index = "/" . $regs[2];
}
elseif ( ereg( "(.*/)([^\/]+\.php)/?", $PHP_SELF, $regs ) )
{
	// Some people using CGI have their $SCRIPT_FILENAME not right... so we are trying this.
    $siteDir = $DOCUMENT_ROOT . $regs[1];
    $index = "/" . $regs[2];
}
else
{
	// Fallback... doesn't work with virtual-hosts, but better than nothing
	$siteDir = "./";
	$index = "/index_admin.php";
}

// What OS-type are we using?
if ( substr( php_uname(), 0, 7) == "Windows" )
    $separator = ";";
else
    $separator = ":";

// Setting the right include_path
$includePath = ini_get( "include_path" );
if ( trim( $includePath ) != "" )
    $includePath .= $separator . $siteDir;
else
    $includePath = $siteDir;
ini_set( "include_path", $includePath );

// Get the webdir.
if ( ereg( "(.*)/([^\/]+\.php)$", $SCRIPT_NAME, $regs ) )
    $wwwDir = $regs[1];

// Fallback... Finding the paths above failed, so $PHP_SELF is not set right.
if ( $siteDir == "./" )
	$PHP_SELF = $REQUEST_URI;

// Trick: Rewrite setup doesn't have index.php in $PHP_SELF, so we don't want an $index
if ( ! ereg( ".*index_admin\.php.*", $REQUEST_URI ) ) 
    $index = "";
else 
{
	// Get the right $REQUEST_URI, when using nVH setup.
    if ( ereg( "^$wwwDir$index(.+)", $REQUEST_URI, $req ) )
        $REQUEST_URI = $req[1];
    else
        $REQUEST_URI = "/";
}

// Remove url parameters
ereg( "([^?]+)", $REQUEST_URI, $regs );
$REQUEST_URI = $regs[1];
    
// Start the buffer cache
ob_start();

$UsePHPSessions = false;

if ( $UsePHPSessions == true )
{
    // start session handling
    session_start();
} 

// settings for sessions
// max timeout is set to 48 hours
ini_alter("session.gc_maxlifetime", "172800");
ini_alter("session.entropy_file","/dev/urandom"); 
ini_alter("session.entropy_length", "512");  

ini_alter("session.cache_expire", "172800");

include_once( "classes/ezdb.php" );
include_once( "classes/INIFile.php" );
include_once( "classes/template.inc" );
include_once( "classes/ezmenubox.php" );

include_once( "ezsession/classes/ezsession.php" );
include_once( "ezuser/classes/ezuser.php" );
include_once( "ezuser/classes/ezusergroup.php" );
include_once( "ezuser/classes/ezmodule.php" );
include_once( "ezuser/classes/ezpermission.php" );
include_once( "ezmodule/classes/ezmodulehandler.php" );

include_once( "classes/ezhttptool.php" );

// File functions
include_once( "classes/ezfile.php" );

$ini =& INIFile::globalINI();
$GlobalSiteIni =& $ini;

// Set the global nVH variables.
$GlobalSiteIni->Index = $index;
$GlobalSiteIni->WWWDir = $wwwDir;
$GlobalSiteIni->SiteDir = $siteDir;
unset( $index );
unset( $wwwDir );

//  $session =& eZSession::globalSession();
//  $session->fetch();
//  print( "<pre>" . $session->hash() . "</pre>" );

// do the statistics
include_once( "ezstats/classes/ezpageview.php" );

$SiteStyle =& $ini->read_var( "site", "SiteStyle" );

$GLOBALS["DEBUG"] = true;

// Remove url parameters
// ereg( "([^?]+)", $REQUEST_URI, $regs ) ;
// $REQUEST_URI = $regs[1];

$url_array =& explode( "/", $REQUEST_URI );

$user =& eZUser::currentUser();
if ( $user )
{
    if ( $url_array[1] == "help" )
    {
        $HelpMode  = "enabled";
        
        include( "design/admin/help_header.php" );
    }
    else
    {
        // html header
        if ( $PrintableVersion == "enabled" )
        {        
            include( "design/admin/print_header.php" );
        }
        else
        {
            include( "design/admin/header.php" );
        }
    }
              
    
    require( "ezuser/admin/admincheck.php" );
    
    if ( !( $HelpMode == "enabled" ) )
    {
        include_once( "ezsession/classes/ezpreferences.php" );
        $preferences = new eZPreferences();

        $site_modules = $ini->read_array( "site", "EnabledModules" );
        $modules =& eZModuleHandler::active();

        $uri =& $GLOBALS["REQUEST_URI"];

        if ( $PrintableVersion != "enabled" )
        {
            if ( !empty( $GLOBALS["ToggleMenu"] ) )
            {
                foreach( $modules as $module )
                {
                    $module_dir = strtolower( $module );
                    if ( $GLOBALS["ToggleMenu"] == $module_dir )
                    {
                        eZModuleHandler::toggle( $module_dir );
                        $uri = eZHTTPTool::removeVariable( $uri, "ToggleMenu" );
                        eZHTTPTool::header( "Location: $uri" );
                        exit;
                    }
                }
            }

            $moved_module = false;
            eZModuleHandler::moveUp( $modules, $GLOBALS["MoveUp"], $moved_module );
            if ( !$moved_module )
            {
                eZModuleHandler::moveDown( $modules, $GLOBALS["MoveDown"], $moved_module );
            }

            $uri = eZHTTPTool::removeVariable( $uri, "MoveUp" );
            $uri = eZHTTPTool::removeVariable( $uri, "MoveDown" );

            if ( $moved_module )
            {
                $preferences->setVariable( "EnabledModules", $modules );
                eZHTTPTool::header( "Location: $uri" );
                exit;
            }

            // draw modules
            foreach ( $modules as $module )
            {
                if ( !empty( $module ) )
                {
                    $module_dir =& strtolower( $module );
                    unset( $menuItems );
                    include( "$module_dir/admin/menubox.php" );
                    if ( isset( $menuItems ) )
                        eZMenuBox::createBox( $module, $module_dir, "admin",
                        $SiteStyle, $menuItems, true, false,
                        "$module_dir/admin/menubox.php", false, true );
                }
            }
        }

        // parse the URI
        $page = "";
    
    
        // send the URI to the right decoder
        $page = "ez" . $url_array[1] . "/admin/datasupplier.php";
        // set the module logo
        $moduleName =& $url_array[1];

        if ( $moduleName == "" )
            $moduleName = "user";


        if ( $PrintableVersion != "enabled" )
        {
            // break the column an draw a horizontal line
            include( "design/admin/separator.php" );
        }

        if ( eZFile::file_exists( $page ) )
        {
            include( $page );
        }
        else
        {
            include( "ezuser/admin/welcome.php" );
        }
    }
    else
    {
        // show the help page

        $helpFile = "ez" . $url_array[2] . "/admin/help/". $Language . "/" . $url_array[3] . "_" . $url_array[4] . ".hlp";

        if ( eZFile::file_exists( $helpFile ) )
        {
            include( $helpFile );
        }
        else
        {
            print( "help file not found" );

        }
    }
    if ( $HelpMode == "enabled" )
    {
        include( "design/admin/help_footer.php" );
    }
    else
    {
        // html footer
        if ( $PrintableVersion == "enabled" )
        {
            include( "design/admin/print_footer.php" );
        }
        else
        {
            include( "design/admin/footer.php" );
        }
    }
}
else
{
    include( "design/admin/loginheader.php" );
    
    if ( $moduleName == "" )
        $moduleName = "user";

    $LoginSeparator = true;

    if ( $REQUEST_URI == "/" )
    {
        $REQUEST_URI = "/user/login";
        $url_array =& explode( "/", $REQUEST_URI );
    }

    // parse the URI
    $page = "";

    // send the URI to the right decoder
    $page = "ezuser/admin/datasupplier.php";

    if ( eZFile::file_exists( $page ) )
    {
        include( $page );
    }

    // html footer
    include( "design/admin/loginfooter.php" );
}


// close the database connection.
$db =& eZDB::globalDatabase();
$db->close();

// flush the buffer cache
ob_end_flush();
?>

