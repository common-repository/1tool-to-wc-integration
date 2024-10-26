<?php 
/*
Plugin Name: 1Tool to WooCommerce / Wordpress
Plugin Author: 1Tool
Author: 1Tool
Author URI: https://www.1tool.com/
Description: This plugin provides integration to sync products from 1Tool to WooCommerce and when order is placed, it will be sent to 1tool via API. It also generates Invoice for that order on 1Tool and create that customer there.
Version: 1.1.0
Text Domain: 1tool-to-wc-integration
*/

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'FIRSTTOOL_PLUGIN_FILE' ) ) {
	/**
	 * Path to this plugin directory that can be used to get relative path of
	 * other plugin sections.
	 *
	 * @since 1.0.0
	 * @var string FIRSTTOOL_PLUGIN_FILE
	 */
	define( 'FIRSTTOOL_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'FIRSTTOOL_ENABLE_DEBUG_MODE' ) ) {
	/**
	 * Path to this plugin directory that can be used to get relative path of
	 * other plugin sections.
	 *
	 * @since 1.0.0
	 * @var string FIRSTTOOL_PLUGIN_FILE
	 */
	define( 'FIRSTTOOL_ENABLE_DEBUG_MODE', TRUE );
}

/**
 * Include FirstTool_API Class to use API Operations.
 */
include( "classes/class-firsttool-api.php" );

/**
 * Include FirstTool_Product_Sync Class to use Product Create/Update related operations.
 */
include( "classes/class-firsttool-product-sync.php" );

/**
 * Include functions that are needed for 1Tool order related operations.
 */
include( "1tool-order.php" );

if ( ! function_exists( 'firsttool_enqueue_scripts' ) ):
	/**
	 * Enqueue plugin scripts and styles
	 *
	 * Enqueue 1Tool plugin's scripts and style files that are needed to prepare UI
	 *
	 * @since 1.0.0
	 *
	 */
	function firsttool_enqueue_scripts() {
		wp_enqueue_style( 'firsttool_css', plugins_url( 'assets/css/1tool-admin-style.css', __FILE__ ) );
		wp_enqueue_script( 'firsttool_js', plugins_url( 'assets/js/1tool-admin.js', __FILE__ ), array( "jquery" ), '1.0.0', true);
		wp_localize_script( 'firsttool_js', 'km', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}
endif;
add_action( 'admin_enqueue_scripts', "firsttool_enqueue_scripts" );

if ( ! function_exists( 'firsttool_product_sync' ) ):
	/**
	 * Add Admin Menu Page for 1Tool API Configuration
	 *
	 * This page is used to configure API credentials that are being used for
	 * connecting to 1Tool APIs. It also has feature to sync products manually
	 * from 1Tool account.
	 *
	 * @since 1.0.0
	 *
	 */
	function firsttool_product_sync() {
		add_menu_page(
			__( '1Tool API Settings', '1tool-to-wc-integration' ),
			__( '1Tool API Settings', '1tool-to-wc-integration' ),
			'manage_options',
			'1tool_settings',
			'firsttool_api_setting',
			'dashicons-admin-generic',
		);
	}
endif;
add_action( 'admin_menu', 'firsttool_product_sync' );

if ( ! function_exists( 'firsttool_api_setting' ) ):
	/**
	 * Manages settings form and process it's value to save them in db
	 *
	 * Prepare UI for setting page and save the submitted value in WP Options table
	 * to use them later for API call.
	 *
	 * @since 1.0.0
	 *
	 */
	function firsttool_api_setting() {
	
		if ( isset($_POST['km_submit_btn']) ) {
			$km_applicationSecret = sanitize_text_field( $_POST['km_applicationSecret'] );
			update_option( 'km_applicationSecret', $km_applicationSecret );
		
			$km_applicationId = sanitize_text_field( $_POST['km_applicationId'] );
			update_option( 'km_applicationId', $km_applicationId );
		
			$km_is_create_invoice = isset( $_POST['km_is_create_invoice'] ) ? sanitize_text_field( $_POST['km_is_create_invoice'] ) : "";
			update_option( 'km_is_create_invoice', $km_is_create_invoice );
		
			$km_api = new FIRSTTOOL_API\FirstTool_API;
			/* get accessToken */
			$km_res = $km_api->km_post_request( 'auth/application', array( 'applicationId' => $km_applicationId, 'applicationSecret' => $km_applicationSecret ) );
		
			if ( isset( $km_res["accessToken"] ) ) {
				update_option( 'km_accessToken', $km_res["accessToken"] );
			}
		}
	
		$km_applicationId = get_option( 'km_applicationId' );
		$km_applicationSecret = get_option( 'km_applicationSecret' );
	
		$checked_cat_list = get_option( 'km_checked_category' );
		$is_sync_value = get_option( 'km_is_sync' );
		$km_is_create_invoice = get_option( 'km_is_create_invoice' );
		$syn_frequency_choice = get_option( 'km_sync_frequency' );
		?>
		<div class="km_preloader" style="display:none">
			<img src="<?php echo admin_url( "images/spinner-2x.gif" ); ?>" >
		</div>
		<h2><?php echo __( '1Tool API Settings', '1tool-to-wc-integration' ); ?></h2>
		<?php
		if ( isset( $km_res["error"] ) ) {
			echo wp_kses_post( '<div class="error notice  is-dismissible"><p>' . $km_res["message"] . '</p></div>' );
		} elseif ( isset( $km_res["accessToken"] ) ) {
			echo  wp_kses_post( '<div class="updated  notice  is-dismissible"><p>Saved successfully</p></div>' );
		}
		?>
		<form method="post" >
			<table>
				<tr>
					<td>
						<label for="km_applicationId"><?php echo __( 'API Id:', '1tool-to-wc-integration' ); ?></label>
					</td>
					<td>
						<input type="text" name="km_applicationId" id="km_applicationId" value="<?php echo esc_html( $km_applicationId ); ?>">
					</td>
				</tr>
				<tr>
					<td>
						<label for="km_applicationSecret"><?php echo __( 'Application Secret:', '1tool-to-wc-integration' );?></label>
					</td>
					<td>
						<input type="text" name="km_applicationSecret" id="km_applicationSecret" value="<?php echo esc_html( $km_applicationSecret ); ?>">
					</td>
				</tr>
				<tr>
					<td>
						<label for="km_is_create_invoice"><?php echo __( 'Create invoice?', '1tool-to-wc-integration' ); ?><br><small><?php echo __( 'If enabled invoice will be created by API ', '1tool-to-wc-integration' ); ?></small></label>
					</td>
					<td>
						<input type="checkbox" name="km_is_create_invoice" id="km_is_create_invoice" value="yes" <?php echo checked( 'yes', $km_is_create_invoice, true ); ?> >
					</td>
				</tr>
				<tfoot>
				</tfoot>
			</table>
			<p>
				<input type="submit" name="km_submit_btn" class= " button km_submit_btn" value="Save"/>
			</p>
		</form>
		<hr />
		<p>
			<button type="button" class="button" id="km_sync_countries_btn" > <?php echo  __( 'Sync Countries for Invoice API', '1tool-to-wc-integration' ); ?> </button>
			<br>
			<?php
			if ( ! empty( get_option( 'firsttool_country_ids', array() ) ) ){
			?>
				Country Codes are Synced.
			<?php
			}
			?>
		</p>
		<div id="km_country_message" >
		</div>
		<hr />
		<p>
			<button type="button" class="button" id="km_sync_products_btn" > <?php echo  __( 'Sync Products', '1tool-to-wc-integration' ); ?> </button>
		</p>
		<div id="km_message" >
		</div>
		<?php
	}
endif;

if ( ! function_exists( 'firsttool_sync_products' ) ):
	/**
	 * Process the Porduct Sync operation
	 *
	 * Starts the import of Products from 1Tool and save them to WooCommerce.
	 *
	 * @since 1.0.0
	 *
	 */
	function firsttool_sync_products( $skip = 0 ) {
	
		$km_api = new FIRSTTOOL_API\FirstTool_API;
		$km_pro_sync = new FIRSTTOOL_API\FirstTool_Product_Sync;
	
		$select = 'select=["Product.ProductGroup.id", 
							"Product.ProductGroup.description",
							"Product.id",
							"Product.languageCode",
							"Product.languageParentId",
							"Product.productParentId",
							"Product.inShop",
							"Product.description",
							"Product.text",
							"Product.period",
							"Product.price",
							"Product.parent",
							"Product.link",
							"Product.seo",
							"Product.descriptionShort",
							"Product.descriptionLong",
							"Product.descriptionExtra",
							"Product.metaDescription",
							"Product.metaKeyword",
							"Product.model",
							"Product.quantity",
							"Product.image",
							"Product.thumb",
							"Product.sortOrder",
							"Product.status",
							"Product.dateAdded",
							"Product.salesTax",
							"Product.originalPrice",
							"Product.depositAmount",
							"Product.isCombinedProduct",
							"Product.updateTime",
							"Product.size",
							"Product.sizeUnit",
							"Product.weight",
							"Product.weightUnit",
							"Product.costUnit",
							"Product.account",
							"Product.accountingUnit",
							"Product.discountsAllowed",
							"Product.clearingDate",
							"Product.clearingNumber",
							"Product.purchasingPrice",
							"Product.currencyId",
							"Product.taxFreeValue",
							"Product.visitProduct",
							"Product.viewId",
							"Product.iecNorm",
							"Product.enNorm",
							"Product.etsiNorm",
							"Product.iecPuzNorm",
							"Product.iecNormStatus",
							"Product.enNormStatus",
							"Product.maxOrderValue",
							"Product.productQuantityLimit",
							"Product.supplier",
							"Product.pdfTemplateId",
							"Product.showShopNotification",
							"Product.startDate",
							"Product.onlyMainStorageInShop",
							"Product.hideQuantityInShop",
							"Product.createdBy",
							"Product.eanCode",
							"Product.availableIn",
							"Product.inShop",
							"PackagingUnitPerProduct.price"
						]&join=[{"model":"PackagingUnitPerProduct","type":"left","left":"Product.id","operator":"=","right":"PackagingUnitPerProduct.productId"}]&';
	
		$km_products = $km_api->km_get_request( 'model/Product?' . $select . 'limit=10&skip=' . $skip .'&status = 1' );
	
		$total_synced = 0;
		if ( $km_api->response_code == 200 ) {
			$total_product = count( $km_products );
			if( ! empty( $km_products ) ) {
				foreach( $km_products as $i => $km_produc ) {
					if ( 1 === $km_produc['Product.status'] && 1 === $km_produc['Product.inShop'] ) {
						get_error_log("syncing product " . $km_produc['Product.id'] . " as those are active and inshop" );
						$wc_product_id = $km_pro_sync->km_update_product( $km_produc );
						firsttool_add_sync_log( $wc_product_id );
						if ( $wc_product_id ) {
							$total_synced++;
						}
					} else {
						get_error_log("not syncing product " . $km_produc['Product.id'] . " as those either not active or not inshop" );
						get_error_log( print_r( $km_produc, true ) );
						$total_synced++;
					}
				}
			} else {
				firsttool_sync_log_process_function();
			}
			$skip = $skip + $total_synced;
		
			return array(
				"status" => 1,
				"message" => "",
				"total_product" => $total_product,
				"total_synced" => $total_synced,
				"skip" => $skip,
			);
		
		} else {
			return array(
				"status" => 0,
				"message" => $km_api->response_message,
				"response_code" => $km_api->response_code,
			);
		}
	}
endif;

if ( ! function_exists( 'firsttool_sync_products_ajax' ) ):
	/**
	 * Callback of AJAX call triggerred from 1Tool settings page on admin side
	 *
	 * Respond the AJAX call triggerred from 1Tool API settings page on admin panel
	 * and starts the product sync process by calling firsttool_sync_products() function.
	 *
	 * @since 1.0.0
	 *
	 * @see firsttool_sync_products()
	 *
	 */
	function firsttool_sync_products_ajax() {
		$skip = isset( $_POST["skip"] ) ? sanitize_text_field($_POST["skip"]) : 0;
		$res = firsttool_sync_products( $skip );
		echo json_encode( $res );
		die();
	}
endif;
add_action( 'wp_ajax_km_sync_products', 'firsttool_sync_products_ajax' );

if ( ! function_exists( 'firsttool_sync_countries_ajax' ) ):
	/**
	 * Callback of AJAX call triggerred from 1Tool settings page on admin side
	 *
	 * Respond the AJAX call triggerred from 1Tool API settings page on admin panel
	 * and starts the product sync process by calling firsttool_sync_products() function.
	 *
	 * @since 1.0.0
	 *
	 * @see firsttool_sync_products()
	 *
	 */
	function firsttool_sync_countries_ajax() {
		$km_api = new FIRSTTOOL_API\FirstTool_API;
		$api_countries = $km_api->km_get_request( 'model/CountryCode?limit=500' );
		if ( ! empty ( $api_countries ) ) {
			$countries = array();
			foreach( $api_countries as $country ) {
				$countries[ $country['CountryCode.code'] ] = $country['CountryCode.id'];
			}
			update_option( 'firsttool_country_ids', $countries );
			$res['status'] = 1;
		} else {
			$res['status'] = 0;
		}
		echo json_encode( $res );
		die();
	}
endif;
add_action( 'wp_ajax_km_sync_countries', 'firsttool_sync_countries_ajax' );

if ( ! function_exists( 'firsttool_custom_cron_schedule' ) ):
	/**
	 * Sets cron schedules of every 6 and 12 hours
	 *
	 * Using cron_schedules filter, add schedule of every 6 and 12 hours to have
	 * cron job of sync to run in that specified intervals.
	 *
	 * @since 1.0.0
	 *
	 */
	function firsttool_custom_cron_schedule( $schedules ) {
		$schedules['every_six_hours'] = array(
			'interval' => 21600, // Every 6 hours
			'display'  =>'Every 6 hours',
		);
		$schedules['every_twelve_hours'] = array(
			'interval' => 43200, // Every 12 hours
			'display'  => 'Every 12 hours',
		);
		return $schedules;
	}
endif;
add_filter( 'cron_schedules', 'firsttool_custom_cron_schedule' );

// Schedule the event if it is not scheduled.
if ( ! wp_next_scheduled( 'firsttool_sync_products_cron', array(0) ) ) {
	wp_schedule_event( strtotime('01:00:00'), 'daily', 'firsttool_sync_products_cron', array(0) );
	wp_schedule_event( strtotime('13:00:00'), 'daily', 'firsttool_sync_products_cron', array(0) ); 
}

if ( ! function_exists( 'firsttool_sync_products_cron' ) ):
	/**
	 * Handles schedule cron and set individual events to sync products from API
	 *
	 * Triggered when scheduled event occure and starts sync process of products
	 * from API by scheduling single run event for each products.
	 *
	 * @since 1.0.0
	 *
	 * @see firsttool_sync_products_cron()
	 *
	 */
	function firsttool_sync_products_cron( $skip ) {
	 
		$km_accessToken = get_option( 'km_accessToken' );
		if ( ! empty( $km_accessToken ) ) {
			$timeIn_new = time();
			$res = firsttool_sync_products( $skip );
			$log = print_r( $res, true );
			
			if ( $res["status"] == 1 && $res["total_product"] == 0 ){
				if ( ! wp_next_scheduled( 'firsttool_sync_log_process_cron' ) ) {
					wp_schedule_single_event( $timeIn_new, "firsttool_sync_log_process_function" );
				}
			}
		 	
			if ( $res["status"] == 0 && ! empty( $res["response_code"] ) && $res["response_code"] !== 200 ) {
				wp_schedule_single_event( $timeIn_new, "firsttool_sync_products_cron", array( $skip ) );
			}
			if ( $res["status"] == 1 && $res["total_product"] == 10 ) {
				$skip = $res["skip"];
				wp_schedule_single_event( $timeIn_new, "firsttool_sync_products_cron", array( $skip ) );
			}
		}
	}
endif;
add_action( 'firsttool_sync_products_cron', 'firsttool_sync_products_cron', 10, 1 );

add_action( 'firsttool_sync_log_process_cron', 'firsttool_sync_log_process_function' );
function firsttool_sync_log_process_function() {
	// Clear not synced products here
	$synced_products = firsttool_get_sync_log();
	if ( !empty( $synced_products ) ){
		$products = wc_get_products( 
			array(
				'post__not_in' => $synced_products,
			) 
		);
		get_error_log("synced log deleting for products " . print_r( $products, true ) );
	}
	foreach( $products as $each_product ){
		$each_product->delete( true ); // Force Delete the product
	}
	firsttool_clear_sync_log();
}

function firsttool_add_sync_log( $product_id ) {
	$current_list = get_option( 'firsttool_synced_products', array() );
	$current_list[] = $product_id;
	get_error_log("synced product added to log " . $product_id );
	update_option( 'firsttool_synced_products', $current_list );
}

function firsttool_get_sync_log() {
	return get_option( 'firsttool_synced_products', array() );
}

function firsttool_clear_sync_log() {
	get_error_log("synced log deleted" );
	delete_option( 'firsttool_synced_products' );
}

function firsttool_get_country_id_from_code( $country_code ) {
	$countries_data = get_option( 'firsttool_country_ids', array() );
	if ( isset( $countries_data[ $country_code ] ) ) {
		return $countries_data[ $country_code ];
	}
	return false;
}
?>