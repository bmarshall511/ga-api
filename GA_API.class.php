<?php
/**
 * Include the Google API Client Library for PHP.
 * @link https://github.com/google/google-api-php-client
 */
require_once( ROOT . '/lib/vendor/autoload.php' );

class GA_API {
	var $analytics;
	var $config;
	var $return = array(
		'init_errors' => array(),
		'hard_errors' => array(),
		'soft_errors' => array(),
		'queries'     => array()
	);

	public function __construct( $config ) {
		$this->config = $config;

		$client = new Google_Client();
	    $client->setApplicationName( $this->config['app_name'] );
	    $client->setAssertionCredentials(
	      new Google_Auth_AssertionCredentials(
	        $this->config['email'],
	        array('https://www.googleapis.com/auth/analytics.readonly'),
	        file_get_contents( $this->config['private_key_file'] )
	      )
	    );

	    $client->setClientId( $this->config['client_id'] );
	    $client->setAccessType( 'offline_access' );

	    $this->analytics = new Google_Service_Analytics( $client );
	}

	public function call( $args ) {
		$param = array();

		$start_date = isset( $args['start_date'] ) ? $args['start_date'] : false;
		$end_date   = isset( $args['end_date'] ) ? $args['end_date'] : false;
		$metrics    = isset( $args['metrics'] ) ? $args['metrics'] : false;

		if( isset( $args['dimensions'] ) ) $param['dimensions'] = isset( $args['dimensions'] ) ? $args['dimensions'] : false;
		if( isset( $args['sort'] ) ) $param['sort'] = isset( $args['sort'] ) ? $args['sort'] : false;
		if( isset( $args['filters'] ) ) $param['filters'] = isset( $args['filters'] ) ? $args['filters'] : false;

		$param['max-results'] = isset( $args['max_results'] ) ? $args['max_results'] : 1000;

		if( ! $start_date ) $this->return['init_errors'] = 'Missing start date.';
		if( ! $end_date ) $this->return['init_errors'] = 'Missing end date.';
		if( ! $metrics ) $this->return['init_errors'] = 'At least one metric must be defined.';

		if( ! count( $this->return['init_errors'] ) ) {
			// If using custom dimensions and date range exceeds the max_days limit,
      		// build a date array to perform multiple API calls to avoid data sampling.
			$diff = intval( strtotime( $start_date ) - strtotime( $end_date ) ) * -1;
      		$diff = floor( $diff / ( 60 * 60 * 24 ) );

      		// Data sampling is only applied to custom dimensions.
      		if( isset( $param['dimensions'] ) && $diff > $this->config['max_days'] ) {
      			$date_range = $this->_date_range( $start_date, $end_date );

      			$cnt = 1;
		        foreach( $date_range as $key => $date ) {
		          if( 1 == $cnt ) {
		            $start_date = $date;
		          } elseif( $cnt == $this->config['max_days'] ) {
		            $end_date = $date;
		            $cnt      = 0;
		            $time_key = strtotime( $start_date ) . '-' . strtotime( $end_date );

		            $this->return['queries'][$time_key] = $this->_call( $start_date, $end_date, $metrics, $param );
		          }
		          $cnt++;
		        }
      		} else {
      			$time_key = strtotime( $start_date ) . '-' . strtotime( $end_date );
       			$this->return['queries'][$time_key] = $this->_call( $start_date, $end_date, $metrics, $param );
      		}
		}

		return $this->return;
	}

	private function _call( $start_date, $end_date, $metrics, $param ) {
		$return = array(
			'soft_errors' => array(),
			'init_errors' => array(),
			'total_results' => 0,
			'total_metric_results' => array(),
			'data' => array()
		);

		try {
			$result = $this->analytics->data_ga->get( 'ga:' . $this->config['view_id'],
                date( 'Y-m-d', strtotime( $start_date ) ),
                date( 'Y-m-d', strtotime( $end_date ) ),
                $metrics, $param );

			// Check for sampled data.
			if( $result->containsSampledData ) {
				$return['soft_errors'][] = 'Sampled data returned. Please decrease the max_days limit configuration setting until you no longer see this message.';
			}

			$return['total_results'] = $result->totalResults;
			$return['total_metric_results'] = $result->totalsForAllResults;
			if( $result->rows ) $return['data'] = $result->rows;
		} catch( Exception $e ) {
			$return['init_errors'][] = 'There was an error : - ' . $e->getMessage();
		}

		return $return;
	}

	private function _date_range( $start_date, $end_date ) {
		$range = array();

	    if( is_string( $start_date ) === true ) $start_date = strtotime( $start_date );
	    if( is_string( $end_date ) === true ) $end_date = strtotime( $end_date );

	    if( $start_date > $end_date ) return $this->_date_range( $end_date, $start_date );

	    do {
	      $range[] = date( 'Y-m-d', $start_date );
	      $start_date = strtotime( '+ 1 day', $start_date );
	    }
	    while( $start_date < $end_date );

	    return $range;
	}
}
