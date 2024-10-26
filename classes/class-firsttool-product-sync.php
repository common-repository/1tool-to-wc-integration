<?php
/**
 * Manages Product Sync from 1Tool API account
 *
 * This file is used for processing Product Snyc as per schedule. It's methods
 * are used for getting data from API and save them as WooCommerce products.
 *
 * @package FIRSTTOOL_API
 * @since 1.0.3
 */

namespace FIRSTTOOL_API;

/**
 * Used for processing product data which and save to woocommerce
 *
 * Processes the product data receved from API and save them as product in WooCommerce
 *
 * @since 1.0.0
 */
class FirstTool_Product_Sync {
	
	/**
	 * Update a product in WooCommerce with provided data
	 *
	 * Updates a product data in WooCommerce based on data received from API
	 * request from 1Tool API EndPoints
	 *
	 * @since 1.0.0
	 *
	 * @param array $product_data Product data to be updated in WooCommerce database
	 * @return integer product_id of updated product
	 */
	public function km_update_product( $product_data ) {
		
		$product_name = $product_data["Product.description"];
		
		$product_id = $this->km_synced_product_id( $product_data["Product.id"] );
		
		if ( ! $product_id ) {
			$post = array(
				'post_status' => 'publish',
				'post_title' => $product_name,
				'post_parent' => '',
				'post_type' => 'product',
			);
			$product_id = wp_insert_post( $post );
		} else {
			$args = array(
				'ID' => $product_id,
				'post_title' => $product_name,
			);
			$result = wp_update_post( $args, true );
			if ( is_wp_error( $result ) ) {
				$errors = $result->get_error_messages();
			}
		}
		
		if ( $product_id > 0 ) {
			
			$product = wc_get_product( $product_id );
			$product->set_description( $product_data['Product.descriptionLong'] );
			$product->set_short_description( $product_data['Product.descriptionShort'] );
			$product->set_price( $product_data["Product.price"] );
			$product->set_regular_price( $product_data["Product.price"] );
			$product->set_name( $product_name );
			$product->set_stock_quantity( $product_data["Product.quantity"] );
			
			$product->set_weight( $product_data["Product.weight"] );
			
			$category_ids = array();
			$ProductGroups = !empty( $product_data["Product.ProductGroup"] ) ? $product_data["Product.ProductGroup"] : array();
			
			foreach( $ProductGroups as $Group ) {
				$term_name = $Group["ProductGroup.description"];
				if ( ! empty( $term_name ) ) {
					$category_ids[] = $this->save_taxonomy_terms( $term_name, 'cat' );
				}
			}
			
			$product->set_category_ids( $category_ids );
			
			// Add product thumb
			if ( ! empty( $product_data["Product.image"] ) ) {
				$img = array( 'src' => 'https://www.kundenmeister.com/crm' . $product_data["Product.image"] );
				$this->km_set_product_images( $product, array( $img ) );
				update_post_meta( $product_id, '_kundenmeister_src', 'https://www.kundenmeister.com/crm' . $product_data["Product.image"] );
			}

			if ( ! empty( $product_data["PackagingUnitPerProduct.price"] ) ) {
				$qty_min = (int) wc_clean( $product_data["PackagingUnitPerProduct.price"] );
				
				update_post_meta( $product_id, '_qty_args', array(
						'qty_min' => $qty_min,
						'qty_max' =>  -1,
						'qty_step' => $qty_min,
					)
				);
			} else {
				update_post_meta( $product_id, '_qty_args', array() ); 
			}
			
			// add product meta
			update_post_meta( $product_id, 'kundenmeister_product_id', $product_data["Product.id"] );
			update_post_meta( $product_id, '_kundenmeister_product', $product_data );
			
			$product->save();
		}
		return $product_id;
	}
	
	/**
	 * Sets a taxonomy to a product
	 *
	 * Set a taxonomy product_cat to a product, it will create a new term if
	 * provided term doesn't exists
	 *
	 * @since 1.0.0
	 *
	 * @param string $term_name Name of product_cat term to assign a product to.
	 * @param string $taxonomy Optional. Name of product_taxonomy postfix. cat.
	 * @return int term_id of assigned product_cat taxonomy term.
	 */
	public function save_taxonomy_terms( $term_name, $taxonomy = 'cat' ){
		$term_id = 0;
		$term_data = get_term_by( 'name', $term_name, 'product_' . $taxonomy );
		if ( $term_data ) {
			$term_id = $term_data->term_id;
		} else {
			$parent = 0;
			$new_cat = wp_insert_term(
				$term_name,
				'product_' . $taxonomy,
				array( 'slug' => $term_name, 'parent' => $parent )
			);
			if ( ! is_wp_error( $new_cat ) ) {
				$term_id = $new_cat['term_id'];
			}
		}
		return $term_id;
	}
	
	/**
	 * Get woocommerce product_id of 1Tool synced product
	 *
	 * Gets woocommerce product_id from database for provided 1Tool API product_id
	 * which was synced before.
	 *
	 * @since 1.0.0
	 *
	 * @param int $to_product_id 1Tool product_id to get WooCommerce synced product_id
	 * @return int product_id of assigned synced product or 0 if fails.
	 */
	function km_synced_product_id( $to_product_id ) {
		
		global $wpdb;
		$sql = "SELECT posts.ID FROM $wpdb->posts AS posts LEFT JOIN $wpdb->postmeta AS postmeta ON ( posts.ID = postmeta.post_id ) WHERE posts.post_type IN ( 'product' ) AND postmeta.meta_key = 'kundenmeister_product_id' AND postmeta.meta_value = %s LIMIT 1";
		
		$product_id = $wpdb->get_var($wpdb->prepare($sql,$to_product_id));
		
		return ( $product_id ) ? intval( $product_id ) : 0;
	}
	
	/**
	 * Creates and Assign product images to product
	 *
	 * Create media attachment of provided images and assign them to provided 
	 * product. It checks image name to see if it's already in library and create new
	 * attachment if not available. Otherwise directly assign that image to product.
	 * If no images are passed then it will clear all assigned images from that
	 * that product. First image will be assigned as Featured image and other
	 * will be assigned to gallery image of provided product.
	 *
	 * @since 1.0.0
	 *
	 * @see find_upload_image()
	 *
	 * @param WC_Product $product WC_Product object to assign provided images
	 * @param array $images WC_Product object to assign provided images
	 * @return WC_Product return updated and saved WC_Product object.
	 */
	function km_set_product_images( $product, $images ) {
		$product_id = $product->get_id();
		if ( is_array( $images ) ) {
			$gallery = array();
			foreach ( $images as $k => $image ) {
				$to_attachment_id = isset( $image['id'] ) ? absint( $image['id'] ) : 0;
				$to_attachment_src = isset( $image['src'] ) ? esc_url_raw( $image['src'] ) : "";
				$to_attachment_name = basename( $to_attachment_src );
				
				$attachment_id = $this->find_upload_image( $to_attachment_name );
		 
				if ( 0 === $attachment_id && ! empty( $to_attachment_src ) ) {
					$upload = wc_rest_upload_image_from_url( $to_attachment_src );

					if ( ! is_wp_error( $upload ) ) { 
						$attachment_id = wc_rest_set_uploaded_image_as_attachment( $upload, $product->get_id() );
					}
				}
				
				if ( $attachment_id > 0 ) {
					
					update_post_meta( $attachment_id, '_kundenmeister_src', $to_attachment_name );

					if ( $k == 0 ) {
						$product->set_image_id( $attachment_id );
					} else {
						$gallery[] = $attachment_id;
					}

					// Set the image alt if present.
					if ( ! empty( $image['alt'] ) ) {
						update_post_meta( $attachment_id, '_wp_attachment_image_alt', wc_clean( $image['alt'] ) );
					}

					// Set the image name if present.
					if ( ! empty( $image['name'] ) ) {
						wp_update_post( array( 'ID' => $attachment_id, 'post_title' => $image['name'] ) );
					}
				}
			}

			if ( ! empty( $gallery ) ) {
				$product->set_gallery_image_ids( $gallery );
			}
		
		} else {
			$product->set_image_id( '' );
			$product->set_gallery_image_ids( array() );
		}
		$product->save();
		return $product;
	}
	
	/**
	 * Get product id of already synced Image
	 *
	 * This function is used to get product_id of provided image src.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to_attachment_src Image src to find product_id of.
	 * @return int product_id of product which has image attached to.
	 */
	function find_upload_image( $to_attachment_src ) {
		
		global $wpdb;
		$meta_key = '_kundenmeister_src';
		$meta_value = $to_attachment_src;
		
		$sql = "SELECT meta.post_id FROM $wpdb->postmeta AS meta WHERE meta.meta_key = %s and meta.meta_value = %s LIMIT 1";
		
		$product_id = $wpdb->get_var( $wpdb->prepare( $sql, $meta_key, $meta_value ) );
		return ( $product_id ) ? intval( $product_id ) : 0;
	}
}
?>