<?php
/**
 * Reports route controller.
 *
 * @package Kurve
 */

namespace KRV\Controllers\API\Routes\Reports;

use KRV\Controllers\API\Routes\RouteInterface;

/**
 * Undocumented class
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
abstract class ReportsRouteController extends \WP_REST_Controller implements RouteInterface {
	/**
	 * WordPress database driver.
	 *
	 * @var object
	 */
	public $db;

	/**
	 * API Endpoint for this route.
	 *
	 * @var string
	 */
	public $namespace = '/reports';

	/**
	 * Undocumented variable
	 *
	 * @var object
	 */
	protected $request;

	/**
	 * Selected date range.
	 *
	 * @var string
	 */
	public $dateRange;

	/**
	 * DateTime object for Start of selected time range.
	 *
	 * @var object
	 */
	public $startDate;

	/**
	 * DateTime object for End of selected time range.
	 *
	 * @var object
	 */
	public $endDate;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	protected $groupBy;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	protected $interval;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	protected $labelFormat;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	protected $stampFormat;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	protected $sqlLabelFormat;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	protected $sqlStampFormat;

	/**
	 * Abstract method to be followed in every child.
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @param \WP_REST_Request $request Holds API request data.
	 * @return object|mixed Response data.
	 */
	public function get( \WP_REST_Request $request )
	{
		$this->db        = $GLOBALS['wpdb'];
		$this->request   = $request;
		$routeController = $this->request->get_attributes()['callback'][0];
		$params          = $this->request->get_params();
		$this->interval  = $params['interval'] ?? null;
		$this->groupBy   = $params['groupBy'] ?? null;

		// Setup requested date range.
		$this->setDateRange( $params['dateRange'] );
		$this->setLabelFormat();

		// Talk to the requested route controller.
		return $routeController->getData();
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	private function setLabelFormat()
	{
		$this->stampFormat    = 'Y-m-d';
		$this->sqlStampFormat = '%Y-%m-%d';

		switch ( $this->interval ) {
			case 'hour':
				$this->labelFormat    = 'M j - g A';
				$this->stampFormat    = 'Y-m-d H';
				$this->sqlLabelFormat = '%b %e - %l %p';
				$this->sqlStampFormat = '%Y-%m-%d %H';
				break;

			case 'week':
				$this->labelFormat    = 'M j';
				$this->sqlLabelFormat = '%b %e';
				break;

			case 'month':
				$this->labelFormat    = 'M \'y';
				$this->stampFormat    = 'Y-m';
				$this->sqlLabelFormat = '%b \'%y';
				$this->sqlStampFormat = '%Y-%m';
				break;

			default:
				$this->labelFormat    = 'M j';
				$this->sqlLabelFormat = '%b %e';
				break;
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @param string $dateRange Selected date range to query.
	 * @return void|json
	 */
	protected function setDateRange( string $dateRange )
	{
		if ( ! $dateRange ) {
			return wp_send_json_error( 'Invalid date range', 400 );
		}

		$timezone        = new \DateTimeZone( $this->getTimezone() );
		$this->dateRange = $dateRange;
		$splitDate       = explode( '...', $this->dateRange );
		$endTime         = 'T23:59:59';
		$this->startDate = new \DateTime( $splitDate[0] . 'T00:00:00', $timezone );
		$this->endDate   = new \DateTime( $splitDate[1] . $endTime, $timezone );

		// When it's a single day, add first hour of the next day.
		if ( $splitDate[0] === $splitDate[1] ) {
			if ( 'hour' === $this->interval ) {
				$this->endDate = new \DateTime( $splitDate[1] . 'T24:00:00', $timezone );
			}
		}

		$interval = \DateInterval::createFromDateString( '1 day' );
		$period   = new \DatePeriod( $this->startDate, $interval, $this->endDate );

		// Count all the intervals from given date-time period.
		$intervalCount = 0;
		foreach ( $period as $_ ) {
			$intervalCount++;
		}

		// Do not show data in hourly interval if the interval is too big. eg, months, years etc.
		if ( $intervalCount >= 32 ) {
			$this->interval = 'hour' === $this->interval ? 'month' : $this->interval;
		}
	}

	/**
	 * Gets saved timezone string from database.
	 * Or sets one if empty.
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return string
	 */
	public function getTimezone()
	{
		$timezone = get_option( 'timezone_string' );

		if ( empty( $timezone ) ) {
			$timezone = date_default_timezone_get();
		}

		return $timezone;
	}
}
