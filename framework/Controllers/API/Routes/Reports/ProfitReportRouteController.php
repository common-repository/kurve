<?php
/**
 * Profit reports route controller.
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
class ProfitReportRouteController extends ReportsRouteController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		$this->namespace .= '/profit';
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
					os.date_created as date,
					date_format( os.date_created, %s ) as label,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) as orders,
					SUM( os.net_total ) + SUM( os.shipping_total ) as netRevenue,
					SUM( os.total_sales ) as grossSales,
					ABS( SUM( CASE WHEN os.net_total < 0 THEN os.net_total ELSE 0 END ) ) as refunds,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) * 22 as shippingCost,
					SUM( ( ( os.total_sales * 2.9 ) / 100 ) + 0.3 ) as transactionCost,
					SUM( 0 ) as advertisingCost,
					SUM( 0 ) as extraCost,
					ROUND( SUM( pm.meta_value ), 2 ) as productCost,
					SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) * 22 + SUM( ( os.total_sales * 2.9 ) / 100 + 0.3 ) + ROUND( SUM( pm.meta_value ), 2 ) as totalCost,
					ROUND( SUM( os.net_total ) + SUM( os.shipping_total ) - ( SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) * 22 + SUM( ( os.total_sales * 2.9 ) / 100 + 0.3 ) + ROUND( SUM( pm.meta_value ), 2 ) ), 2 ) as profit,
					ROUND( SUM( os.net_total ) + SUM( os.shipping_total ) - ( SUM( CASE WHEN os.parent_id = 0 THEN 1 ELSE 0 END ) * 22 + SUM( ( os.total_sales * 2.9 ) / 100 + 0.3 ) + ROUND( SUM( pm.meta_value ), 2 ) ), 2 ) / ( SUM( os.net_total ) + SUM( os.shipping_total ) ) * 100 as margin,
					SUM( os.tax_total ) as taxes
				FROM %1s os
				JOIN %1s pm ON pm.post_id = os.order_id
				WHERE os.date_created >= %s AND os.date_created <= %s
				AND os.status NOT IN ( 'wc-trash','wc-pending','wc-failed','wc-cancelled', 'wc-on-hold' )
				AND pm.meta_key = '_wc_cog_order_total_cost'
				GROUP BY label",
				$this->sqlLabelFormat,
				$ordersTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
			)
		);

		$totals = [
			'orders'          => 0,
			'netRevenue'      => 0,
			'grossSales'      => 0,
			'productCost'     => 0,
			'refunds'         => 0,
			'taxes'           => 0,
			'shippingCost'    => 0,
			'transactionCost' => 0,
			'advertisingCost' => 0,
			'extraCost'       => 0,
			'totalCost'       => 0,
			'profit'          => 0,
			'margin'          => 0,
			'avgMargin'       => 0,
			'avgProfit'       => 0,
		];

		foreach ( $results as $result ) {
			$result->profit = (int) $result->profit;
			$result->margin = (float) $result->margin;

			$totals['orders']          += $result->orders;
			$totals['netRevenue']      += $result->netRevenue;
			$totals['grossSales']      += $result->grossSales;
			$totals['productCost']     += $result->productCost;
			$totals['refunds']         += $result->refunds;
			$totals['taxes']           += $result->taxes;
			$totals['shippingCost']    += $result->shippingCost;
			$totals['transactionCost'] += $result->transactionCost;
			$totals['advertisingCost'] += $result->advertisingCost;
			$totals['extraCost']       += $result->extraCost;
			$totals['totalCost']       += $result->totalCost;
			$totals['profit']          += $result->profit;
			$totals['margin']           = $result->margin;
			$totals['avgProfit']        = round( $totals['profit'] / $totals['orders'], 2 );
			$totals['avgMargin']        = round( $totals['profit'] / $totals['netRevenue'] * 100, 2 );
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
		foreach ( $labels as $date ) {
			$key = array_search( $date['label'], array_column( $results, 'label' ), true );

			if ( false === $key ) {
				$results[] = (object) [
					'date'            => $date['stamp'],
					'label'           => $date['label'],
					'orders'          => 0,
					'netRevenue'      => 0,
					'grossSales'      => 0,
					'refunds'         => 0,
					'productCost'     => 0,
					'taxes'           => 0,
					'shippingCost'    => 0,
					'transactionCost' => 0,
					'advertisingCost' => 0,
					'extraCost'       => 0,
					'totalCost'       => 0,
					'profit'          => 0,
					'margin'          => 0,
					'avgMargin'       => 0,
					'avgProfit'       => 0,
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
}
