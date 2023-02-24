<?php

function PostToUrl( $url, $data, $json = true ) {
    if ( $json == true ) {
        $data = json_encode( $data );
    }
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'content-type: application/json' ) );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
    curl_setopt( $ch, CURLOPT_HEADER, 0 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

    $response = curl_exec( $ch );
    return $response;
}

function PostToUrlEvent( $url, $data, $json = true ) {
    if ( $json == true ) {
        $data = json_encode( $data );
    }
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'content-type: application/json' ) );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
    curl_setopt( $ch, CURLOPT_HEADER, 0 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 0 );

    $response = curl_exec( $ch );
    return $response;
}