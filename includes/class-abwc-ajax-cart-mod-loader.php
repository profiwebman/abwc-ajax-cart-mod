<?php
/**
 * Register all actions and filters for the plugin
 *
 * @link       https://wordpress.org/plugins/ajaxified-cart-woocommerce/
 * @since      1.0.0
 *
 * @package    ABWC_Ajax_Cart
 * @subpackage ABWC_Ajax_Cart/includes
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin. Call the run function to execute the
 * list of actions and filters.
 *
 * @package    ABWC_Ajax_Cart
 * @subpackage ABWC_Ajax_Cart/includes
 * @author     Abhishek Kumar <abhishekfdd@gmail.com>
 */
class ABWC_Ajax_Cart_Loader {

	/**
	 * Initialize the collections .
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'single_product_ajaxified_button' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'quantity_inputs_for_woocommerce_loop_add_to_cart_link' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		add_action( 'wp_ajax_woocommerce_add_to_cart_variable_rc', array( $this, 'abwc_add_to_cart_variable_rc_callback' ) );
		add_action( 'wp_ajax_nopriv_woocommerce_add_to_cart_variable_rc', array( $this, 'abwc_add_to_cart_variable_rc_callback' ) );

		add_action( 'wp_ajax_abwc_get_cart_total', array( $this, 'abwc_get_cart_total' ) );
		add_action( 'wp_ajax_nopriv_abwc_get_cart_total', array( $this, 'abwc_get_cart_total' ) );

		add_action( 'after_setup_theme', array( $this, 'abwc_variable_product_archive_ajax' ) );
	}

	/**
	 * Adds hidden button with product attributes next to add to cart button
	 *
	 * @global type $product
	 *
	 * @since    1.0.0
	 */
	public function single_product_ajaxified_button() {

		global $product;

		if ( 'simple' === $product->get_type() ) {

			echo apply_filters(
				'abwc_add_to_cart_link',
				sprintf(
					'<input type=hidden data-product_id="%s" data-product_sku="%s" class="abwc-ajax-btn button">',
					esc_attr( $product->get_id() ),
					esc_attr( $product->get_sku() )
				),
				$product
			);
		}
	}

	public function quantity_inputs_for_woocommerce_loop_add_to_cart_link() {
		global $product, $html;
		if ( $product && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && ! $product->is_sold_individually() ) {
			$html  = '<form action="' . esc_url( $product->add_to_cart_url() ) . '" class="cart" method="post" enctype="multipart/form-data">';
			$html .= woocommerce_quantity_input( array(), $product, false );
			$html .= '<button type="submit" name="add-to-cart" class="single_add_to_cart_button button alt">' . esc_html( $product->add_to_cart_text() ) . '</button>';
			$html .= sprintf(
				'<input type=hidden data-product_id="%s" data-product_sku="%s" class="abwc-ajax-btn button">',
				esc_attr( $product->get_id() ),
				esc_attr( $product->get_sku() )
			);
			$html .= '</form>';
		}
		return $html;
	}
	/**
	 * Ajax callback for variable products
	 *
	 * @since    1.0.0
	 */
	function abwc_add_to_cart_variable_rc_callback() {

		$product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
		$quantity          = empty( $_POST['quantity'] ) ? 1 : apply_filters( 'woocommerce_stock_amount', $_POST['quantity'] );
		$variation_id      = isset( $_POST['variation_id'] ) ? ( $_POST['variation_id'] ) : '';
		$variation         = isset( $_POST['variation'] ) ? ( $_POST['variation'] ) : '';
		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

		if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) ) {

			do_action( 'woocommerce_ajax_added_to_cart', $product_id );

			if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
				wc_add_to_cart_message( $product_id );
			}

			// Return fragments.
			WC_AJAX::get_refreshed_fragments();
		} else {

			// If there was an error adding to the cart, redirect to the product page to show any errors.
			$data = array(
				'error'       => true,
				'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
			);
			wp_send_json( $data );
		}
		wp_die();
	}

	function abwc_get_cart_total() {

		$add_popup_window = run_abwc_ajax_cart()->option( 'show_popup_after_cart_add' );

		if ( ! isset( $add_popup_window ) || ( isset( $add_popup_window ) && 'yes' !== $add_popup_window ) ) {
			echo 'false';
			wp_die();
		}

		$cart_totals      = WC()->cart->get_totals();
		$cart             = WC()->cart->get_cart();
		$cart_total_count = 0;
		$image            = '';
		$product_id       = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
		$quantity         = empty( $_POST['quantity'] ) ? 1 : apply_filters( 'woocommerce_stock_amount', $_POST['quantity'] );
		$variation_id     = isset( $_POST['variation_id'] ) ? ( $_POST['variation_id'] ) : '';
		$variation        = isset( $_POST['variation'] ) ? ( $_POST['variation'] ) : '';
		$type             = isset( $_POST['type'] ) ? ( $_POST['type'] ) : 'simple';

		foreach ( $cart as $key => $item ) {
				$cart_total_count += $item['quantity'];
		}

		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'single-post-thumbnail' );
		$image = $image[0];
		if ( ! $variation_id ) {
			$product = new WC_Product( $product_id );
		} else {
			$product = new WC_Product_Variable( $product_id );
		}
		$product_name = $product->name;
		$price        = $product->price;

		if ( 'variable' === $product->get_type() ) {

			$variations = $product->get_available_variations();

			foreach ( $variations as $key => $variation_data ) {
				if ( $variation_data['variation_id'] == $variation_id ) {
					$image = $variation_data['image']['src'];
					$price = $variation_data['display_price'];
				}
			}
		}

		echo json_encode(
			array(
				'product_name'  => $product_name,
				'image'         => $image,
				'count_in_cart' => $cart_total_count,
				'product_price' => wc_price( $price, '' ),
				'total_price'   => wc_price( ( $cart_totals['total'] ), '' ),
			)
		);

		wp_die();

	}

	/**
	 * Variable product ajax
	 *
	 * Loads markup for WooCommerce variable products in
	 * archive pages and prepares it for ajax.
	 */
	function abwc_variable_product_archive_ajax() {

		$category_page = run_abwc_ajax_cart()->option( 'enable_on_archive_page' );

		if ( ! isset( $category_page ) || ( isset( $category_page ) && 'yes' !== $category_page ) ) {
			return;
		}

		if ( ! function_exists( 'woocommerce_template_loop_add_to_cart' ) ) {

			/**
			 * Get the add to cart template for the loop.
			 *
			 * @subpackage  Loop
			 *
			 * @param array $args args for the function.
			 */
			function woocommerce_template_loop_add_to_cart( $args = array() ) {
				global $product;

				if ( $product ) {
					$defaults = array(
						'quantity'   => 1,
						'class'      => implode(
							' ',
							array_filter(
								array(
									'button',
									'product_type_' . $product->get_type(),
									$product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
									$product->supports( 'ajax_add_to_cart' ) ? 'ajax_add_to_cart' : '',
								)
							)
						),
						'attributes' => array(
							'data-product_id'  => $product->get_id(),
							'data-product_sku' => $product->get_sku(),
							'aria-label'       => $product->add_to_cart_description(),
							'rel'              => 'nofollow',
						),
					);

					$args = apply_filters( 'woocommerce_loop_add_to_cart_args', wp_parse_args( $args, $defaults ), $product );

					if ( 'variable' === $product->get_type() ) {
						woocommerce_variable_add_to_cart();
					} else {
						wc_get_template( 'loop/add-to-cart.php', $args );
					}
				}
			}
		}
	}

	/**
	 * Loading js required for this plugin
	 *
	 * @since    1.0.0
	 */
	public function assets() {

		wp_enqueue_script( 'abwc-arcticmodal-js', ABWC_AJAX_CART_PLUGIN_URL . 'assets/js/arcticmodal.min.js', array( 'jquery' ), ABWC_AJAX_CART_PLUGIN_VERSION . true );
		wp_enqueue_script( 'abwc-ajax-mod-js', ABWC_AJAX_CART_PLUGIN_URL . 'assets/js/abwc-ajax-cart-mod.min.js', array( 'jquery' ), ABWC_AJAX_CART_PLUGIN_VERSION . true );
		wp_enqueue_script( 'abwc-ajax-variation-mod--js', ABWC_AJAX_CART_PLUGIN_URL . 'assets/js/abwc-ajax-variation-cart-mod.min.js', array( 'jquery' ), ABWC_AJAX_CART_PLUGIN_VERSION . true );

		wp_enqueue_style( 'abwc-ajax-mod-css', ABWC_AJAX_CART_PLUGIN_URL . 'assets/css/abwc-ajax-cart-mod.css' );
		wp_enqueue_style( 'abwc-arcticmodal-css', ABWC_AJAX_CART_PLUGIN_URL . 'assets/css/jquery.arcticmodal-0.3.css' );
	}

	/**
	 * Loading admin js required for this plugin
	 *
	 * @since    1.0.0
	 */
	public function admin_assets() {

		wp_enqueue_script( 'abwc-ajax-admin-js', ABWC_AJAX_CART_PLUGIN_URL . 'assets/js/abwc-ajax-cart-mod-admin.js', array( 'jquery' ), ABWC_AJAX_CART_PLUGIN_VERSION . true );
		wp_localize_script( 'abwc-ajax-admin-js', 'abwc_ajax_data', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

	}

}
