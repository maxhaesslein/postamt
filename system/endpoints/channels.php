<?php

if( ! $postamt ) exit;

$request_type = $postamt->route->get('request_type');

$channels = $postamt->channels->get();

if( $request_type == 'get' ) {

	if( ! is_array($channels) || ! count($channels) ) {
		$postamt->error( 'internal_server_error', 'no channels found', 500 );
	}

	echo json_encode([
		'channels' => $channels
	]);

	exit;

} elseif( $request_type == 'post' ) {

	$name = false;
	if( isset($_REQUEST['name']) ) {
		$name = $_REQUEST['name'];
	}

	$uid = false;
	if( isset($_REQUEST['channel']) ) {
		// update channel
		$uid = $_REQUEST['channel'];

		$method = 'update';

		if( isset($_REQUEST['method']) ) {
			$method = $_REQUEST['method'];
		}

	} else {
		$method = 'create';
	}


	if( $method == 'create' ) {

		// check if channel exists;
		if( $postamt->channels->channel_exists( false, $name ) ) {
			$postamt->error( 'internal_server_error', 'a channel with this name already exists' );
		}

		$channel = $postamt->channels->create_channel( $name );

		if( ! $channel ) {
			$postamt->error( 'internal_server_error', 'could not create channel' );
		}

		echo json_encode($channel);

	} elseif( $method == 'update' ) {
		// TODO

	} elseif( $method == 'delete' ) {

		if( $uid == 'notifications' ) {
			$postamt->error( 'invalid_request', 'notifications channel cannot be deleted' );
		}

		if( ! $postamt->channels->channel_exists( $uid ) ) {
			$postamt->error( 'invalid_request', 'this channel does not exist' );
		}

		if( ! $postamt->channels->delete_channel( $uid ) ) {
			$postamt->error( 'internal_server_error', 'could not delete channel' );
		}

		exit;

	} else {
		$postamt->error( 'invalid_request', 'invalid method' );
	}

	exit;

}