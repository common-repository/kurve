<?php
/**
 * Revenue reports route controller.
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
class RevenueReportRouteController extends ReportsRouteController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		$this->namespace .= '/revenue';
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return json
	 */
	public function getStats()
	{
		$ordersStatsTable = $this->db->prefix . 'wc_order_stats';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					os.date_created AS date,
					DATE_FORMAT( os.date_created, %s ) AS label,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) AS orders,
					SUM( CASE WHEN os.net_total > 0 THEN os.net_total ELSE 0 END ) AS netRevenue,
					SUM( os.total_sales ) + ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) AS grossSales,
					-SUM( os.tax_total ) AS taxes,
					-SUM( os.shipping_total ) AS shipping,
					SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) AS refunds,
					-SUM( 0 ) AS fees
				FROM
					%1s os
				WHERE 1=1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND os.parent_id = 0
					AND os.status NOT IN (
						'wc-trash',
						'wc-cancelled',
						'wc-failed',
						'wc-pending',
						'wc-on-hold'
					)
				GROUP BY label
				ORDER BY NULL
				",
				$this->sqlLabelFormat,
				$ordersStatsTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		$totals = [
			'orders'     => 0,
			'netRevenue' => 0,
			'grossSales' => 0,
			'refunds'    => 0,
			'taxes'      => 0,
			'shipping'   => 0,
			'fees'       => 0,
		];

		// Need positive values to represent in front.
		foreach ( $results as $result ) {
			$result->grossSales = (float) $result->grossSales;
			$result->netRevenue = (float) $result->netRevenue;
			$result->orders     = (int) $result->orders;
			$result->refunds    = (float) $result->refunds;

			$totals['orders']     += abs( $result->orders );
			$totals['netRevenue'] += abs( $result->netRevenue );
			$totals['grossSales'] += abs( $result->grossSales );
			$totals['refunds']    += abs( $result->refunds );
			$totals['taxes']      += abs( $result->taxes );
			$totals['shipping']   += abs( $result->shipping );
			$totals['fees']       += abs( $result->fees );
		}

		$interval = \DateInterval::createFromDateString( '1 ' . $this->interval );
		$period   = new \DatePeriod( $this->startDate, $interval, 'hour' === $this->interval ? $this->endDate->modify( '+1 hour' ) : $this->endDate );
		$labels   = [];

		foreach ( $period as $dt ) {
			$labels[] = [
				'label' => $dt->format( $this->labelFormat ),
				'stamp' => $dt->format( 'Y-m-d H:i:s' ),
			];
		}

		// Fill in remaining data to present correctly on chart.
		foreach ( $labels as $orderDate ) {
			$key = array_search( $orderDate['label'], array_column( $results, 'label' ), true );

			if ( false === $key ) {
				$results[] = (object) [
					'date'       => $orderDate['stamp'],
					'label'      => $orderDate['label'],
					'orders'     => 0,
					'netRevenue' => 0,
					'grossSales' => 0,
					'taxes'      => 0,
					'refunds'    => 0,
					'shipping'   => 0,
					'fees'       => 0,
				];
			}
		}

		// Sort data according to date to present correct flow in chart.
		usort(
			$results,
			function( $a, $b ) {
				return $a->date <=> $b->date;
			}
		);

		$returnData = [
			'totals'    => $totals,
			'intervals' => $results,
		];

		return $returnData;
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
	public function groupedData()
	{
		$ordersTable   = $this->db->prefix . 'wc_order_stats';
		$postMetaTable = $this->db->prefix . 'postmeta';
		$searchMetaKey = '_' . $this->groupBy;

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					CASE WHEN pm.meta_value IS NULL THEN 'Other' ELSE pm.meta_value END as label,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as orders,
					SUM( CASE WHEN os.net_total < 0 THEN 0 ELSE os.net_total END) AS netRevenue,
					SUM( CASE WHEN os.total_sales > 0 THEN os.total_sales ELSE 0 END  ) as grossSales,
					SUM( CASE WHEN os.net_total < 0 THEN 0 ELSE os.net_total END) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as averageNet,
					SUM( CASE WHEN os.total_sales > 0 THEN os.total_sales ELSE 0 END  ) / SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) averageGross
				FROM %1s os
				LEFT JOIN %1s pm
					ON pm.post_id = os.order_id
					AND pm.meta_key = %s
				WHERE
					1=1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
				GROUP BY label
				ORDER BY orders DESC",
				$ordersTable,
				$postMetaTable,
				$searchMetaKey,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);
	}
}
