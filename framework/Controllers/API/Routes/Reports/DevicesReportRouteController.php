<?php
/**
 * Devices reports route controller.
 *
 * @package Kurve
 */

namespace KRV\Controllers\API\Routes\Reports;

use \UAParser\Parser;

/**
 * Undocumented class
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
class DevicesReportRouteController extends ReportsRouteController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		$this->namespace .= '/devices';
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return json
	 */
	public function getData()
	{
		$ordersTable   = $this->db->prefix . 'wc_order_stats';
		$postMetaTable = $this->db->prefix . 'postmeta';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
				    os.date_created as orderDate,
					SUM( CASE WHEN os.total_sales > 0 THEN os.total_sales ELSE 0 END )  as grossSales,
					pm.meta_value as ua
				FROM
					%1s os
				JOIN
					%1s pm ON pm.post_id = os.order_id
				WHERE 1=1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					AND pm.meta_key = '_customer_user_agent'
				GROUP BY orderDate, ua
				",
				$ordersTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			),
			OBJECT_K
		);

		$os = [];
		$browsers = [];
		$devices  = [
			'Desktop' => [
				'ordersCount' => 0,
				'grossSales'  => 0,
			],
			'Mobile'  => [
				'ordersCount' => 0,
				'grossSales'  => 0,
			],
		];

		$parser = Parser::create();
		foreach ( $results as $result ) {
			$userAgent   = $parser->parse( $result->ua );
			$osType      = $userAgent->os->family;
			$browserType = trim( str_replace( 'Mobile', '', $userAgent->ua->family ) );
			$deviceType  = strpos( $userAgent->ua->family, 'Mobile' ) !== false ? 'Mobile' : 'Desktop';

			// Group OS data.
			if ( array_key_exists( $osType, $os ) ) {
				$os[ $osType ]['ordersCount'] += 1;
				$os[ $osType ]['grossSales']  += $result->grossSales;
				$os[ $osType ]['avgGross']     = round( $os[ $osType ]['grossSales'] / $os[ $osType ]['ordersCount'], 2 );
			} else {
				$os[ $osType ] = [
					'type'        => $osType,
					'ordersCount' => 1,
					'grossSales'  => $result->grossSales,
					'avgGross'    => $result->grossSales / 1,
				];
			}

			// Group browser data.
			if ( array_key_exists( $browserType, $browsers ) ) {
				$browsers[ $browserType ]['ordersCount'] += 1;
				$browsers[ $browserType ]['grossSales']  += $result->grossSales;
				$browsers[ $browserType ]['avgGross']     = round( $browsers[ $browserType ]['grossSales'] / $browsers[ $browserType ]['ordersCount'], 2 );
			} else {
				$browsers[ $browserType ] = [
					'type'        => $browserType,
					'ordersCount' => 1,
					'grossSales'  => $result->grossSales,
					'avgGross'    => $result->grossSales / 1,
				];
			}

			// Group device data.
			if ( array_key_exists( $deviceType, $devices ) ) {
				$devices[ $deviceType ]['ordersCount'] += 1;
				$devices[ $deviceType ]['grossSales']  += $result->grossSales;
				$devices[ $deviceType ]['avgGross']     = round( $devices[ $deviceType ]['grossSales'] / $devices[ $deviceType ]['ordersCount'], 2 );
			} else {
				$devices[ $deviceType ] = [
					'type'        => $deviceType,
					'ordersCount' => 1,
					'grossSales'  => $result->grossSales,
					'avgGross'    => $result->grossSales / 1,
				];
			}
		}

		return [
			'devices'  => $devices,
			'os'       => array_values( $os ),
			'browsers' => array_values( $browsers ),
		];
	}
}
