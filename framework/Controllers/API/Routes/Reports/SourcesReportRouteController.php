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
class SourcesReportRouteController extends ReportsRouteController {
	public function __construct()
	{
		$this->namespace .= '/sources';
	}

	public function getData()
	{
		return $this->groupBySource();
	}

	public function groupBySource()
	{
		$ordersTable   = $this->db->prefix . 'wc_order_stats';
		$postMetaTable = $this->db->prefix . 'postmeta';
		$metaKey       = $this->groupBy;
		$_prefix       = 'referer' === $metaKey ? '_metorik_referer' : '_metorik_utm_' . $metaKey;

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					pm.meta_value as source,
					COUNT( os.order_id ) as orders,
					SUM( os.total_sales ) as grossSales,
					ROUND( SUM( os.total_sales ) / COUNT( pm.meta_value ), 2 ) as avgGross
				FROM
					%1s os
				JOIN %1s pm ON pm.post_id = os.order_id
				WHERE
					os.date_created >= %s AND os.date_created <= %s
					AND os.status = 'wc-completed'
					AND pm.meta_key = %s
				GROUP BY pm.meta_value
				ORDER BY orders DESC",
				$ordersTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$_prefix,
			)
		);
	}
}
