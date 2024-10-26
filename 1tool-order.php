<?php 

if ( ! function_exists( 'firsttool_create_order' ) ):
	/**
	 * Creates order on 1Tool account using API call.
	 *
	 * This calls ShopOrders endpoint on 1Tool API to create Sales order there.
	 *
	 * @since 1.0.3
	 *
	 */
	function firsttool_create_order( $order_id ) {
		
		get_error_log("start to create order");
		
		$obj_kdm = new FIRSTTOOL_API\FirstTool_API;
	
		$created_in_kundenmeister = get_post_meta( $order_id, 'created_in_kundenmeister', true );
		
		get_error_log("Created kundenmeister val :".$created_in_kundenmeister);
		 
		if ( empty( $created_in_kundenmeister ) ) {
		
		get_error_log("in if loop when created_in_kundenmeister is empty");
		
			$order = wc_get_order( $order_id );
			$kdm_order = array();
			$orderId = $order_id;
			$customer_id = $order->get_customer_id();
			
			get_error_log("Customer ID : ".$customer_id);
			
			$kdm_customer_id = 0;
			if ( $customer_id > 0 ) {
				
				get_error_log("if customer id is >0" .$customer_id);
				
				$kdm_customer = get_user_meta( $customer_id, 'kundenmeister_customer', true );
				$kdm_customer_id = isset( $kdm_customer["Customer.id"] ) ? $kdm_customer["Customer.id"] : 0;
				get_error_log("KM customer id is for " . $customer_id . " is ". $kdm_customer_id );
			}else{ // custom else part
				get_error_log("if customer id = 0 :".$customer_id);
			}
			$invoice_address_data = array();
			
			$orderDate = $order->get_date_created()->format( "Y-m-d" );
			$customer_note = $order->get_customer_note();
			$firstName = $order->get_billing_first_name();
			$lastName = $order->get_billing_last_name();
			$invoice_address_data[] = $firstName . " " . $lastName;
			$company = $order->get_billing_company();
			$address = $order->get_billing_address_1();
			$address_a = $order->get_billing_address_2();
			$invoice_address_data[] = $address . " " . $address_a;
			$city = $order->get_billing_city();
			$invoice_address_data[] = $city;
			$state = $order->get_billing_state();
			$postalCode = $order->get_billing_postcode();
			$invoice_address_data[] = $postalCode . " " . $state;
			$billing_country = $order->get_billing_country();
			$invoice_address_data[] = WC()->countries->countries[ $billing_country ];
			
			$invoice_address_string = implode("<br>", $invoice_address_data );
		
			$email = $order->get_billing_email();
			$phone = $order->get_billing_phone();
		 
			$shipping_address_1 = $order->get_shipping_address_1();
			$shipping_address_1 = empty( $shipping_address_1 ) ? $address : $shipping_address_1;
			$shipping_address_2 = $order->get_shipping_address_2();
			$shipping_address_2 = empty( $shipping_address_2 ) ? $address_a : $shipping_address_2;
		
			$deliverCity = $order->get_shipping_city();
			$deliverCity = empty( $deliverCity ) ? $city : $deliverCity;
		
			$deliverPostalCode = $order->get_shipping_postcode();
			$deliverPostalCode = empty( $deliverPostalCode ) ? $postalCode : $deliverPostalCode;
		
			$remarks = $order->get_customer_note();
			$total = $order->get_total();
		
			$shippingTotal = $order->get_shipping_total();
			$total_discount = $order->get_total_discount();
			$billingAddress = $address . " " . $address_a;
			$shippingAddress = $shipping_address_1 . " " . $shipping_address_2;
			
			$payment_method = $order->get_payment_method();
			$is_offline_payment = in_array($payment_method, array('bacs', 'cheque', 'cod'));
			
			// Get and Loop Over Order Items
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id = $item->get_product_id();
		   
				$kdm_product_id = get_post_meta( $product_id, 'kundenmeister_product_id', true );
				
				get_error_log("product id : ".$kdm_product_id);
				
				if ( ! empty( $kdm_product_id ) ) {
					
					get_error_log( "in if loop when product id is not empty " . $kdm_product_id );
					
					$quantity = $item->get_quantity();
					$product_name = $item->get_name();
					$total = $item->get_total();
					$unitPrice = $total / $quantity;
					$subtotal = $item->get_subtotal();
					$tax = $item->get_subtotal_tax();
					
					$tax_percentage = round( ( $tax / $subtotal ), 2 );
					
					$kdm_order["items"][ $item_id ] = $item;
					$kdm_order["items_invoide"][ $item_id ] = array(
						"text" => $product_name,
						"quantity" => $quantity,
						"price_per_unit" => $unitPrice,
						"product_id" => $kdm_product_id,
						"tax" => $tax_percentage,
					);
			   }else{ // custom else part
				   get_error_log("if kdm_product_id is empty ".$kdm_product_id);
			   }
			}
		
			// Create customer
			if ( $kdm_customer_id == 0 ) {
				
				get_error_log("in if loop to create customer ".$kdm_customer_id);
				
				// create customer
				$kdm_Customer = array(
					"Customer.mail" => $email,
					"Customer.name" => $lastName,
					"Customer.firstName" => $firstName,
					"Customer.street" => $address . " " . $address_a,
					"Customer.postalCode" => $postalCode,
					"Customer.city" => $city,
					"Customer.federalState" => $state,
					"Customer.phoneNumber" => $phone,
					"Customer.invoiceName" => $firstName . " " . $lastName,
					"Customer.invoiceFirstName" => $firstName,
					"Customer.invoiceStreet" => $address . " " . $address_a,
					"Customer.invoicePostalCode" => $postalCode,
					"Customer.invoiceCity" => $city,
					"Customer.invoiceCountry" => firsttool_get_country_id_from_code( $billing_country ),
				);
						
				$kdm_where = '?limit=1&where=[{"property":"Customer.mail","operator":"=","value":"' . $email . '"}]';
			
				$kdm_cus_get = $obj_kdm->km_get_request( 'model/Customer' . $kdm_where );
				
				get_error_log("response for kdm_cus_get  ". print_r($kdm_cus_get,true));
				
				if ( $obj_kdm->response_code == 200 && isset( $kdm_cus_get[0]["Customer.id"] ) ) {
					
					get_error_log("in if loop if create customer have 200 response");
					
					$kdm_customer_id = $kdm_cus_get[0]["Customer.id"];
					update_user_meta( $customer_id, "kundenmeister_customer", $kdm_cus_get[0] );
				} else {
					$kdm_cus = $obj_kdm->km_post_request( 'model/Customer', $kdm_Customer );
					
					get_error_log("in else part of create customer");
					
					
					if ( $obj_kdm->response_code == 200 && isset( $kdm_cus["Customer.id"] ) ) {
						
						get_error_log("nested if in create customer's else part");
						
						get_error_log("response for Customer  ". print_r($kdm_cus,true));
						
						$kdm_customer_id = $kdm_cus["Customer.id"];
						update_user_meta( $customer_id, "kundenmeister_customer", $kdm_cus );
					}else { // custom else part

						get_error_log("response for Customer if not 200 code ". print_r($kdm_cus,true));
					}						
				}
			}else{ // custom else part
				get_error_log("kdm_customer_id is not = 0 ".$kdm_customer_id);
			}
			
			$payment_status = $order->is_paid();
			if ( $payment_status === true ) {
				$ispaid = current_time('timestamp');
			} else {
				$ispaid = 	"null";
			}
		
			if ( isset( $kdm_order["items"] ) ) {
				
				get_error_log("in if loop of create invoice ".print_r($kdm_order["items"], true));
				
				// Create invice
				$kdm_invice_data = array();
				$kdm_invice_data["items"] = $kdm_order["items_invoide"];
				$kdm_invice_data["optionId"] = '1';
				$kdm_invice_data["invoiceData"] = array( "customer_email" => $email );
				//$kdm_invice_data["isPaid"] = '1';
				$kdm_invoice_data["isPaid"] = $ispaid;
				$km_is_create_invoice = get_option( 'km_is_create_invoice' );
				if ( $km_is_create_invoice == "yes" ) {
					$res_kdm_invoice =  $obj_kdm->km_post_request( 'invoice/createInvoice', $kdm_invice_data);
					
					get_error_log("if create invoice ".print_r($res_kdm_invoice,true));
					
					get_error_log("In if loop where km_is_create_invoice == yes");
				}
			
				$invoiceId = null;
				$invoiceCreated = 0;
				if ( isset( $res_kdm_invoice["invoiceIds"] ) ) {
					
					get_error_log("in if condition if invoice id is set ".print_r($res_kdm_invoice["invoiceIds"], true));
					
					$invoiceId = $res_kdm_invoice["invoiceIds"][0];
					$invoiceCreated = 1;
				}else{ // custom else part
					get_error_log("in else part of if condition if invoice id is not set ".print_r($res_kdm_invoice["invoiceIds"],true));
				}
				
				$kdm_ShopOrder = array(
					"ShopOrder.id" => null,
					"ShopOrder.status" => 0,
					"ShopOrder.currencyId" => null,
					"ShopOrder.userId" => $kdm_customer_id,
					"ShopOrder.total" => $total,
					"ShopOrder.shippingTotal" => $shippingTotal,
					"ShopOrder.billingAddress" => null,
					"ShopOrder.shippingAddress" => null,
					"ShopOrder.userComment" => $customer_note,
					"ShopOrder.comment" => '',
					"ShopOrder.couponId" => 0,
					"ShopOrder.orderDiscount" => $total_discount,
					"ShopOrder.shippingStatus" => '4',
					"ShopOrder.paymentMethod" => null, 
					"ShopOrder.departmentId" => null,
					"ShopOrder.orderDate" => $orderDate,
					"ShopOrder.description" => "WooCommerce Order ID #" . $orderId,
					"ShopOrder.invoiceCreated" => $invoiceCreated,
					"ShopOrder.invoiceId" => $invoiceId,
					"ShopOrder.packageNumber" => null,
					"ShopOrder.orderCode" => null,
				);
				// Create order
				$res_kdm_order = $obj_kdm->km_post_request( 'model/ShopOrder', $kdm_ShopOrder );
				
				get_error_log("response for Shop Order  ". print_r($res_kdm_order,true));
				
				if ( isset( $res_kdm_order["ShopOrder.id"] ) ) {
					
					get_error_log("in if condition of shoporder");
					
					// Add item in order
					foreach( $kdm_order["items"] as $item_id => $item ) {
						$quantity = $item->get_quantity();
						$total = $item->get_total();
						$unitPrice = $total / $quantity;
						$subtotal = $item->get_subtotal();
						$tax = $item->get_subtotal_tax();
						$tax_percentage = round( ( $tax / $subtotal ) * 100 );
						$product_id = $item->get_product_id();
						$productId = get_post_meta( $product_id, 'kundenmeister_product_id', true );
						$kdm_items = array(
							"ShopOrderItem.orderId" => $res_kdm_order["ShopOrder.id"],
							"ShopOrderItem.productId" => $productId,
							"ShopOrderItem.quantity" => $quantity,
							"ShopOrderItem.price" => $unitPrice,
							"ShopOrderItem.subTotal" => $total,
							"ShopOrderItem.tax" => $tax_percentage,
							"ShopOrderItem.options" => "",
							"ShopOrderItem.storageId" => null,
						);
						$res_kdm_item =  $obj_kdm->km_post_request( 'model/ShopOrderItem', $kdm_items );
						
						get_error_log("response for Shop Order Item ". print_r($res_kdm_item,true));
					}
					update_post_meta( $order_id, 'created_in_kundenmeister', $res_kdm_order );
					
					get_error_log("order updated");
				}
				$kdm_update_invoice = array(
					"Invoice.contactId" => $kdm_customer_id,
					"Invoice.customerAddress" => $invoice_address_string
				);
				// Link Invoice to Customer and ShopOrder
				get_error_log("Updating Invoice for :". $invoiceId );
				$res_kdm_item =  $obj_kdm->km_put_request( 'model/Invoice/' . $invoiceId, $kdm_update_invoice );
				get_error_log("Updated Invoice response :". print_r( $res_kdm_item, true ) );
			}else{ // custom else part 
				get_error_log(" if kdm_order['items'] is not set :".$kdm_order["items"]);
			}
		}
	}
endif;

/* Create order on kundenmeister  */
add_action( 'woocommerce_order_status_processing', 'firsttool_create_order' );

function get_error_log( $message ) {
	if ( FIRSTTOOL_ENABLE_DEBUG_MODE ){
		$logFilePath = __DIR__ . "/my-errors.log";
		$result = error_log("\n\r".$message, 3, $logFilePath);
		return $result; 
	}
}
?>