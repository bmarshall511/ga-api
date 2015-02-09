<?php
class GA_API {
  var $config = array(
    'app_name'             => '',
    'email'                => '',
    'client_id'            => '',
    'private_key_filename' => '',
    'view_id'              => '',
    'max_days'             => 7,
    'max_calls_sec'        => 10
  );

  var $client;
  var $analytics;
  var $analytics_id;
  var $num_api_calls;

  var $return = array(
    'soft_errors' => 0,
    'init_errors' => 0,
    'hard_errors' => array(),
    'queries'     => array()
  );

  public function __construct( $config, $num_api_calls = 0 ) {
    $this->config        = array_merge( $this->config, $config );
    $this->num_api_calls = $num_api_calls;

    $this->client = new Google_Client();
    $this->client->setApplicationName( $this->config['app_name'] );
    $this->client->setAssertionCredentials(
      new Google_Auth_AssertionCredentials(
        $this->config['email'],
        array('https://www.googleapis.com/auth/analytics.readonly'),
        file_get_contents( $this->config['private_key_filename'] )
      )
    );

    $this->client->setClientId( $this->config['client_id'] );
    $this->client->setAccessType( 'offline_access' );

    $this->analytics    = new Google_Service_Analytics( $this->client );
    $this->analytics_id = 'ga:' . $this->config['view_id'];
  }

  public function call( $args ) {
    $param       = array();
    $start_date  = isset( $args['start_date'] ) ? $args['start_date'] : false;
    $end_date    = isset( $args['end_date'] ) ? $args['end_date'] : false;
    $metrics     = isset( $args['metrics'] ) ? $args['metrics'] : false;

    if( ! $start_date ) {
      $this->return['hard_errors'][] = 'Missing start date.';
    }

    if( ! $end_date ) {
      $this->return['hard_errors'][] = 'Missing end date.';
    }

    if( ! $metrics ) {
      $this->return['hard_errors'][] = 'You must define at least one metric.';
    }

    $param['max-results'] = isset( $args['max_results'] ) ? $args['max_results'] : 1000;

    if( isset( $args['dimensions'] ) ) {
      $param['dimensions']  = isset( $args['dimensions'] ) ? $args['dimensions'] : false;
    }

    if( isset( $args['sort'] ) ) {
      $param['sort'] = isset( $args['sort'] ) ? $args['sort'] : false;
    }

    if( isset( $args['filters'] ) ) {
      $param['filters'] = isset( $args['filters'] ) ? $args['filters'] : false;
    }

    if( ! count( $this->return['hard_errors'] ) ) {
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
      'init_errors' => array(),
      'hard_errors' => array(),
      'soft_errors' => array(),
      'data'        => array()
    );

    if( $this->num_api_calls == $this->config['max_calls_sec'] ) {
      // Max. API calls per second reached, wait 1 second before the next set of
      // calls.
      sleep(1);
    }

    $this->num_api_calls++;

    try {
      $result = $this->analytics->data_ga->get( $this->analytics_id,
                date( 'Y-m-d', strtotime( $start_date ) ),
                date( 'Y-m-d', strtotime( $end_date ) ),
                $metrics, $param );

      if( !$result->containsSampledData ) {
        $this->return['soft_errors']++;
        $return['soft_errors'][] = 'Sampled data returned. Please decrease the max_days limit configuration setting until you no longer see this message.';
      } else {
        if( $result->getRows() ) {
          $this->return['queries'];
          $return['data'] = $result->getRows();
        } else {
          $this->return['soft_errors']++;
          $return['soft_errors'][] = 'No data available.';
        }
      }
    } catch( Exception $e ) {
      $this->return['init_errors']++;
      $return['init_errors'][] = 'There was an error : - ' . $e->getMessage();
    }

    return $return;
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
