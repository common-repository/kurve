<?php
/**
 * Orders reports route controller.
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
class OrdersReportRouteController extends ReportsRouteController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		$this->namespace .= '/orders';
	}

	public function getRecentOrders()
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';
		$event          = $this->request['event'];
		$limit          = 'everything' === $event ? 50 : 30;

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					cl.customer_id AS customerID,
					os.order_id as orderID,
					MAX( os.date_created ) as orderDate,
					os.status,
					CONCAT( cl.first_name, ' ', cl.last_name ) as customerName,
					cl.country,
					os.returning_customer as returningCustomer,
					SUM( os.total_sales ) as grossSale
				FROM
					%1s os
				INNER JOIN %1s cl
					ON os.customer_id = cl.customer_id
				WHERE
					os.status NOT IN ( 'wc-trash' )
				GROUP BY %1s
				ORDER BY orderDate DESC
				LIMIT %d
				",
				$ordersTable,
				$customersTable,
				( 'orders' || 'everything' ) === $limit ? 'orderID' : 'customerID',
				$limit,
			)
		);

		foreach ( $results as $key => $result ) {
			$result->orderDate = $this->time_elapsed_string( $result->orderDate );
			$result->status    = ucwords( str_replace( [ 'wc-', '-' ], [ '', ' ' ], $result->status ) );

			if ( 'customers' === $event && '1' === $result->returningCustomer ) {
				unset( $results[ $key ] );
			}

			if ( 'everything' === $event ) {
				$lol = [];
			}
		}

		if ( 'customers' === $event ) {
			$tempArr = array_unique( array_column( $results, 'customerID' ) );
			$results = array_values( array_intersect_key( $results, $tempArr ) );
		}

		return $results;
	}

	// @todo Move it to helpers and make it cleaner.
	public function time_elapsed_string( $datetime, $full = false ) {
		$now = new \DateTime;
		$ago = new \DateTime( $datetime );
		$diff = $now->diff( $ago );

		$diff->w  = floor( $diff->d / 7 );
		$diff->d -= $diff->w * 7;

		$string = [
			'y' => 'year',
			'm' => 'month',
			'w' => 'week',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		];

		foreach ( $string as $k => &$v ) {
			if ( $diff->$k ) {
				$v = $diff->$k . ' ' . $v . ( $diff->$k > 1 ? 's' : '' );
			} else {
				unset( $string[ $k ] );
			}
		}

		if ( ! $full ) $string = array_slice( $string, 0, 1);
		return $string ? implode( ', ', $string ) . ' ago' : 'just now';
	}

	public function getFailedOrders()
	{
		$ordersTable    = $this->db->prefix . 'wc_order_stats';
		$customersTable = $this->db->prefix . 'wc_customer_lookup';

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					date_format( os.date_created, %s ) as orderDate,
					os.order_id as orderID,
					CONCAT( cl.first_name, ' ', cl.last_name ) as customerName,
					SUM( os.total_sales ) as grossSale,
					SUM( os.num_items_sold ) as totalItems
				FROM %1s os
				JOIN %1s cl ON os.customer_id = cl.customer_id
				WHERE 1=1
					AND os.parent_id = 0
					AND os.status = 'wc-failed'
				GROUP BY orderID
				ORDER BY os.date_created DESC
				LIMIT 5
				",
				'%M %e, %Y',
				$ordersTable,
				$customersTable,
			)
		);
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
		$ordersTable  = $this->db->prefix . 'wc_order_stats';
		$couponsTable = $this->db->prefix . 'wc_order_coupon_lookup';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					date_format( os.date_created, %s ) as label,
					os.date_created as orderDate,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as ordersCount,
					SUM( os.net_total ) as netSales,
					SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as grossSales,
					ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as refunds,
					SUM( os.shipping_total ) as shipping,
					SUM( os.net_total ) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as avgNetSales,
					SUM( os.num_items_sold ) as totalItems,
					SUM( c.discount_amount ) as discounts,
					0 as fees,
					SUM( os.tax_total ) as taxes
				FROM %1s os
				LEFT JOIN %1s c
					ON c.order_id = os.order_id
				WHERE 1=1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND os.parent_id = 0
					AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY label
				ORDER BY NULL",
				$this->sqlLabelFormat,
				$ordersTable,
				$couponsTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		$totals = [
			'ordersCount' => 0,
			'netSales'    => 0,
			'discounts'   => 0,
			'fees'        => 0,
			'refunds'     => 0,
			'totalItems'  => 0,
			'grossSales'  => 0,
			'taxes'       => 0,
			'shipping'    => 0,
			'avgNetSales' => 0,
			'avgItems'    => 0,
		];

		$dailySpend  = [];

		foreach ( $results as $result ) {
			$result->totalItems = (int) $result->totalItems;

			$totals['ordersCount'] += $result->ordersCount;
			$totals['netSales']    += $result->netSales;
			$totals['taxes']       += $result->taxes;
			$totals['totalItems']  += $result->totalItems;
			$totals['grossSales']  += $result->grossSales;
			$totals['shipping']    += $result->shipping;
			$totals['discounts']   += $result->discounts;
			$totals['refunds']     += $result->refunds;
			$totals['fees']        += $result->fees;
			$totals['avgNetSales']  = $totals['netSales'] / $totals['ordersCount'];
			$totals['avgItems']     = round( $totals['totalItems'] / $totals['ordersCount'] );

			$dd = new \DateTime( $result->orderDate );
			$dayKey  = $dd->format( 'w' );
			$dayName = $dd->format( 'l' );

			// Prepare "Daily Spend" data for distribution report.
			if ( array_key_exists( $dayKey, $dailySpend ) ) {
				$dailySpend[ $dayKey ]['ordersCount'] += $result->ordersCount;
				$dailySpend[ $dayKey ]['grossSales']  += $result->grossSales;
			} else {
				$dailySpend[ $dayKey ] = [
					'day'         => $dayName,
					'ordersCount' => $result->ordersCount,
					'grossSales'  => $result->grossSales,
				];
			}
		}

		// Get all week day names.
		for ( $i = 0; $i < 7; $i++ ) {
			$days[] = (object) [
				'key'  => $i + 1, // Hack week day index to "start" with Monday.
				'name' => strftime( '%A', strtotime( "last Monday +$i day" ) ),
			];
		}

		foreach ( $days as $day ) {
			if ( ! isset( $dailySpend[ $day->key ] ) ) {
				$dailySpend[ $day->key ] = [
					'day'         => $day->name,
					'ordersCount' => 0,
					'grossSales'  => 0,
				];
			}
		}

		// Correct ordering of days.
		ksort( $dailySpend );

		$interval = \DateInterval::createFromDateString( '1 ' . $this->interval );
		// @todo Fix this logic.
		$period   = new \DatePeriod( $this->startDate, $interval, 'hour' === $this->interval ? $this->endDate->modify( '+1 hour' ) : $this->endDate );
		$labels   = [];

		foreach ( $period as $dt ) {
			$labels[] = [
				'label' => $dt->format( $this->labelFormat ),
				'stamp' => $dt->format( 'Y-m-d H:i:s' ),
			];
		}

		$cc = $this->endDate->diff( $this->startDate );

		switch ( $this->interval ) {
			case 'day':
				$divideKey = $cc->days;
				break;

			case 'week':
				$divideKey = round( $cc->days / 7, 2 );
				break;

			case 'month':
				$divideKey  = ( $cc->y * 12 ) + $cc->m;
				$divideKey += number_format( $cc->d / 30, 1 );
				break;

			case 'hour':
				// @todo Fix this.
				$userTimezone = new \DateTimeZone( 'America/Vancouver' );
				$_d = new \DateTime( 'now', $userTimezone );
				$_c = $_d->diff( $this->startDate );

				$divideKey = $_c->h;
				break;

			default:
				$divideKey = 1;
		}

		// Let's not break the application.
		$divideKey = 0 === $divideKey ? 1 : $divideKey;

		$intervalBasedData = [
			'net'    => round( $totals['netSales'] / $divideKey, 2 ),
			'gross'  => round( $totals['grossSales'] / $divideKey, 2 ),
			'orders' => round( $totals['ordersCount'] / $divideKey, 2 ),
			'items'  => round( $totals['totalItems'] / $divideKey, 2 ),
		];

		// Fill in remaining data to present correctly on chart.
		foreach ( $labels as $orderDate ) {
			$key = array_search( $orderDate['label'], array_column( $results, 'label' ), true );

			if ( false === $key ) {
				$results[] = (object) [
					'label'       => $orderDate['label'],
					'orderDate'   => $orderDate['stamp'],
					'ordersCount' => 0,
					'netSales'    => 0,
					'discounts'   => 0,
					'refunds'     => 0,
					'fees'        => 0,
					'grossSales'  => 0,
					'taxes'       => 0,
					'totalItems'  => 0,
					'shipping'    => 0,
				];
			}
		}

		// Sort data according to date to present correct flow in chart.
		usort(
			$results,
			function( $a, $b ) {
				return $a->orderDate <=> $b->orderDate;
			}
		);

		$hourlySpend = $this->groupByHourlySpend();
		$itemCount   = $this->groupByItemCount();

		$returnData = [
			'totals'      => $totals,
			'intervals'   => $results,
			'itemCount'   => $itemCount['itemCount'],
			'orderValue'  => $itemCount['orderValue'],
			'dailySpend'  => array_values( $dailySpend ),
			'hourlySpend' => $hourlySpend,
			'intData'     => $intervalBasedData,
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
	public function groupByHourlySpend()
	{
		$ordersTable = $this->db->prefix . 'wc_order_stats';

		$ff = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					date_format( os.date_created, %s ) as label,
					os.date_created as orderDate,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as ordersCount,
					SUM( os.net_total ) as netSales,
					SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as grossSales,
					ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as refunds,
					SUM( os.shipping_total ) as shipping,
					SUM( os.net_total ) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as avgNetSales,
					SUM( os.num_items_sold ) as totalItems,
					SUM( os.tax_total ) as taxes
				FROM %1s os
				WHERE os.date_created >= %s AND os.date_created <= %s
				AND os.parent_id = 0
				AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY label",
				'%l %p',
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			),
			OBJECT_K
		);

		$hourlySpend = [];

		foreach ( $ff as $f ) {
			$dd = new \DateTime( $f->orderDate );
			$hourKey = $dd->format( 'g A' );

			// Prepare "Hourly Spend" data for distribution report.
			if ( array_key_exists( $hourKey, $hourlySpend ) ) {
				$hourlySpend[ $hourKey ]['ordersCount'] += $f->ordersCount;
				$hourlySpend[ $hourKey ]['grossSales']  += $f->grossSales;
			} else {
				$hourlySpend[ $hourKey ] = [
					'label'       => $hourKey,
					'ordersCount' => $f->ordersCount,
					'grossSales'  => $f->grossSales,
				];
			}
		}

		// Get all hours in a day.
		for ( $i = 0; $i <= 24; $i++ ) {
			$stamp = strtotime( "$i:00" ) + ( 0 !== $i ?? 60 * 60 );
			$hour  = gmdate( 'g A', $stamp );

			$hours[] = [
				'stamp' => $stamp,
				'label' => $hour,
			];
		}

		// Fill in remaining data to present correctly on chart.
		foreach ( $hours as $hour ) {
			if ( ! isset( $hourlySpend[ $hour['label'] ] ) ) {
				$hourlySpend[ $hour['label'] ] = [
					'label'       => $hour['label'],
					'ordersCount' => 0,
					'grossSales'  => 0,
				];
			}
		}

		// Sort data according to date to present correct flow in chart.
		usort(
			$hourlySpend,
			function( $a, $b ) {
				return strtotime( $a['label'] ) > strtotime( $b['label'] );
			}
		);

		return $hourlySpend;
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function groupByItemCount()
	{
		$ordersTable = $this->db->prefix . 'wc_order_stats';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					os.order_id AS orderID,
					os.date_created as orderDate,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as ordersCount,
					SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as grossSales,
					SUM( os.num_items_sold ) as items
				FROM %1s os
				WHERE 1=1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND os.parent_id = 0
					AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY orderID
				ORDER BY NULL",
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		$itemCount  = [];
		$orderValue = [];

		$grossSaleValues = array_column( $results, 'grossSales' );
		$maxOrderValue   = floor( max( $grossSaleValues ) );
		$groupIntervals  = floor( $maxOrderValue / 15 );

		if ( $groupIntervals > 6 ) {
			$l = 0;
			$r = $groupIntervals;
			for ( $i = 0; $i < 15; $i++ ) {
				$key = "$l-$r";

				$orderValue[ $key ] = [
					'count' => 0,
					'min' => (int) $l,
					'max' => (int) $r,
				];

				$l += $groupIntervals + 1;
				$r += $groupIntervals + 1;
			}
		}

		foreach ( $results as $result ) {
			$itemKey = $result->items;

			if ( array_key_exists( $itemKey, $itemCount ) ) {
				$itemCount[ $itemKey ]['ordersCount'] += $result->ordersCount;
			} else {
				$itemCount[ $itemKey ] = [
					'ordersCount' => $result->ordersCount,
					'label'       => number_format( $itemKey, 2 ),
				];
			}

			if ( $groupIntervals > 6 ) {
				foreach ( $orderValue as $rn => $d ) {

					if ( ( $d['min'] <= (int) $result->grossSales ) && ( (int) $result->grossSales <= $d['max'] ) ) {
						if ( isset( $orderValue[ $rn ] ) ) {
							$orderValue[ $rn ]['count'] += 1;
						}

						break;
					}
				}
			}
		}

		ksort( $itemCount );

		return [
			'itemCount'  => array_values( $itemCount ),
			'orderValue' => $orderValue,
		];
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
	 * @return array|mixed Response data.
	 */
	public function groupedData()
	{
		// Detect the group type requested.
		if ( strpos( $this->groupBy, 'billing' ) !== false
			|| strpos( $this->groupBy, 'shipping' ) !== false
			|| strpos( $this->groupBy, 'payment' ) !== false
		) {
			return $this->gg();
		}

		if ( strpos( $this->groupBy, 'customersType' ) !== false ) {
			return $this->groupByCustomersType();
		}

		if ( strpos( $this->groupBy, 'ordersStatus' ) !== false ) {
			return $this->groupByOrdersStatus();
		}

		if ( strpos( $this->groupBy, 'failed' ) !== false ) {
			return $this->getFailedOrders();
		}

		if ( strpos( $this->groupBy, 'recent' ) !== false ) {
			return $this->getRecentOrders();
		}
	}

	/**
	 *
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function groupByPayment()
	{
		$ordersTable   = $this->db->prefix . 'wc_order_stats';
		$postMetaTable = $this->db->prefix . 'postmeta';
		$searchMetaKey = '_' . $this->groupBy;

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					CASE WHEN pm.meta_value IS NULL THEN 'Other' ELSE pm.meta_value END as value,
					os.date_created as orderDate,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as ordersCount,
					SUM( os.net_total ) as netSales,
					SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as grossSales,
					ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as refunds,
					SUM( os.shipping_total ) as shipping,
					SUM( os.net_total ) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as avgNetSales,
					SUM( os.num_items_sold ) as totalItems,
					SUM( os.tax_total ) as taxes
				FROM %1s os
				LEFT JOIN %1s pm
					ON pm.post_id = os.order_id
					AND pm.meta_key = %s
				WHERE 1=1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND os.parent_id = 0
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
				GROUP BY value
				ORDER BY ordersCount DESC",
				$ordersTable,
				$postMetaTable,
				$searchMetaKey,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	public function gg()
	{
		$ordersTable   = $this->db->prefix . 'wc_order_stats';
		$postMetaTable = $this->db->prefix . 'postmeta';
		$searchMetaKey = '_' . $this->groupBy;

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					CASE WHEN pm.meta_value IS NULL THEN 'Other' ELSE pm.meta_value END as label,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as orders,
					SUM( os.num_items_sold ) as items,
					SUM( os.net_total ) as netSales,
					SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as grossSales,
					ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as refunds,
					SUM( os.shipping_total ) as shipping,
					SUM( os.net_total ) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as averageNet,
					SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as averageGross,
					SUM( os.tax_total ) as taxes
				FROM %1s os
				LEFT JOIN %1s pm
					ON pm.post_id = os.order_id
					AND pm.meta_key = %s
				WHERE 1=1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND os.parent_id = 0
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
				GROUP BY label
				ORDER BY NULL",
				$ordersTable,
				$postMetaTable,
				$searchMetaKey,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	private function groupByCustomersType()
	{
		global $wpdb;
		$tblPrefix      = $wpdb->prefix;
		$ordersTable    = $tblPrefix . 'wc_order_stats';
		$customersTable = $tblPrefix . 'wc_customer_lookup';

		$ff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				date_format( os.date_created, %s ) as orderDate,
				cl.date_registered as registerDate,
				cl.customer_id as customerID,
				SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as ordersCount,
				SUM( os.net_total ) as netSales,
				SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as grossSales,
				ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as refunds,
				SUM( os.shipping_total ) as shipping,
				SUM( os.net_total ) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as avgNetSales,
				SUM( CASE WHEN cl.date_registered >= %s OR cl.date_registered IS NULL THEN 1 ELSE 0 END ) as newCustomers,
				SUM( CASE WHEN cl.date_registered < %s THEN 1 ELSE 0 END ) as oldCustomers
				FROM %1s os
				RIGHT JOIN %1s cl ON os.customer_id = cl.customer_id
				WHERE os.date_created >= %s AND os.date_created <= %s
				AND os.parent_id = 0
				AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY os.order_id",
				$this->sqlDateFormat,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$ordersTable,
				$customersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			),
		);
		$dd = [
			'new' => [
				'type' => 'New',
				'customers' => 0,
				'ordersCount' => 0,
				'netSales' => 0,
				'avgNet' => 0,
				'grossSales' => 0,
				'avgGross' => 0,
			],
			'returning' => [
				'type' => 'Returning',
				'customers' => 0,
				'ordersCount' => 0,
				'netSales' => 0,
				'avgNet' => 0,
				'grossSales' => 0,
				'avgGross' => 0,
			],
		];

		foreach ($ff as $f ) {
			if ( $f->newCustomers > 0 ) {
				$dd['new']['customers'] += $f->newCustomers;
				$dd['new']['ordersCount'] += $f->ordersCount;
				$dd['new']['netSales'] += $f->netSales;
				$dd['new']['avgNet']  = round( $dd['new']['netSales'] / $dd['new']['ordersCount'], 2 );
				$dd['new']['grossSales'] += $f->grossSales;
				$dd['new']['avgGross'] = round( $dd['new']['grossSales'] / $dd['new']['ordersCount'], 2 );
			}
			if ( $f->oldCustomers > 0 ) {
				$dd['returning']['customers'] += $f->oldCustomers;
				$dd['returning']['ordersCount'] += $f->ordersCount;
				$dd['returning']['netSales'] += $f->netSales;
				$dd['returning']['avgNet'] = round( $dd['returning']['netSales'] / $dd['returning']['ordersCount'], 2 );
				$dd['returning']['grossSales'] += $f->grossSales;
				$dd['returning']['avgGross'] = round( $dd['returning']['grossSales'] / $dd['returning']['ordersCount'], 2 );
			}
		}

		return $dd;
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	private function groupByOrdersStatus()
	{
		$ordersTable   = $this->db->prefix . 'wc_order_stats';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					os.status as label,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as orders,
					SUM(os.net_total) as netSales,
					SUM(os.total_sales) as grossSales,
					SUM(os.total_sales) / SUM( CASE WHEN os.total_sales = 0 THEN 0 ELSE 1 END ) as averageGross,
					SUM(os.num_items_sold ) as items,
					SUM(os.net_total) / SUM( CASE WHEN os.total_sales = 0 THEN 0 ELSE 1 END ) as averageNet
				FROM %1s as os
				WHERE
					os.date_created >= %s
					AND os.date_created <= %s
					AND os.parent_id = 0
					AND os.status NOT IN ( 'wc-trash', 'wc-failed', 'wc-cancelled' )
				GROUP BY label
				",
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		foreach ( $results as $result ) {
			$result->label = ucwords( str_replace( [ 'wc-', '-' ], [ '', ' ' ], $result->label ) );
		}

		return $results;
	}
}
