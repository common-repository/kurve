<?php
/**
 * Products reports route controller.
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
class ProductsReportRouteController extends ReportsRouteController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		$this->namespace .= '/products';
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array|null
	 */
	public function getData()
	{
		// Detect the group type requested.
		if ( strpos( $this->groupBy, 'productID' ) !== false ) {
			return $this->groupByProductID();
		}

		if ( strpos( $this->groupBy, 'variationID' ) !== false ) {
			return $this->groupByVariationID();
		}

		if ( strpos( $this->groupBy, 'category' ) !== false ) {
			return $this->groupByCategory();
		}

		if ( strpos( $this->groupBy, 'all' ) !== false ) {
			return $this->getAllProducts();
		}

		if ( strpos( $this->groupBy, 'v12' ) !== false ) {
			return $this->checkProduct();
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function checkProduct()
	{
		$productID = $this->request['productID'];

		if ( ! $productID || empty( $productID ) ) {
			return;
		}

		$productsLookupTable = $this->db->prefix . 'wc_order_product_lookup';
		$postsTable          = $this->db->prefix . 'posts';

		$results = $this->db->get_results(
			$this->db->prepare(
				'SELECT
					pl.date_created as orderDate,
					date_format( pl.date_created, %s ) as label,
					SUM( pl.product_qty ) as netSold,
					p.post_title as title,
					SUM( pl.product_net_revenue ) as netRevenue
				FROM
					%1s pl
				JOIN %1s p ON p.ID = pl.product_id
				WHERE
					pl.date_created >= %s
					AND pl.date_created <= %s
					AND pl.product_id = %d
				GROUP BY label
				',
				$this->sqlLabelFormat,
				$productsLookupTable,
				$postsTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$productID,
			)
		);

		$product = [
			'id'         => $productID,
			'title'      => wc_get_product( $productID )->name,
			'netSold'    => 0,
			'netRevenue' => 0,
			'views'      => 0,
			'cr'         => 0,
		];

		foreach ( $results as $result ) {
			$product['netSold']    += $result->netSold;
			$product['netRevenue'] += $result->netRevenue;
		}

		$interval = \DateInterval::createFromDateString( '1 ' . $this->interval );
		$period   = new \DatePeriod( $this->startDate, $interval, 'hour' === $this->interval ? $this->endDate->modify( '+1 hour' ) : $this->endDate );
		$labels   = [];
		$returnData = [];

		foreach ( $period as $dt ) {
			$labels[] = [
				'label' => $dt->format( $this->labelFormat ),
				'stamp' => $dt->format( 'Y-m-d H:i:s' ),
			];
		}

		foreach ( $labels as $orderDate ) {
			$key = array_search( $orderDate['label'], array_column( $results, 'label' ), true );

			if ( false === $key ) {
				$results[] = (object) [
					'orderDate'  => $orderDate['stamp'],
					'label'      => $orderDate['label'],
					'netSold'    => 0,
					'netRevenue' => 0,
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

		$returnData = [
			'product'   => $product,
			'intervals' => $results,
		];

		return $returnData;
	}

	/**
	 * Undocumented function
	 *
	 * @todo Add OFFSET + LIMIT to load data.
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function getAllProducts()
	{
		$postsTable    = $this->db->prefix . 'posts';
		$postMetaTable = $this->db->prefix . 'postmeta';

		$productsRes = $this->db->get_results(
			$this->db->prepare(
				"SELECT
				p.ID as value,
				p.post_title as label
				FROM %1s p
				JOIN %1s pm ON pm.post_id = p.ID
				WHERE p.post_type = 'product'
				AND pm.meta_key = '_sku'
				",
				$postsTable,
				$postMetaTable,
			)
		);

		return $productsRes;
	}

	/**
	 * Undocumented function
	 *
	 * @todo Add OFFSET + LIMIT to load data.
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function groupByProductID()
	{
		$productsTable = $this->db->prefix . 'wc_order_product_lookup';
		$postMetaTable = $this->db->prefix . 'postmeta';
		$postsTable    = $this->db->prefix . 'posts';
		$ordersTable   = $this->db->prefix . 'wc_order_stats';
		$limit         = $this->request['limit'];

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					pd.product_id AS productID,
					pp.post_title AS title,
					SUM(pd.product_qty) AS netSold,
					SUM(pd.product_net_revenue) AS netSales,
					pm.meta_value AS sku
				FROM
					%1s os
						JOIN
					%1s pd ON pd.order_id = os.order_id
						JOIN
					%1s pp ON pp.ID = pd.product_id
						JOIN
					%1s pm ON pm.post_id = pd.product_id
				WHERE
					1 = 1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND pd.date_created >= %s
					AND pd.date_created <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					AND pm.meta_key = '_sku'
				GROUP BY productID
				ORDER BY netSold DESC, sku DESC
				LIMIT %d
				",
				$ordersTable,
				$productsTable,
				$postsTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$limit,
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @todo Add OFFSET + LIMIT to load data.
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function groupByVariationID()
	{
		$productsTable = $this->db->prefix . 'wc_order_product_lookup';
		$postMetaTable = $this->db->prefix . 'postmeta';
		$postsTable    = $this->db->prefix . 'posts';
		$ordersTable   = $this->db->prefix . 'wc_order_stats';
		$limit         = $this->request['limit'];

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					pd.variation_id AS productID,
					pp.post_title AS title,
					SUM(pd.product_qty) AS netSold,
					SUM(pd.product_net_revenue) AS netSales,
					pm.meta_value AS sku
				FROM
					%1s os
						JOIN
					%1s pd ON pd.order_id = os.order_id
						JOIN
					%1s pp ON pp.ID = pd.variation_id
						JOIN
					%1s pm ON pm.post_id = pd.variation_id
				WHERE
					1 = 1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND pd.date_created >= %s
					AND pd.date_created <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
					AND pm.meta_key = '_sku'
				GROUP BY productID
				ORDER BY netSold DESC, sku DESC
				LIMIT %d
				",
				$ordersTable,
				$productsTable,
				$postsTable,
				$postMetaTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$limit,
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @todo Add OFFSET + LIMIT to load data.
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function groupByCategory()
	{
		$productsTable     = $this->db->prefix . 'wc_order_product_lookup';
		$ordersTable       = $this->db->prefix . 'wc_order_stats';
		$termRelationTable = $this->db->prefix . 'term_relationships';
		$categoryTable     = $this->db->prefix . 'wc_category_lookup';
		$termsTable        = $this->db->prefix . 'terms';
		$limit             = $this->request['limit'];

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					SUM(pd.product_qty) AS netSold,
					SUM(pd.product_net_revenue) AS netSales,
					qq.name as title
				FROM
					%1s os
					JOIN %1s pd ON pd.order_id = os.order_id
					RIGHT JOIN
					(SELECT
						tr.object_id,
						tr.term_taxonomy_id,
						ts.name
					FROM
						%1s tr
					JOIN %1s ts ON ts.term_id = tr.term_taxonomy_id
					WHERE tr.term_taxonomy_id IN ( SELECT DISTINCT category_id FROM %1s )
					) qq ON qq.object_id = pd.product_id
				WHERE
					1 = 1
					AND os.date_created >= %s
					AND os.date_created <= %s
					AND pd.date_created >= %s
					AND pd.date_created <= %s
					AND os.status NOT IN (
						'wc-trash',
						'wc-pending',
						'wc-failed',
						'wc-cancelled',
						'wc-on-hold'
					)
				GROUP BY title
				ORDER BY netSold DESC
				LIMIT %d",
				$ordersTable,
				$productsTable,
				$termRelationTable,
				$termsTable,
				$categoryTable,
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$this->startDate->format( 'Y-m-d H:i:s' ),
				$this->endDate->format( 'Y-m-d H:i:s' ),
				$limit,
			)
		);
	}
}
