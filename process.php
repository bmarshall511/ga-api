<?php
// Define constants.
define( 'ROOT', dirname( __FILE__ ) );

/**
 * Include the config.
 */
require_once( ROOT . '/config.php' );

/**
 * Include the Google API Client Library for PHP.
 * @link https://github.com/google/google-api-php-client
 */
require_once( ROOT . '/vendor/autoload.php' );

/**
 * Include the GA API class.
 */
require_once( ROOT . '/GA_API.class.php' );

// Initialize the GA API class.
$ga_api = new GA_API( $config );

// Get form parameters.
$args = array(
	'start_date' => isset( $_POST['start_date'] ) ? $_POST['start_date'] : false,
	'end_date' => isset( $_POST['end_date'] ) ? $_POST['end_date'] : false,
	'dimensions' => isset( $_POST['dimensions'] ) ? $_POST['dimensions'] : false,
	'filters' => isset( $_POST['filters'] ) ? $_POST['filters'] : false,
	'metrics' => isset( $_POST['metrics'] ) ? $_POST['metrics'] : false,
);

$results = $ga_api->call( $args );

echo json_encode( $results );