<?php

$abspath = realpath(dirname(__FILE__)).'/';
$abspath = preg_replace( '/system\/$/', '', $abspath );


if( ! file_exists($abspath.'config.php') || ! file_exists($abspath.'.htaccess') ) {
	echo 'error, invalid config';
	exit;
}


include_once( $abspath.'system/functions.php' );
include_once( $abspath.'system/classes.php' );

$postamt = new Postamt( true );

if( empty($_GET['secret']) ) {
	$postamt->error( 'invalid_request', 'missing secret', 400, false );
}

$secret = $_GET['secret'];

$secret_option = $postamt->config->get('cron_secret');

if( $secret != $secret_option ) {
	$postamt->error( 'invalid_request', 'invalid secret', 400, false );
	exit;
}



$active_feeds = []; // NOTE: these are all the active feeds of _all_ users on this system


// TODO: add a filter parameter, to only refresh a specific user
$userfolders_obj = new Folder( $postamt->abspath.'content/' );
$userfolders = $userfolders_obj->get_subfolders();

if( empty($userfolders) ) {
	// no users exist yet
	return false;
}


foreach( $userfolders as $userfolder ) {

	// TODO: check, when the last user login was; if it was a long time, reduce the frequency of updates

	$channels_obj = new Channels( $postamt, $userfolder['path'] );

	$active_feeds = array_merge( $active_feeds, $channels_obj->get_active_feeds() );

}


refresh_feed_items( $active_feeds );


exit;
