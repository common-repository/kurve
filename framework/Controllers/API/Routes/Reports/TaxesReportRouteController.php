<?php
/**
 * Taxes reports route controller.
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
class TaxesReportRouteController extends ReportsRouteController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		$this->namespace .= '/taxes';
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
		$args = [
			'per_page' => 1000,
			'order'    => 'asc',
			'orderby'  => 'tax_rate_id',
			'after'    => $this->startDate->format( 'Y-m-d H:i:s' ),
			'before'   => $this->endDate->format( 'Y-m-d H:i:s' ),
		];

		$data_store = \WC_Data_Store::load( 'report-taxes' );
		$results    = $data_store->get_data( $args );
		$report_data = apply_filters( 'woocommerce_analytics_taxes_select_query', $results, $args );
		$gg = [];

		foreach ( $report_data->data as $data ) {
			switch ( $this->groupBy ) {
				case 'id':
					$key = $data['tax_rate_id'];
					break;

				case 'label':
					$key = $data['name'];
					break;

				case 'code':
					$key = $data['country'] . '-' . strtoupper( $data['name'] ) . '-' . $data['priority'];
					break;
			}

			$gg[ $key ] = [
				'label'       => $key,
				'orders'      => $data['orders_count'],
				'netTax'      => $data['total_tax'],
				'totalTax'    => $data['total_tax'],
				'cartTax'     => $data['order_tax'],
				'shippingTax' => $data['shipping_tax'],
				'refundedTax' => 0,
			];
		}

		$response = rest_ensure_response( array_values( $gg ) );

		return $response;
	}
}
