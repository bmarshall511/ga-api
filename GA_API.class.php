<?php
class GA_API {
	var $config = array(
		'app_name'             => '',
		'email'                => '',
		'client_id'            => '',
		'private_key_filename' => '',
		'view_id'              => ''
	);

	var $client;
	var $analytics;
	var $analytics_id;

	public function __construct( $config ) {
		$this->config = array_merge( $this->config, $config );

		$this->client = new Google_Client();
		$this->client->setApplicationName( $this->config['app_name'] );
		$this->client->setAssertionCredentials(
			new Google_Auth_AssertionCredentials(
				$this->config['email'],
				array('https://www.googleapis.com/auth/analytics.readonly'),
				file_get_contents( ROOT . '/' . $this->config['private_key_filename'] )
			)
		);


		$this->client->setClientId( $this->config['client_id'] );
		$this->client->setAccessType( 'offline_access' );

		$this->analytics    = new Google_Service_Analytics( $this->client );
		$this->analytics_id = 'ga:' . $this->config['view_id'];
	}

	public function call( $args ) {
		$result = array();

		try {
			$param       = array();
			$start_date  = isset( $args['start_date'] ) ? $args['start_date'] : false;
			$end_date    = isset( $args['end_date'] ) ? $args['end_date'] : false;

			if( isset( $args['dimensions'] ) ) {
				$param['dimensions']  = isset( $args['dimensions'] ) ? $args['dimensions'] : false;
			}

			if( isset( $args['sort'] ) ) {
				$param['sort'] = isset( $args['sort'] ) ? $args['sort'] : false;
			}

			$param['max-results'] = isset( $args['max_results'] ) ? $args['max_results'] : 1000;

			if( isset( $args['filters'] ) ) {
				$param['filters'] = isset( $args['filters'] ) ? $args['filters'] : false;
			}

			$metrics = isset( $args['metrics'] ) ? $args['metrics'] : false;

			// If using custom dimensions and date range exceeds 7 days, build a
			// date array to perform multiple API calls to avoid data sampling.
			$diff = intval( strtotime( $start_date ) - strtotime( $end_date ) ) * -1;
			if( isset( $param['dimensions'] ) && floor( $diff / ( 60 * 60 * 24 ) ) > 7 ) {
				$date_range = $this->_date_range( $start_date, $end_date );

				$cnt = 1;
				foreach( $date_range as $key => $date ) {
					if( 1 == $cnt ) {
						$start_date = $date;
					} elseif( $cnt == 7 ) {
						$end_date = $date;
						$cnt = 0;

						$result[] = $this->_call( $start_date, $end_date, $metrics, $param );
					}
					$cnt++;
				}

				if( count( $result ) > 1 ) {
					$array = array();
					$cnt=0;
					foreach( $result as $key => $ary ) {
						foreach( $ary as $k => $a ) {
							$array[] = $a;
						}
					}

					$result = $array;
				}
			} else {
				$result = $this->_call( $start_date, $end_date, $metrics, $param );
			}
		} catch( Exception $e ) {
			echo 'There was an error : - ' . $e->getMessage();
		}

		return $result;
	}

	private function _call( $start_date, $end_date, $metrics, $param ) {
		$result = $this->analytics->data_ga->get( $this->analytics_id,
		          date( 'Y-m-d', strtotime( $start_date ) ),
		          date( 'Y-m-d', strtotime( $end_date ) ),
		          $metrics, $param );
		if( $result->containsSampledData ) {
			return 'Sampled data returned. Please narrow down the date range to avoid data sampling.';
		} else {
			if( $result->getRows() ) {
				$result = $result->getRows();
			}
		}

		return $result;
	}

	private function _date_range( $start, $end ) {
		$range = array();

		if( is_string( $start ) === true ) $start = strtotime( $start );
		if( is_string( $end ) === true ) $end = strtotime( $end );

		if( $start > $end ) return $this->_date_range( $end, $start );

		do {
			$range[] = date( 'Y-m-d', $start );
			$start = strtotime( '+ 1 day', $start );
		}
		while( $start < $end );

		return $range;
	}
}
