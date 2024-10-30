<?php
/**
 * Refunds reports route controller.
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
class RefundsReportRouteController extends ReportsRouteController {
	public function __construct()
	{
		$this->namespace .= '/refunds';
	}

	public function getStats()
	{
		global $wpdb;
		$tblPrefix      = $wpdb->prefix;
		$ordersTable    = $tblPrefix . 'wc_order_stats';

		$ff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				date_format( osx.date_created, %s ) as orderDate,
				date_format( osx.date_created, %s ) as label,
				COUNT( os.order_id ) as ordersCount,
				COUNT( osx.order_id ) as totalOrders,
				ABS( SUM( CASE WHEN os.total_sales < 0 THEN os.total_sales ELSE 0 END ) ) as refunded,
				SUM( osx.total_sales ) as grossSales
				FROM %1s os
				INNER JOIN %1s osx
				ON os.parent_id = osx.order_id
				WHERE osx.date_created >= %s AND osx.date_created <= %s
				AND osx.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY label",
				$this->sqlStampFormat,
				$this->sqlLabelFormat,
				$ordersTable,
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			),
			OBJECT_K
		);

		$totals = [
			'ordersCount' => 0,
			'totalOrders' => 0,
			'grossSales'  => 0,
			'refunded'    => 0,
			'avgRefunded' => 0,
			'avgItems'    => 0,
			'refundRate'  => 0,
			'pctOfSales'  => 0,
		];

		foreach ( $ff as $f ) {
			$f->ordersCount = (int) $f->ordersCount;

			$totals['ordersCount'] += $f->ordersCount;
			$totals['totalOrders'] += $f->totalOrders;
			$totals['refunded']    += $f->refunded;
			$totals['grossSales']  += $f->grossSales;
			$totals['avgRefunded']  = round( $totals['refunded'] / ( $totals['ordersCount'] <= 0 ? 1 : $totals['ordersCount'] ) );
			$totals['avgItems']    += 0;
			$totals['refundRate']   = round( $totals['ordersCount'] / $totals['totalOrders'] * 100, 2 );
			$totals['pctOfSales']   = round( $totals['refunded'] / $totals['grossSales'] * 100, 2 );
		}

		$interval = \DateInterval::createFromDateString( '1 ' . $this->interval );
		$period   = new \DatePeriod( $this->startDate, $interval, $this->endDate );
		$labels   = [];
		$returnData = [];

		foreach ( $period as $dt ) {
			$labels[] = [
				'label' => $dt->format( $this->labelFormat ),
				'stamp' => $dt->format( $this->stampFormat ),
			];
		}

		// Fill in remaining data to present correctly on chart.
		foreach ( $labels as $orderDate ) {
			$key = array_search( $orderDate['stamp'], array_column( $ff, 'orderDate' ), true );

			if ( false === $key ) {
				$ff[ $orderDate['stamp'] ] = (object) [
					'orderDate'   => $orderDate['stamp'],
					'label'       => $orderDate['label'],
					'ordersCount' => 0,
					'refunded'    => 0,
				];
			}
		}

		// Sort data according to date to present correct flow in chart.
		usort(
			$ff,
			function( $a, $b ) {
				$d1 = \DateTime::createFromFormat( $this->stampFormat, $a->orderDate );
				$d2 = \DateTime::createFromFormat( $this->stampFormat, $b->orderDate );

				return $d1->format( 'U' ) > $d2->format( 'U' );
			}
		);

		$returnData = [
			'totals'    => $totals,
			'intervals' => $ff,
		];

		return $returnData;
	}
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @param \WP_REST_Request $request Holds API request data.
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


	public function groupedData()
	{
		// Detect the group type requested.
		if ( strpos( $this->groupBy, 'refundReason' ) !== false ) {
			return $this->groupByReason();
		}

		if ( strpos( $this->groupBy, 'shipping' ) !== false ) {
			return $this->groupByShipping();
		}

		if ( strpos( $this->groupBy, 'billing' ) !== false ) {
			return $this->groupByBilling();
		}

		if ( strpos( $this->groupBy, 'refundTime' ) !== false ) {
			return $this->refundTime();
		}
	}

	protected function groupByReason()
	{
		global $wpdb;

		$tblPrefix      = $wpdb->prefix;
		$ordersTable    = $tblPrefix . 'wc_order_stats';
		$postMetaTable  = $tblPrefix . 'postmeta';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				COUNT( osx.order_id ) as ordersCount,
				pm.meta_value as refundReason,
				ABS( SUM( CASE WHEN osx.net_total IS NULL THEN 0 ELSE osx.net_total END ) ) as refunded,
				ROUND( ABS( SUM( CASE WHEN osx.net_total IS NULL THEN 0 ELSE osx.net_total END ) ) / COUNT( osx.order_id ), 2 ) as avgRefunded
				FROM %1s os
				LEFT JOIN %1s osx ON osx.parent_id = os.order_id
				JOIN %1s pm ON pm.post_id = osx.order_id
				WHERE os.date_created >= %s AND os.date_created <= %s
				AND pm.meta_key = '_refund_reason'
				AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY pm.meta_value",
				$ordersTable,
				$ordersTable,
				$postMetaTable,
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
	 * @param object $reportData
	 * @param string $groupBy
	 * @return void
	 */
	private function groupByBilling()
	{
		global $wpdb;

		$groupBy        = str_replace( 'billing_', '', $this->groupBy );
		$tblPrefix      = $wpdb->prefix;
		$ordersTable    = $tblPrefix . 'wc_order_stats';
		$postMetaTable  = $tblPrefix . 'postmeta';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				COUNT( osx.order_id ) as ordersCount,
				pm.meta_value as %s,
				ABS( SUM( CASE WHEN osx.net_total IS NULL THEN 0 ELSE osx.net_total END ) ) as refunded,
				ROUND( ABS( SUM( CASE WHEN osx.net_total IS NULL THEN 0 ELSE osx.net_total END ) ) / COUNT( osx.order_id ), 2 ) as avgRefunded
				FROM %1s os
				LEFT JOIN %1s osx ON osx.parent_id = os.order_id
				JOIN %1s pm ON pm.post_id = osx.parent_id
				WHERE os.date_created >= %s AND os.date_created <= %s
				AND pm.meta_key = %s
				AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY pm.meta_value",
				$groupBy,
				$ordersTable,
				$ordersTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				'_billing_' . $groupBy,
				$groupBy,
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	private function groupByShipping()
	{
		global $wpdb;

		$groupBy       = str_replace( 'shipping_', '', $this->groupBy );
		$tblPrefix     = $wpdb->prefix;
		$ordersTable   = $tblPrefix . 'wc_order_stats';
		$postMetaTable = $tblPrefix . 'postmeta';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				COUNT( osx.order_id ) as ordersCount,
				pm.meta_value as %s,
				ABS( SUM( CASE WHEN osx.net_total IS NULL THEN 0 ELSE osx.net_total END ) ) as refunded,
				ROUND( ABS( SUM( CASE WHEN osx.net_total IS NULL THEN 0 ELSE osx.net_total END ) ) / COUNT( osx.order_id ), 2 ) as avgRefunded
				FROM %1s os
				LEFT JOIN %1s osx ON osx.parent_id = os.order_id
				JOIN %1s pm ON pm.post_id = osx.parent_id
				WHERE os.date_created >= %s AND os.date_created <= %s
				AND pm.meta_key = %s
				AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				GROUP BY pm.meta_value",
				$groupBy,
				$ordersTable,
				$ordersTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				'_shipping_' . $groupBy,
				$groupBy,
			)
		);
	}

	public function refundTime()
	{
		$ordersTable   = $this->db->prefix . 'wc_order_stats';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					osx.date_created AS orderDate,
					os.date_created AS refundDate,
					COUNT( os.order_id ) as ordersCount
				FROM %1s os
				INNER JOIN
					%1s osx
					ON os.parent_id = osx.order_id
				WHERE 1=1
					AND osx.date_created >= %s
					AND osx.date_created <= %s
					AND osx.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
				GROUP BY orderDate
				",
				$ordersTable,
				$ordersTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		$dd = [];
		$data = [];

		foreach ( $results as $result ) {
			$refundDate = new \DateTime( $result->refundDate );
			$orderDate  = new \DateTime( $result->orderDate );
			$difference = $refundDate->diff( $orderDate );
			$key = $difference->d;

			if ( array_key_exists( $key, $data ) ) {
				$data[ $key ]['count'] += $result->ordersCount;
			} else {
				$data[ $key ] = [
					'label'   => $key,
					'count' => $result->ordersCount,
				];
			}

			//if ( $difference->d > 13 ) {
			//	$dd['week'][] = floor( $difference->d / 7 );
			//}
			//// Maybe use week instead and if week is above 52 then use months.
			//elseif ( $difference->d > 200 ) {
			//	$dd['month'][] = $difference->d;
			//} else {
			//	$dd['day'][] = $difference->d;

			//}
		}

		ksort( $data );

		return array_values( $data );
	}
}
