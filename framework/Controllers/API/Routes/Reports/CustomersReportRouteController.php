<?php
/**
 * Customers reports route controller.
 *
 * @package Kurve
 */

namespace KRV\Controllers\API\Routes\Reports;

/**
 * Undocumented class
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
class CustomersReportRouteController extends ReportsRouteController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		$this->namespace .= '/customers';
	}


	public function getVIPCustomers()
	{
		$ordersTable = $this->db->prefix . 'wc_order_stats';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					COUNT( os.customer_id ) as ordersCount,
					SUM( os.total_sales ) AS totalLTV

				FROM %1s os
				WHERE
					os.status NOT IN ( 'wc-trash', 'wc-cancelled', 'wc-pending', 'wc-failed' )
					AND os.parent_id = 0
				GROUP BY os.customer_id
				HAVING totalLTV > 250
				ORDER BY NULL
				",
				$ordersTable,
			)
		);

		$returnData = [
			'ordersCount' => 0,
			'totalLTV'    => 0,
			'customers'   => count( $results ),
		];

		foreach ( $results as $result ) {
			$returnData['ordersCount'] += $result->ordersCount;
			$returnData['totalLTV']    += $result->totalLTV;
		}

		$returnData['avgLTV']    = $returnData['totalLTV'] / $returnData['customers'];
		$returnData['avgOrders'] = $returnData['ordersCount'] / $returnData['customers'];

		return $returnData;
	}
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function getRetentionData()
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';
		$all = $this->request['all'];

		if ( $all ) {
			$results = $this->db->get_results(
				$this->db->prepare(
					"SELECT
						SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) AS ordersCount,
						COUNT( DISTINCT cl.customer_id ) as customers,
						GROUP_CONCAT( os.date_created ORDER BY os.date_created DESC ) as orderDates,
						SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) / COUNT( DISTINCT cl.customer_id ) as avgOrder,
						SUM( os.total_sales ) AS grossSpend
					FROM
						%1s cl
					JOIN
						%1s os ON cl.customer_id = os.customer_id
					WHERE 1=1
						AND os.status NOT IN (
							'wc-trash',
							'wc-pending',
							'wc-failed',
							'wc-cancelled',
							'wc-on-hold'
						)
					GROUP BY os.customer_id
					ORDER BY NULL
					",
					$customersTable,
					$ordersTable,
				)
			);
		} else {
			$results = $this->db->get_results(
				$this->db->prepare(
					"SELECT
						SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) AS ordersCount,
						COUNT( DISTINCT cl.customer_id ) as customers,
						GROUP_CONCAT( os.date_created ORDER BY os.date_created DESC ) as orderDates,
						SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) / COUNT( DISTINCT cl.customer_id ) as avgOrder,
						SUM( os.total_sales ) AS grossSpend
					FROM
						%1s cl
					JOIN
						%1s os ON cl.customer_id = os.customer_id
					WHERE 1=1
						AND os.date_created >= %s
						AND cl.date_registered >= %s
						AND cl.date_registered <= %s
						AND os.status NOT IN (
							'wc-trash',
							'wc-pending',
							'wc-failed',
							'wc-cancelled',
							'wc-on-hold'
						)
					GROUP BY os.customer_id
					ORDER BY NULL
					",
					$customersTable,
					$ordersTable,
					$this->startDate->format( 'Y-m-d H:i:s' ),
					$this->startDate->format( 'Y-m-d H:i:s' ),
					$this->endDate->format( 'Y-m-d H:i:s' ),
				)
			);
		}

		$totals = [
			'customers'         => 0,
			'retentionRate'     => 0,
			'avgOrders'         => 0,
			'maxOrders'         => 0,
			'oneTimeCustomers'  => [
				'orders'     => 0,
				'count'      => 0,
				'grossSpend' => 0,
				'avgGross'   => 0,
			],
			'returnedCustomers' => [
				'orders'     => 0,
				'count'      => 0,
				'grossSpend' => 0,
				'avgGross'   => 0,
			],
		];

		$ss = [];

		foreach ( $results as $result ) {
			$key = (int) $result->ordersCount;

			// Set Totals data.
			if ( 1 === $key ) {
				$totals['oneTimeCustomers']['count']      += $result->customers;
				$totals['oneTimeCustomers']['orders']     += $result->ordersCount;
				$totals['oneTimeCustomers']['grossSpend'] += $result->grossSpend;
				$totals['oneTimeCustomers']['avgGross']    = round( $totals['oneTimeCustomers']['grossSpend'] / $totals['oneTimeCustomers']['orders'] );
			} elseif ( $key > 1 ) {
				$totals['returnedCustomers']['count']      += $result->customers;
				$totals['returnedCustomers']['orders']     += $result->ordersCount;
				$totals['returnedCustomers']['grossSpend'] += $result->grossSpend;
				$totals['returnedCustomers']['avgGross']    = round( $totals['returnedCustomers']['grossSpend'] / $totals['returnedCustomers']['orders'] );
			}

			$totals['avgOrders']     = floor( ( $totals['oneTimeCustomers']['orders'] + $totals['returnedCustomers']['orders'] ) / ( $totals['oneTimeCustomers']['count'] + $totals['returnedCustomers']['count'] ) );
			$totals['customers']    += $result->customers;
			$totals['retentionRate'] = round( $totals['returnedCustomers']['count'] / $totals['customers'] * 100, 2 );

			// Set chart interval data.
			if ( $key >= 7 ) {
				$key = '7+';
			}

			if ( array_key_exists( $key, $ss ) ) {
				$ss[ $key ]['ordersCount']  += $result->ordersCount;
				$ss[ $key ]['customers']    += $result->customers;
				$ss[ $key ]['grossSpend']   += $result->grossSpend;
				$ss[ $key ]['avgGrossSpend'] += round( $ss[ $key ]['grossSpend'] / $ss[ $key ]['ordersCount'], 2 );
			} else {
				$ss[ $key ] = [
					'label'         => $key,
					'ordersCount'   => $result->ordersCount,
					'customers'     => $result->customers,
					'grossSpend'    => $result->grossSpend,
					'avgGrossSpend' => round( $result->grossSpend / $result->ordersCount, 2 ),
				];
			}
		}

		$totals['maxOrders'] = max( array_column( $results, 'ordersCount' ) );

		ksort( $ss );
		$ss = array_values( $ss );

		$itemsDistribution = $this->getItemsDistribution();

		$returnData = [
			'intervals' => $ss,
			'totals'    => $totals,
			'items'     => $itemsDistribution,
		];

		return $returnData;
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function getTimeDiff()
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) AS ordersCount,
					COUNT( DISTINCT cl.customer_id ) AS customers,
					GROUP_CONCAT( os.date_created ORDER BY os.date_created DESC ) as orderDates
				FROM
					%1s cl
				LEFT JOIN
					%1s os ON cl.customer_id = os.customer_id
				WHERE 1=1
					AND cl.date_registered >= %s
					AND cl.date_registered <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					AND os.parent_id = 0
				GROUP BY cl.customer_id
				HAVING ordersCount > 2
				",
				$customersTable,
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		$weeksIntervals = [];
		$allr = [];
		foreach ( $results as $result ) {
			// Get time difference between orders.
			$orderDates          = explode( ',', $result->orderDates );
			$latestOrderDate     = new \DateTime( $orderDates[0] );
			$nextLatestOrderDate = new \DateTime( $orderDates[1] );
			$timeDifference      = $latestOrderDate->diff( $nextLatestOrderDate );
			$weekRange           = (int) floor( $timeDifference->days / 7 );
			$weekRangeKey        = (int) floor( $timeDifference->days / 7 );


			$allr[] = $weekRange;

			if ( $weekRangeKey >= 11 ) {
				$weekRangeKey = '11+';
			}

			if ( array_key_exists( $weekRangeKey, $weeksIntervals ) ) {
				$weeksIntervals[ $weekRangeKey ]['customers'] += 1;
			} else {
				$weeksIntervals[ $weekRangeKey ] = [
					'difference' => $weekRangeKey,
					'customers'  => 1,
				];
			}

			//if ( 0 !== $weeksIntervals[ $weekRangeKey ]['difference'] ) {
			//	$stats['avgDiff']   = $weeksIntervals[ $weekRangeKey ]['customers'] / $weeksIntervals[ $weekRangeKey ]['difference'];
			//	$stats['leastDiff'] = 0;
			//	$stats['mostDiff']  = 0;
			//}
		}

		$d = array_unique( $allr );
		$totalxx = count( $d );
		$totalxx = 0 === $totalxx ? 1 : $totalxx;
		$d1 = array_sum( $d );

		$stats = [
			'avg'   => $d1 / $totalxx,
			'least' => 0,
			'most'  => 0,
		];

		ksort( $weeksIntervals );
		$weeksIntervals = array_values( $weeksIntervals );

		$returnData = [
			'intervals' => $weeksIntervals,
			'stats'     => $stats,
		];

		return $returnData;
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function getItemsDistribution() : array
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					os.order_id,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) AS ordersCount,
					COUNT( DISTINCT cl.customer_id ) AS customers,
					SUM( os.num_items_sold ) as items
				FROM
					%1s cl
				LEFT JOIN
					%1s os ON cl.customer_id = os.customer_id
				WHERE 1=1
					AND cl.date_registered >= %s
					AND cl.date_registered <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					AND os.parent_id = 0
				GROUP BY os.customer_id
				",
				$customersTable,
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		$data = [];

		foreach ( $results as $result ) {
			$itemKey = $result->items;

			if ( $itemKey >= 12 ) {
				$itemKey = '12+';
			}

			if ( array_key_exists( $itemKey, $data ) ) {
				$data[ $itemKey ]['count'] += $result->customers;
			} else {
				$data[ $itemKey ] = [
					'count' => $result->customers,
					'items' => $itemKey,
				];
			}
		}

		ksort( $data );

		return array_values( $data );
	}

	public function highSpendingCustomers()
	{
		$days           = abs( $this->request['days'] );
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';
		$currentDate    = new \DateTime( gmdate( 'Y-m-d' ) );
		$checkDate      = $currentDate->modify( "-$days days" );

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) AS ordersCount,
					CONCAT( cl.first_name, ' ', cl.last_name ) as name,
					SUM( os.total_sales ) as grossSales
				FROM
					%1s cl
				LEFT JOIN
					%1s os ON cl.customer_id = os.customer_id
				WHERE 1=1
					AND cl.date_registered >= %s
					AND cl.date_registered <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					AND os.parent_id = 0
				GROUP BY os.customer_id
				HAVING MAX( os.date_created ) <= %s
				ORDER BY grossSales DESC
				LIMIT 3
				",
				$customersTable,
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$checkDate->format( 'Y-m-d H:i:s' ),
			)
		);

		return $results;
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function getStats()
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					DATE_FORMAT( os.date_created, %s ) AS label,
					os.date_created AS registerDate,
					COUNT( DISTINCT cl.customer_id ) AS customers,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) AS ordersCount,
					SUM( os.total_sales ) AS grossSales,
					SUM( os.num_items_sold ) AS items
				FROM
					%1s cl
				JOIN
					%1s os ON cl.customer_id = os.customer_id
				WHERE 1=1
					AND os.status NOT IN ( 'wc-trash', 'wc-pending', 'wc-failed', 'wc-cancelled', 'wc-on-hold' )
					AND os.parent_id = 0
					AND os.date_created >= %s
					AND cl.date_registered >= %s
					AND cl.date_registered <= %s
				GROUP BY label
				",
				$this->sqlLabelFormat,
				$customersTable,
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			),
			OBJECT_K
		);

		$totals = [
			'ordersCount' => 0,
			'customers'   => 0,
			'totalSpend'  => 0,
			'totalItems'  => 0,
		];

		foreach ( $results as $result ) {
			$result->customers = (int) $result->customers;
		}

		$interval = \DateInterval::createFromDateString( '1 ' . $this->interval );
		$period   = new \DatePeriod( $this->startDate, $interval, $this->endDate );
		$labels   = [];
		$returnData = [];

		foreach ( $period as $dt ) {
			$labels[] = [
				'label' => $dt->format( $this->labelFormat ),
				'stamp' => $dt->format( 'Y-m-d H:i:s' ),
			];
		}

		// Fill in remaining data to present correctly on chart.
		foreach ( $labels as $registerDate ) {
			if ( ! isset( $results[ $registerDate['label'] ] ) ) {
				$results[ $registerDate['label'] ] = (object) [
					'label'        => $registerDate['label'],
					'registerDate' => $registerDate['stamp'],
					'ordersCount'  => 0,
					'customers'    => 0,
					'grossSales'   => 0,
					'items'        => 0,
				];
			}

			$totals['customers']   += $results[ $registerDate['label'] ]->customers;
			$totals['ordersCount'] += $results[ $registerDate['label'] ]->ordersCount;
			$totals['totalSpend']  += $results[ $registerDate['label'] ]->grossSales;
			$totals['totalItems']  += $results[ $registerDate['label'] ]->items;
		}

		// Sort data according to date to present correct flow in chart.
		usort(
			$results,
			function( $a, $b ) {
				return strtotime( $a->registerDate ) > strtotime( $b->registerDate );
			}
		);

		$returnData = [
			'totals'    => $totals,
			'intervals' => $results,
		];

		return $returnData;
	}

	public function groupedData()
	{
		if ( strpos( $this->groupBy, 'retention' ) !== false ) {
			return $this->getRetentionData();
		}

		if ( strpos( $this->groupBy, 'timeDiff' ) !== false ) {
			return $this->getTimeDiff();
		}

		if ( strpos( $this->groupBy, 'shipping' ) !== false ) {
			return $this->groupByLocation();
		}

		if ( strpos( $this->groupBy, 'billing' ) !== false ) {
			return $this->groupByLocation();
		}

		if ( strpos( $this->groupBy, 'role' ) !== false ) {
			return $this->groupByRole();
		}

		if ( strpos( $this->groupBy, 'highSpenders' ) !== false ) {
			return $this->highSpendingCustomers();
		}

		if ( strpos( $this->groupBy, 'vip' ) !== false ) {
			return $this->getVIPCustomers();
		}
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
		if ( $this->groupBy ) {
			return $this->groupedData();
		} elseif ( $this->interval ) {
			return $this->getStats();
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function groupByLocation()
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';
		$postMetaTable  = $this->db->prefix . 'postmeta';
		$searchMetaKey  = '_' . $this->groupBy;

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					pm.meta_value as label,
					COUNT( DISTINCT cl.customer_id ) AS customers,
					COUNT( os.order_id ) AS totalOrders,
					SUM( os.total_sales ) AS totalLTV,
					SUM( os.total_sales ) / COUNT( DISTINCT cl.customer_id ) AS averageLTV,
					ROUND( COUNT( os.order_id ) / COUNT( DISTINCT cl.customer_id ), 2 ) AS averageOrders,
					0 as returnRate
				FROM
					%1s cl
				JOIN
					%1s os ON cl.customer_id = os.customer_id
				JOIN
					%1s pm ON pm.post_id = os.order_id
				WHERE 1=1
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					AND os.parent_id = 0
					AND os.date_created >= %s
					AND cl.date_registered >= %s
					AND cl.date_registered <= %s
					AND pm.meta_key = %s
				GROUP BY label
				ORDER BY NULL
				",
				$customersTable,
				$ordersTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$searchMetaKey,
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @todo Fix this logic.
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	private function groupByRole()
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';
		$userMetaTable  = $this->db->prefix . 'usermeta';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					um.meta_value AS label,
					COUNT( DISTINCT cl.customer_id ) AS customers,
					COUNT( os.order_id ) AS totalOrders,
					SUM( os.total_sales ) AS totalLTV,
					SUM( os.total_sales ) / COUNT( DISTINCT cl.customer_id ) AS averageLTV,
					ROUND( COUNT( os.order_id ) / COUNT( DISTINCT cl.customer_id ), 2 ) AS averageOrders,
					0 as returnRate
				FROM
					%1s cl
				RIGHT JOIN
					%1s os ON cl.customer_id = os.customer_id
				JOIN
					%1s um ON cl.user_id = um.user_id
						AND um.meta_key = 'wp_capabilities'
				WHERE 1=1
					AND os.date_created >= %s
					AND cl.date_registered >= %s
					AND cl.date_registered <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					GROUP BY label
					ORDER BY NULL",
				$customersTable,
				$ordersTable,
				$userMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			),
		);

		foreach ( $results as $result ) {
			$result->label = ucfirst( key( unserialize( $result->label ) ) );
		}

		return $results;
	}
}
