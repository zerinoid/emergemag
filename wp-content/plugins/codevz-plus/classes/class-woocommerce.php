<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Woocommerce compatibility.
 * 
 * @link https://xtratheme.com/
 */

class Xtra_Woocommerce {

	public static $instance = null;

	public function __construct() {

		// Quickview popup content.
		add_action( 'wp_footer', [ $this, 'wp_footer' ] );

		// Products page number of columns.
		add_filter( 'loop_shop_columns', [ $this, 'columns' ], 11 );

		// Number of products per page.
		add_filter( 'woocommerce_product_query', [ $this, 'products_per_page' ], 11 );

		// AJAX mini cart content.
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart' ] );

		// Number of  related products per page.
		add_filter( 'woocommerce_upsell_display_args', [ $this, 'related_products' ], 11 );
		add_filter( 'woocommerce_output_related_products_args', [ $this, 'related_products' ], 11 );

		// Customize products HTML and add quickview and wihlist.
		add_filter( 'woocommerce_post_class', [ $this, 'product_classes' ] );
		add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'single_wishlist' ], 20 );
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'woocommerce_before_shop_loop_item_title_low' ], 9 );
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'woocommerce_before_shop_loop_item_title_high' ], 11 );

		// Single Wrap.
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'before_single' ], 11 );
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'after_single' ], 1 );

		// Cart item removal AJAX.
		add_action( 'wp_ajax_xtra_remove_item_from_cart', [ $this, 'remove_item_from_cart' ] );
		add_action( 'wp_ajax_nopriv_xtra_remove_item_from_cart', [ $this, 'remove_item_from_cart' ] );

		// Quickview AJAX function.
		add_action( 'wp_ajax_xtra_quick_view', [ $this, 'quickview' ] );
		add_action( 'wp_ajax_nopriv_xtra_quick_view', [ $this, 'quickview' ] );

		// Single product AJAX add to cart.
		add_action( 'wp_ajax_woocommerce_ajax_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
		add_action( 'wp_ajax_nopriv_woocommerce_ajax_add_to_cart', [ $this, 'ajax_add_to_cart' ] );

		// Get wishlist page content via AJAX.
		add_action( 'wp_ajax_xtra_wishlist_content', [ $this, 'wishlist_content' ] );
		add_action( 'wp_ajax_nopriv_xtra_wishlist_content', [ $this, 'wishlist_content' ] );

		// Wishlist shortcode.
		add_shortcode( 'cz_wishlist', [ $this, 'wishlist_shortcode' ] );

		// Modify checkout page.
		add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'checkout_before' ] );
		add_action( 'woocommerce_checkout_after_order_review', [ $this, 'after_single' ] );

		// Add back to store button on WooCommerce cart page.
		add_action( 'woocommerce_cart_actions', [ $this, 'continue_shopping' ] );

	}

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get WooCommerce cart in header.
	 * 
	 * @return string
	 */
	public static function cart( $fragments ) {

		$wc = WC();
		$count = $wc->cart->cart_contents_count;
		$total = $wc->cart->get_cart_total();
		
		ob_start(); ?>
			<div class="cz_cart">
				<?php if ( $count > 0 ) { ?>
				<span class="cz_cart_count"><?php echo esc_html( $count ); ?></span>
				<?php } ?>
				<div class="cz_cart_items"><div>
			        <?php if ( $wc->cart->cart_contents_count == 0 ) { ?>
				    	<div class="cart_list">
				    		<div class="item_small xtra-empty-cart"><?php echo esc_html( Codevz_Plus::option( 'woo_no_products', 'No products in the cart' ) ); ?></div>
				    	</div>
				    <?php $fragments['.cz_cart'] = ob_get_clean(); return $fragments; } else { ?>
			        	<div class="cart_list">

			        		<div class="item_small xtra-empty-cart hidden"><?php echo esc_html( Codevz_Plus::option( 'woo_no_products', 'No products in the cart' ) ); ?></div>
			        		
			        		<?php foreach( $wc->cart->cart_contents as $cart_item_key => $cart_item ) {
			        			$id = $cart_item['product_id'];
			        			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			        		?>
					            <div class="item_small">
					                <a href="<?php echo esc_url( get_permalink( $id ) ); ?>">
					                	<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'codevz_600_600' ), $cart_item, $cart_item_key ) ); ?>
					                </a>
					                <div class="cart_list_product_title cz_tooltip_up">
					                    <h3><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3>
					                    <div class="cart_list_product_quantity"><?php echo wp_kses_post( $cart_item['quantity'] ); ?> x <?php echo wp_kses_post( $wc->cart->get_product_subtotal( $cart_item['data'], 1 ) ); ?> </div>
					                    <a href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>" class="remove" data-product_id="<?php echo esc_attr( $id ); ?>" data-title="<?php echo esc_html__( 'Remove', 'codevz' ); ?>"><i class="fa czico-198-cancel"></i></a>
					                </div>
					            </div>
			        		<?php } ?>
			        	</div>
				        
				        <div class="cz_cart_buttons clr">
							<a href="<?php echo esc_url( get_permalink(get_option('woocommerce_cart_page_id')) ); ?>"><?php echo esc_html( do_shortcode( Codevz_Plus::option( 'woo_cart', 'Subtotal' ) ) ); ?> <span><?php echo wp_kses_post( $wc->cart->get_cart_total() ); ?></span></a>
							<a href="<?php echo esc_url( get_permalink(get_option('woocommerce_checkout_page_id')) ); ?>"><?php echo esc_html( do_shortcode( Codevz_Plus::option( 'woo_checkout', 'Checkout' ) ) ); ?></a>
				        </div>
			        <?php } ?>
				</div></div>
			</div>
		<?php 

		$fragments['.cz_cart'] = ob_get_clean();

		return $fragments;
	}

	/**
	 * WooCommerce products columns
	 * 
	 * @return string
	 */
	public static function columns() {
		return (int) Codevz_Plus::option( 'woo_col', 4 );
	}

	/**
	 * WooCommerce products per page
	 * 
	 * @return string
	 */
	public static function products_per_page( $q ) {
		$q->set( 'posts_per_page', (int) Codevz_Plus::option( 'woo_items_per_page', 8 ) );
	}

	/**
	 * WooCommerce products per page
	 * 
	 * @return string
	 */
	public static function related_products( $i ) {
		$c = (int) Codevz_Plus::option( 'woo_related_col' );

		$i['columns'] = $c;
		$i['posts_per_page'] = $c;

		return $i;
	}

	/**
	 * Wishlist container shortcode.
	 * 
	 * @return string
	 */
	public static function wishlist_shortcode( $a, $c = '' ) {
		return '<div class="woocommerce xtra-wishlist xtra-icon-loading" data-empty="' . esc_html__( 'Your wishlist is empty.', 'codevz' ) . '" data-nonce="' . wp_create_nonce( 'xtra_wishlist_content' ) . '"></div>';
	}

	/**
	 * Get wishlist products via AJAX.
	 * 
	 * @return string
	 */
	public static function wishlist_content() {
		if ( empty( $_POST['ids'] ) && empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'xtra_wishlist_content' ) ) {
			wp_die( '<b>' . esc_html__( 'Server error, Please reload page ...', 'codevz' ) . '</b>' );
		}

		wp_die( do_shortcode( '[products ids="' . $_POST['ids'] . '" columns="3"]' ) );
	}

	/**
	 * Enable ajax add to cart in single pages.
	 * 
	 * @return string
	 */
	public static function ajax_add_to_cart() {
	 
	    $product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
	    $quantity = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( $_POST['quantity'] );
	    $variation_id = absint( $_POST['variation_id'] );
	    $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
	    $product_status = get_post_status( $product_id );

	    if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id ) && 'publish' === $product_status ) {

	        do_action('woocommerce_ajax_added_to_cart', $product_id );

	        if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
	            wc_add_to_cart_message( array( $product_id => $quantity ), true );
	        }

	        WC_AJAX::get_refreshed_fragments();
	    } else {

	        $data = array(
	            'error' 		=> true,
	            'product_url' 	=> apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id )
	        );

	        echo wp_send_json( $data );
	    }

	    wp_die();
	}

	/**
	 * Add wishlist icon into single product page.
	 * 
	 * @return string
	 */
	public static function single_wishlist() {

		if ( Codevz_Plus::option( 'woo_wishlist' ) ) {

			echo '<div class="xtra-product-icons cz_tooltip_up" data-id="' . get_the_ID() . '">';
			echo '<i class="fa fa-heart-o xtra-add-to-wishlist" data-url="" data-title="' . esc_html__( 'Add to Wishlist', 'codevz' ) . '"></i>';
			echo '</div>';

		}

	}

	/**
	 * AJAX remove product from header cart.
	 * 
	 * @return string
	 */
	public static function remove_item_from_cart() {

		$wc = WC();
		$cart = $wc->instance()->cart;
		$cart_id = $cart->generate_cart_id( $_POST['id'] );
		$cart_item_id = $cart->find_product_in_cart( $cart_id );

		if ( $cart_item_id ) {
			$cart->set_quantity( $cart_item_id, 0 );
		} 

		//wp_die( $wc->cart->cart_contents_count );

		// Return cart content
		wp_die( WC_AJAX::get_refreshed_fragments() );
	}

	/**
	 * Add extra custom classes to products.
	 * 
	 * @return array
	 */
	public static function product_classes( $classes ) {

		// Check array.
		if ( ! is_array( $classes ) ) {
			return $classes;
		}

		// Product ID.
		$id = get_the_id();

		// Current query.
		global $wp_query;

		// Check single product class name.
		if ( is_single() && $wp_query->post->ID === $id ) {
			return $classes;
		}

		// Hover effect name.
		$hover = Codevz_Plus::option( 'woo_hover_effect' );

		if ( $hover ) {

			$product = new WC_Product( $id );
			$attachment_ids = $product->get_gallery_image_ids();

			// Check gallery first image.
			if ( is_array( $attachment_ids ) && isset( $attachment_ids[0] ) ) {

				$classes[] = 'cz_image';
				$classes[] = 'cz_image_' . esc_attr( $hover );

			}
		}

		return (array) $classes;
	}

	public static function quickview() {
		if ( ! isset( $_POST['id'] ) && ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'xtra_quick_view' ) ) {
			wp_die( '<b>' . esc_html__( 'Server error, Please reload page and try again ...', 'codevz' ) . '</b>' );
		}

		echo '<div class="xtra-qv-product-content">';
		$content = do_shortcode( '[product_page id="' . $_POST['id'] . '"] ' );
		echo str_replace( 'data-src=', 'src=', $content );
		
		echo '</div>';

		echo '<script src="' . plugins_url( 'assets/js/zoom/jquery.zoom.min.js', WC_PLUGIN_FILE ) . '"></script>';
		echo '<script src="' . plugins_url( 'assets/js/flexslider/jquery.flexslider.min.js', WC_PLUGIN_FILE ) . '"></script>';
		
		?><script type='text/javascript'>
		/* <![CDATA[ */
		var wc_single_product_params = <?php echo json_encode( array(
			'flexslider' => apply_filters(
				'woocommerce_single_product_carousel_options',
				array(
					'rtl'            => Codevz_Plus::$is_rtl,
					'animation'      => 'slide',
					'smoothHeight'   => true,
					'directionNav'   => false,
					'controlNav'     => 'thumbnails',
					'slideshow'      => false,
					'animationSpeed' => 500,
					'animationLoop'  => false, // Breaks photoswipe pagination if true.
					'allowOneSlide'  => false,
				)
			),
			'zoom_enabled' => apply_filters( 'woocommerce_single_product_zoom_enabled', get_theme_support( 'wc-product-gallery-zoom' ) ),
			'zoom_options' => apply_filters( 'woocommerce_single_product_zoom_options', array() ),
			'photoswipe_enabled' => false,
			'flexslider_enabled' => apply_filters( 'woocommerce_single_product_flexslider_enabled', get_theme_support( 'wc-product-gallery-slider' ) ),
		) ); ?>;
		/* ]]> */
		</script><?php

		echo '<script src="' . plugins_url( 'assets/js/frontend/single-product.min.js', WC_PLUGIN_FILE ) . '"></script>';
		
		wp_die();
	}

	public static function woocommerce_before_shop_loop_item_title_low() {
		echo '<div class="xtra-product-thumbnail">';

		$product_id = get_the_ID();

		$wishlist = Codevz_Plus::option( 'woo_wishlist' );
		$quick_view = Codevz_Plus::option( 'woo_quick_view' );

		if ( $wishlist || $quick_view ) {

			$center = Codevz_Plus::option( 'woo_wishlist_qv_center' ) ? ' xtra-product-icons-center' : '';
			$center .= $center ? ' cz_tooltip_up' : ( ( Codevz_Plus::$is_rtl || is_rtl() ) ? ' cz_tooltip_right' : ' cz_tooltip_left' );

			echo '<div class="xtra-product-icons' . $center . '" data-id="' . $product_id . '">';
			echo $wishlist ? '<i class="fa fa-heart-o xtra-add-to-wishlist" data-title="' . esc_html__( 'Add to Wishlist', 'codevz' ) . '"></i>' : '';
			echo $quick_view ? '<i class="fa czico-146-search-4 xtra-product-quick-view" data-title="' . esc_html__( 'Quick View', 'codevz' ) . '" data-nonce="' . wp_create_nonce( 'xtra_quick_view' ) . '"></i>' : '';
			echo '</div>';

		}

		$hover = Codevz_Plus::option( 'woo_hover_effect' );

		if ( $hover && class_exists( 'WC_Product' ) ) {

			$product = new WC_Product( $product_id );
			$attachment_ids = $product->get_gallery_image_ids();

			if ( is_array( $attachment_ids ) && isset( $attachment_ids[0] ) ) {

				echo '<div class="cz_image_in">';
				echo '<div class="cz_main_image">';

			}
		}
	}

	public static function woocommerce_before_shop_loop_item_title_high() {

		$hover = Codevz_Plus::option( 'woo_hover_effect' );

		if ( $hover && class_exists( 'WC_Product' ) ) {

			$product = new WC_Product( get_the_ID() );
			$attachment_ids = $product->get_gallery_image_ids();

			if ( is_array( $attachment_ids ) && isset( $attachment_ids[0] ) ) {

				echo '</div><div class="cz_hover_image">';

				echo Codevz_Plus::get_image( $attachment_ids[0], 'woocommerce_thumbnail' );

				echo '</div></div>';

			}

		}

		echo '</div>';
	}

	/**
	 * Quick view popup content.
	 * 
	 * @return string
	 */
	public static function wp_footer() {

		if ( Codevz_Plus::option( 'woo_quick_view' ) ) { ?>

			<a class="hidden xtra-qv-link" href="#xtra_quick_view"></a>

			<div id="cz_xtra_quick_view" class="cz_xtra_quick_view">

				<div id="xtra_quick_view" data-overlay-bg="" class="cz_popup_modal clr" data-popup="xtra_quick_view">
					<div class="cz_popup_in">
						<div></div>
					</div>

					<i class="fa czico-198-cancel cz_close_popup" style="color:#fff"></i>
					<div class="cz_overlay"></div>
				</div>
			</div>

		<?php }

	}

	/**
	 * Modify checkout page and add wrap to order details.
	 * 
	 * @return string
	 */
	public static function checkout_before() {
		echo '<div class="xtra-woo-checkout-details cz_sticky_col">';
	}

	/**
	 * Single product add wrap div.
	 * 
	 * @return string
	 */
	public static function before_single() {
		echo '<div class="xtra-single-product clr">';
	}

	public static function after_single() {
		echo '</div>';
	}

	/**
	 * Continue shopping button in cart page.
	 * 
	 * @return string
	 */
	public function continue_shopping() {
		echo '<a class="button wc-backward" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'codevz' ) . '</a>';
	}

}

// Run.
function xtra_woocommerce() {
	
	Xtra_Woocommerce::instance();

}
add_action( 'init', 'xtra_woocommerce', 11 );