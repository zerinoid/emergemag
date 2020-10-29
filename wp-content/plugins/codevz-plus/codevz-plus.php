<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Plugin Name: Codevz Plus
 * Plugin URI: https://xtratheme.com/
 * Description: StyleKit, custom post types, options and page builder elements.
 * Version: 3.9.10
 * Author: XtraTheme
 * Author URI: https://xtratheme.com/
 * Text Domain: codevz
 * Copyright: XtraTheme
*/

class Codevz_Plus {

	// Plugin version.
	public static $ver = '3.9.10';
	
	// Server API address.
	public static $api = 'https://xtratheme.com/api/';
	
	// Plugin title.
	public static $title;
	
	// Directory.
	public static $dir;
	
	// Plugin URL.
	public static $url;

	// Cache post query.
	public static $post;

	// RTL mode.
	public static $is_rtl = false;

	// Check admin pages.
	public static $is_admin = false;

	// Check WPBakery frontend.
	public static $vc_editable = false;

	// Get array list of pages.
	public static $array_pages = [];

	public static $social_fa_upgrade = [];

	// Instance of this class.
	private static $instance = null;

	// Core functionality.
	public function __construct() {

		// Define
		self::$post 		= &$GLOBALS['post'];
		self::$vc_editable 	= ( isset( $_GET['vc_editable'] ) || isset( $_GET['preview_id'] ) || get_option( 'wpm_languages' ) );
		self::$is_admin 	= is_admin();

		self::$title 		= esc_html__( 'XTRA', 'codevz' );
		self::$dir 			= trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$url 			= trailingslashit( plugin_dir_url( __FILE__ ) );

		self::$is_rtl 		= ( self::option( 'rtl' ) || is_rtl() || isset( $_GET['rtl'] ) );

		// Get list of all pages as array.
		if( self::$is_admin || is_customize_preview() ) {

			self::$array_pages = [
				'' => esc_html__( '---- DISABLE ----', 'codevz' )
			];
			$pages = (array) get_posts( 'post_type="page"&numberposts=-1' );
			foreach( $pages as $page ) {
				if ( isset( $page->post_title ) && $page->post_title ) {
					self::$array_pages[ $page->post_title ] = $page->post_title;
				}
			}

		}

		// Fix font awesome upgrade.
		self::$social_fa_upgrade = [ 'fa ', 'far ', 'fas ', 'fab ', 'fa-', 'fas-', 'far-', 'fab-', 'czico-', '-square', '-official', '-circle' ];

		// Include files.
		require_once( self::$dir . 'admin/csf.php' );
		require_once( self::$dir . 'classes/class-options.php' );
		require_once( self::$dir . 'classes/class-widgets.php' );
		require_once( self::$dir . 'classes/class-menu-walker.php' );
		require_once( self::$dir . 'classes/class-auto-update.php' );
		require_once( self::$dir . 'classes/class-woocommerce.php' );
		require_once( self::$dir . 'classes/class-wpbakery.php' );

		// Check features.
		$disable = array_flip( (array) self::option( 'disable' ) );

		// Demo importer.
		if( ! isset( $disable['importer'] ) ) {
			require_once( self::$dir . 'classes/class-demo-importer.php' );
		}

		// Presets.
		if( ! isset( $disable['presets'] ) ) {
			require_once( self::$dir . 'classes/class-presets.php' );
		}

		// Templates.
		if( ! isset( $disable['templates'] ) ) {
			require_once( self::$dir . 'classes/class-templates.php' );
		}

		// Lazyload
		$lazyload = self::option( 'lazyload' );

		// WP Lazyload 5.5.x
		if ( $lazyload != 'wp' ) {
			add_filter( 'wp_lazy_loading_enabled', '__return_false' );
		}

		// jQuery lazyload
		if ( ! self::$vc_editable && $lazyload == 'true' ) {
			$lazyload = [ $this, 'lazyload' ];
			add_filter( 'the_content', $lazyload, 999 );
			add_filter( 'widget_text', $lazyload, 999 );
			add_filter( 'wp_nav_menu_items', $lazyload, 999 );
			add_filter( 'post_thumbnail_html', $lazyload, 99999 );
		}

		// Query args & types of assets
		add_filter( 'script_loader_src', [ $this, 'remove_query_args' ], 15, 1 );
		add_filter( 'style_loader_src',  [ $this, 'remove_query_args' ], 15, 1 );
		add_filter( 'style_loader_tag',  [ $this, 'remove_type_attr' ], 10, 2 );
		add_filter( 'script_loader_tag', [ $this, 'remove_type_attr' ], 10, 2 );

		// Delete emoji for performance
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		
		// do_shortcode
		add_filter( 'widget_text', 'do_shortcode' );
		
		// Custom sidebars
		add_action( 'wp_ajax_codevz_custom_sidebars', [ $this, 'custom_sidebars' ] );

		// Custom default colors to WP Colorpicker
		add_action( 'admin_footer', [ $this, 'wp_color_palettes' ] );
		add_action( 'customize_controls_print_footer_scripts', [ $this, 'wp_color_palettes' ] );

		// Redirect maintenance pagefunction maintenance_mode() {
		//add_action( 'get_header', [ $this, 'maintenance_mode' ] );
		add_filter( 'template_redirect', [ $this, 'maintenance_mode' ] );

		// Ajax search result
		add_action( 'wp_ajax_codevz_ajax_search', [ $this, 'ajax_search' ] );
		add_action( 'wp_ajax_nopriv_codevz_ajax_search', [ $this, 'ajax_search' ] );

		// Post types query settings
		add_action( 'pre_get_posts', [ $this, 'action_pre_get_posts' ], 99 );

		// Filter HTML output of widgets
		add_filter( 'wp_list_categories', [ $this, 'wp_list_categories' ] );
		add_filter( 'get_archives_link',  [ $this, 'get_archives_link' ] );

		// Actions and filters
		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'save_post', array( $this, 'save_post' ), 9999 );

		// Body custom classes
		add_filter( 'body_class', array( $this, 'body_class' ) );

		// Head and footer
		add_action( 'wp_head', array( $this, 'wp_head' ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );

		if( ! isset( $disable['options'] ) ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 99 );
		}

		// Share icons.
		add_action( 'xtra_share', [ $this, 'share' ] );
		add_action( 'woocommerce_share', [ $this, 'share' ] );

		// Plugin white label.
		add_filter( 'all_plugins', [ $this, 'white_label' ] );

		// Disable autoptimize on page builder.
		add_filter( 'autoptimize_filter_noptimize', [ $this, 'vc_autoptimize' ], 10, 0 );

	}

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get page settings
	 * 
	 * @var $id = post id
	 * @var $key = array key
	 * @return array|string|null
	 */
	public static function meta( $id = null, $key = null ) {

		if ( ! $id && isset( self::$post->ID ) ) {
			$id = self::$post->ID;
		}

		$meta = get_post_meta( $id, 'codevz_page_meta', true );

		if ( $key ) {
			return isset( $meta[ $key ] ) ? $meta[ $key ] : 0;
		} else {
			return $id ? $meta : '';
		}
	}

	/**
	 * Get theme options
	 * 
	 * @return array|string|null
	 */
	public static function option( $key = '', $default = '' ) {
		$all = (array) get_option( 'codevz_theme_options' );

		if ( isset( $_GET['rtl'] ) ) {
			$all[ 'rtl' ] = 1;
		}

		return $key ? ( empty( $all[ $key ] ) ? $default : $all[ $key ] ) : $all;
	}

	/**
	 * Add social share icons to post, page and products.
	 * 
	 * @return String
	 */
	public function share() {

		$share = self::option( 'share' );
		$post_type = array_flip( (array) self::option( 'post_type' ) );

		if ( empty( $share ) || ! isset( $post_type[ self::get_post_type() ] ) ) {
			return false;
		}

		$classes = 'cz_social xtra-share';
		$classes .= self::option( 'share_color' ) ? ' ' . self::option( 'share_color' ) : '';
		$classes .= self::option( 'share_title' ) ? ' cz_social_inline_title' : '';

		$tooltip = self::option( 'share_tooltip' );
		$classes .= $tooltip ? ' cz_tooltip cz_tooltip_up' : '';

		$post_title = get_the_title();
		$post_link  = get_the_permalink();
		$post_link  = get_the_permalink();

		$url = [

			'facebook-f' => [
				'title' => esc_html__( 'Facebook', 'codevz' ),
				'url' 	=> 'https://facebook.com/share.php?u=' . $post_link . '&title=' . $post_title
			],
			'twitter' => [
				'title' => esc_html__( 'Twitter', 'codevz' ),
				'url' 	=> 'https://twitter.com/intent/tweet?text=' . $post_title . '+' . $post_link
			],
			'pinterest' => [
				'title' => esc_html__( 'Pinterest', 'codevz' ),
				'url' 	=> 'https://pinterest.com/pin/create/bookmarklet/?media=' . get_the_post_thumbnail_url( get_the_id(), 'full' ) . '&url=' . $post_link . '&is_video=false&description=' . $post_title
			],
			'reddit' => [
				'title' => esc_html__( 'Reddit', 'codevz' ),
				'url' 	=> 'https://reddit.com/submit?url=' . $post_link . '&title=' . $post_title
			],
			'delicious' => [
				'title' => esc_html__( 'Delicious', 'codevz' ),
				'url' 	=> 'https://del.icio.us/post?url=' . $post_link . '&title=' . $post_title . '&notes=' . strip_tags( get_the_excerpt() )
			],
			'linkedin' => [
				'title' => esc_html__( 'Linkedin', 'codevz' ),
				'url' 	=> 'https://linkedin.com/shareArticle?mini=true&url=' . $post_link . '&title=' . $post_title . '&source=' . $post_link
			],
			'whatsapp' => [
				'title' => esc_html__( 'Whatsapp', 'codevz' ),
				'url' 	=> 'whatsapp://send?text=' . $post_title . ' ' . $post_link
			],
			'telegram' => [
				'title' => esc_html__( 'Telegram', 'codevz' ),
				'url' 	=> 'https://telegram.me/share/url?url=' . $post_link . '&text=' . $post_title
			],
			'envelope' => [
				'title' => esc_html__( 'Email', 'codevz' ),
				'url' 	=> 'mailto:?body=' . $post_title . ' ' . $post_link
			],
			'print' => [
				'title' => esc_html__( 'Print', 'codevz' ),
				'url' 	=> '#'
			],
			'copy' => [
				'title' => esc_html__( 'Shortlink', 'codevz' ),
				'url' 	=> wp_get_shortlink( get_the_id() )
			],
		
		];

		echo '<div class="clr mb10"></div>';

		echo '<div class="' . esc_attr( $classes ) . '">';

		// Echo share icons.
		foreach( $share as $name ) {

			$name = ( $name === 'facebook' ) ? 'facebook-f' : $name;

			if ( isset( $url[ $name ] ) ) {

				$title_prefix = ( self::contains( $name, [ 'envelope', 'whatsapp', 'telegram' ] ) ) ? esc_html__( 'Share by', 'codevz' ) : esc_html__( 'Share on', 'codevz' );
				$title_prefix = ( self::contains( $name, [ 'copy' ] ) ) ? esc_html__( 'Copy', 'codevz' ) . ' ' : $title_prefix;
				$title_prefix = ( $name === 'print' ) ? '' : $title_prefix;
				$icon_prefix = ( $name === 'envelope' || $name === 'print' ) ? 'fa' : 'fab';
				$icon_prefix = ( $name === 'copy' ) ? 'far' : $icon_prefix;
				$custom_data = ( $name === 'copy' ) ? ' data-copied="' . esc_html__( 'Link copied', 'codevz' ) . '"' : '';

				echo '<a href="' . $url[ $name ]['url'] . '" class="cz-' . $name . '" ' . ( $tooltip ? 'data-' : '' ) . 'title="' . esc_attr( $title_prefix . ' ' . $url[ $name ]['title'] ) . '"' . $custom_data . '><i class="' . $icon_prefix . ' fa-' . $name . '"></i><span>' . esc_html( $url[ $name ]['title'] ) . '</span></a>';

			}

		}

		echo '</div>';

	}

	/**
	 * Disable autoptimize on frontend page builder.
	 * @return boolean
	 */
	public static function vc_autoptimize() {
		if ( self::$vc_editable ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * New shortcut menus to WP admin bar
	 * @var object of WP admin bar
	 * @return object
	 */
	public static function admin_bar_menu( $i ) {
		$admin = get_admin_url();
		$customize = $admin . 'customize.php?url=' . urlencode( esc_url( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) ) . '&';
		
		$i->add_node(array(
			'id' 	=> 'codevz_menu',
			'title' => esc_html__( 'Theme Options', 'codevz' ), 
			'href' 	=> $customize
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_demos',
			'title' => esc_html__( 'Demo Importer', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-demos',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_favicon',
			'title' => esc_html__( 'Site Favicon', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=title_tagline',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_layout',
			'title' => esc_html__( 'Layout', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-layout',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_colors',
			'title' => esc_html__( 'Theme Colors', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-styling',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_typography',
			'title' => esc_html__( 'Typography', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-typography',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_logo',
			'title' => esc_html__( 'Site Logo', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[control]=codevz_theme_options[logo]',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_header',
			'title' => esc_html__( 'Header', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-header',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_mobile_header',
			'title' => esc_html__( 'Mobile Header', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-mobile_header',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_title',
			'title' => esc_html__( 'Title & Breadcrumbs', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-title_br',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_back_to_top',
			'title' => esc_html__( 'Back to top icon', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-footer_more',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_footer',
			'title' => esc_html__( 'Footer', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-footer',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_copyright',
			'title' => esc_html__( 'Copyright text', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-footer_2',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_posts',
			'title' => esc_html__( 'Blog Settings', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-posts',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_portfolio',
			'title' => esc_html__( 'Portfolio Settings', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-portfolio',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_product',
			'title' => esc_html__( 'WooCommerce', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-product',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_custom_css',
			'title' => esc_html__( 'Additional CSS', 'codevz' ), 
			'href' 	=> $customize . 'autofocus[section]=custom_css',
		));

		$mt = self::option( 'maintenance_mode' );
		if ( $mt && $mt !== 'none' ) {
			$i->add_node(array(
				'id' 	=> 'codevz_menu_maintenance',
				'title' => esc_html__( 'Maintenance mode is ON', 'codevz' ), 
				'href' 	=> $customize . 'autofocus[control]=codevz_theme_options[maintenance_mode]',
			));
		}
		
		$i->remove_menu( 'customize' );
	}

	/**
	 * Body Classes
	 * 
	 * @return string
	 */
	public static function body_class( $c = [] ) {

		// Post type class
		$cpt = self::get_post_type();
		$cpt = $cpt ? $cpt : get_post_type();
		$cpt = ( ! $cpt || $cpt === 'page' || is_search() ) ? 'post' : $cpt;
		if ( $cpt ) {
			$c[] = 'cz-cpt-' . $cpt;

			// Woo single
			if ( is_single() && $cpt === 'product' ) {

				$tabs = self::option( 'woo_product_tabs' );
				if ( $tabs ) {
					$c[] = 'woo-product-tabs-' . $tabs;
				}
				
				if ( in_array( 'lightbox', (array) self::option( 'woo_gallery_features' ) ) ) {
					$c[] = 'woo-disable-lightbox';
				}

				//if ( self::option( 'woo_single_add_to_cart_ajax' ) ) {
				//	$c[] = 'woo-single-ajax-add-to-cart';
				//}
			}
		}

		// RTL
		$c[] = self::$is_rtl ? 'rtl' : '';

		// Sticky
		$c[] = self::option( 'sticky' ) ? 'cz_sticky' : '';

		// Disable lightbox
		$disable = array_flip( (array) self::option( 'disable' ) );
		$c[] = isset( $disable['lightbox'] ) ? 'no_lightbox' : '';

		// Theme version.
		$theme = wp_get_theme();
		if ( ! empty( $theme->get( 'Version' ) ) ) {
			$c[] = 'xtra-' . $theme->get( 'Version' );
		}

		// Plugins version.
		$c[] = 'codevz-plus-' . self::$ver;

		// Fix
		$c[] = 'clr';

		// Page ID
		if ( get_the_id() ) {
			$c[] = 'cz-page-' . get_the_id();
		}

		return $c;
	}

	/**
	 * wp_head
	 * 
	 * @return string
	 */
	public static function wp_head() {

		// Disable automatic telephone link for mobile.
		echo apply_filters( 'xtra_telephone_meta', '<meta name="format-detection" content="telephone=no">' . "\n" );

		// SEO meta tags
		if ( ! self::$vc_editable && self::option( 'seo_meta_tags' ) && ! defined( 'WPSEO_VERSION' ) ) {

			$title = $desc = $tags = '';

			if ( is_single() || is_page() ) {
				$url = get_the_permalink();
				$title = get_the_title();
				$desc = self::meta( false, 'seo_desc' );
				if ( ! $desc ) {
					$desc = apply_filters( 'the_content', self::$post->post_content );
					$desc = $desc ? wp_trim_words( do_shortcode( strip_tags( $desc ) ), 30 ) : $title;
				}
				$tags = self::meta( false, 'seo_keywords' );
				$tags = $tags ? $tags : rtrim( strip_tags( str_replace( '</a>', ',', get_the_tag_list() ) ), ',' );
				$image = get_the_post_thumbnail_url();
				echo $image ? '<meta property="og:image" content="' . $image . '" />' . "\n" : '';
			} else {
				global $wp;
				$url = home_url( $wp->request );
			}

			$title = $title ? $title : get_bloginfo( 'name' );
			$desc = $desc ? $desc : self::option( 'seo_desc', get_bloginfo( 'description' ) );
			$desc = trim( preg_replace( '/\s+/', ' ', strip_tags( $desc ) ) );
			$tags = $tags ? $tags : self::option( 'seo_keywords' );

			echo '<meta property="og:title" content="' . strip_tags( $title ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
			echo '<meta name="description" content="' . $desc . '">' . "\n";
			echo $tags ? '<meta name="keywords" content="' . strip_tags( $tags ) . '">' . "\n" : '';
			echo '<meta property="og:description" content="' . $desc . '" />' . "\n";
			echo '<meta property="og:type" content="website" />' . "\n";

		}

		// Custom header codes
		echo str_replace( '&', '&amp;', do_shortcode( self::option( 'head_codes' ) ) );
	}

	/**
	 * Site footer.
	 * 
	 * @return string
	 */
	public function wp_footer() {

		do_action( 'codevz_hook_end_body' );

		echo str_replace( '&', '&amp;', do_shortcode( self::option( 'foot_codes' ) ) );

	}

	/**
	 * Get shortcode from page ID + Generate styles
	 * 
	 * @var post ID
	 * @return string
	 */
	public static function get_page_as_element( $id = '', $query = 0 ) {

		// Escape
		$id = esc_html( $id );

		// Check
		if ( ! $id ) {
			return;
		}

		// Check number and 404.
		if ( ! is_numeric( $id ) || $id === '404' ) {
			$page = get_page_by_title( $id, 'object', 'page' );
			if ( isset( $page->ID ) && ! is_page( $page->ID ) ) {
				$id = $page->ID;
			} else {
				return;
			}
		}

		// If post not exist
		if ( ! get_post_status( $id ) ) {
			return;
		}

		// WPML compatible
		if ( function_exists( 'icl_object_id' ) ) {
			$id = icl_object_id( $id, 'page', true, ICL_LANGUAGE_CODE );
		}

		// Get post content by ID
		$o = get_post_field( 'post_content', $id );

		// Fix posts grid
		if ( $query ) {
			$o = str_replace( 'query=""', 'query="1"', $o );
		}
		
		// Get post meta
		$s = get_post_meta( $id, '_wpb_shortcodes_custom_css', 1 ) . get_post_meta( $id, 'cz_sc_styles', 1 ) . get_post_meta( $id, 'codevz_single_page_css', 1 );

		// Responsive page builder tablet styles
		$tablet = get_post_meta( $id, 'cz_sc_styles_tablet', 1 );
		if ( $tablet ) {
			if ( substr( $tablet, 0, 1 ) === '@' ) {
				$s .= $tablet;
			} else {
				$s .= '@media screen and (max-width:' . self::option( 'tablet_breakpoint', '768px' ) . '){' . $tablet . '}';
			}
		}

		// Responsive page builder mobile styles
		$mobile = get_post_meta( $id, 'cz_sc_styles_mobile', 1 );
		if ( $mobile ) {
			if ( substr( $mobile, 0, 1 ) === '@' ) {
				$s .= $mobile;
			} else {
				$s .= '@media screen and (max-width:' . self::option( 'mobile_breakpoint', '480px' ) . '){' . $mobile . '}';
			}
		}

		// Output
		if ( ! is_page( $id ) ) {
			$o = "<div data-cz-style='" . esc_attr( preg_replace( "/(.cz-page-)(.*)[{]/", "{", $s ) ) . "'>" . do_shortcode( $o ) . "</div>";
		} else {
			return;
		}

		return $o;
	}

	/**
	 * Get current post type name
	 * 
	 * @return string
	 */
	public static function get_post_type( $id = '', $page = false ) {

		if ( is_search() || is_tag() || is_404() ) {
			$cpt = '';
		} else if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
			$cpt = 'bbpress';
		} else if ( function_exists( 'is_woocommerce' ) && ( is_shop() || is_woocommerce() ) ) {
			$cpt = 'product';
		} else if ( function_exists( 'is_buddypress' ) && is_buddypress() ) {
			$cpt = 'buddypress';
		} else if ( ( ! $page && get_post_type( $id ) ) || is_singular() ) {
			$cpt = get_post_type( $id );
		} else if ( is_tax() ) {
			$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			if ( get_taxonomy( $term->taxonomy ) ) {
				$cpt = get_taxonomy( $term->taxonomy )->object_type[0];
			}
		} else if ( is_post_type_archive() ) {
			$cpt = get_post_type_object( get_query_var( 'post_type' ) )->name;
		} else {
			$cpt = 'post';
		}

		return $cpt;
	}

	/**
	 * WordPress init
	 * 
	 * @return object
	 */
	public function init() {

		// Menu navigation locations
		register_nav_menus(array(
			'primary' 	=> esc_html__( 'Primary', 'codevz' ), 
			'one-page' 	=> esc_html__( 'One Page', 'codevz' ), 
			'secondary' => esc_html__( 'Secondary', 'codevz' ), 
			'footer'  	=> esc_html__( 'Footer', 'codevz' ),
			'mobile'  	=> esc_html__( 'Mobile', 'codevz' ),
			'custom-1' 	=> esc_html__( 'Custom 1', 'codevz' ), 
			'custom-2' 	=> esc_html__( 'Custom 2', 'codevz' ), 
			'custom-3' 	=> esc_html__( 'Custom 3', 'codevz' )
		));

		// Register CPTs
		self::post_types();

		// Enqueue and register plugin assets
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts_1' ), 1 );
		if ( ! isset( $_POST['vc_inline'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts_11a' ), 11 );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts_11' ), 11 );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts_999' ), 999 );
		}

		// Admin assets for Presets, StyleKit and Theme colors for palettes
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Custom JS/CSS for VC popup box
		add_filter( 'vc_edit_form_fields_after_render', array( $this, 'vc_edit_form_fields_after_render' ) );
		
		// Enable some features for WP Editor
		add_filter( 'mce_buttons_2', array( $this, 'mce_buttons_2' ) );

		// Customize some features of WP Editor
		add_filter( 'tiny_mce_before_init', array( $this, 'tiny_mce_before_init' ) );

		// New Params for VC
		if ( function_exists( 'vc_add_shortcode_param' ) ) {
			vc_add_shortcode_param( 'cz_title', array( $this, 'vc_param_cz_title' ) );
			vc_add_shortcode_param( 'cz_sc_id', array( $this, 'vc_param_cz_sc_id' ) );
			vc_add_shortcode_param( 'cz_hidden', array( $this, 'vc_param_cz_hidden' ) );
			vc_add_shortcode_param( 'cz_presets', array( $this, 'vc_param_cz_presets' ) );
			vc_add_shortcode_param( 'cz_sk', array( $this, 'vc_param_cz_sk' ) );
			vc_add_shortcode_param( 'cz_upload', array( $this, 'vc_param_cz_upload' ) );
			vc_add_shortcode_param( 'cz_icon', array( $this, 'vc_param_cz_icon' ) );
			vc_add_shortcode_param( 'cz_image_select', array( $this, 'vc_param_image_select' ) );
			vc_add_shortcode_param( 'cz_slider', array( $this, 'vc_param_cz_slider' ) );
		}

		// Filter for moving animation param into new tab Animation
		add_filter( 'vc_map_add_css_animation', array( $this, 'vc_map_add_css_animation' ) );

		// Useful shortcodes
		add_shortcode( 'br', array( $this, 'br' ) );
		add_shortcode( 'cz_lang', array( $this, 'shortcode_translate' ) );
		add_shortcode( 'codevz_year', array( $this, 'shortcode_get_current_year' ) );
		add_shortcode( 'cz_current_year', array( $this, 'shortcode_get_current_year' ) );

		// Add loop animations to vc animations list
		add_filter( 'vc_param_animation_style_list', array( $this, 'vc_param_animation_style_list' ) );

		// Plugin Languages
		load_textdomain( 'codevz', self::$dir .'languages/'. get_locale() .'.mo' );
	}

	/**
	 * WPBakery custom params
	 */
	public static function vc_param_cz_title( $s, $v ) {
		$c = empty( $s['class'] ) ? '' : ' class="' . $s['class'] . '"';
		$u = empty( $s['url'] ) ? '' : '<a href="' . $s['url'] . '" target="_blank">';
		return $u . '<h4' . $c . '>' . $s['content'] . '</h4>' . ( $u ? '</a>' : '' ) . '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />';
	}

	public static function vc_param_cz_sc_id( $s, $v ) {
		return '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />';
	}

	public static function vc_param_cz_hidden( $s, $v ) {
		return '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />';
	}

	public static function vc_param_cz_presets( $s, $v ) {
		return '<div class="cz_presets clr ' . $s['class'] . '" data-presets="' . $s['param_name'] . '"><div class="cz_presets_loader"></div></div>';
	}

	public static function vc_param_cz_sk( $s, $v ) {
		$hover = isset( $s['hover_id'] ) ? ' data-hover_id="' . $s['hover_id'] . '"' : '';
		$out = '<div class="cz_sk clr"><input type="hidden" name="'. $s['param_name'] . '"' . $hover . ' value="' . $v . '" class="csf-onload wpb_vc_param_value ' . esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ) . '" data-selector="' . ( isset( $s['selector'] ) ? $s['selector'] : '' ) . '" data-fields="' . implode( ' ', $s['settings'] ) . '" />';
		
		$is_active = $v ? ' active_stylekit' : '';
		
		$bg = '';
		if ( self::contains( $v, 'http' ) ) {
			preg_match_all( '/(http|https):\/\/[^ ]+(\.gif|\.jpg|\.jpeg|\.png)/', $v, $img );
			$bg = isset( $img[0][0] ) ? ' style="background-image:url(' . $img[0][0] . ')"' : '';
		}

		$out .= '<a href="#" class="button cz_sk_btn' . $is_active . '"><span class="cz_skico cz_skico_vc"></span>' . $s['button'] . '</a><div class="sk_btn_preview_image"' . $bg . '></div></div>';


		return $out;
	}

	public static function vc_param_cz_upload( $s, $v ) {
		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'upload',
			'title' => '',
			'attributes' => array(
				'class' => 'csf-onload wpb_vc_param_value '.esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ).''
			),
			'settings'   => array(
				'upload_type'  => esc_attr( $s['upload_type'] ),
				'frame_title'  => 'Upload / Select',
				'insert_title' => 'Insert',
			),
		);

		if ( function_exists('csf_add_field') ) {
			return '<div class="csf-onload">' . csf_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}
	}

	public static function vc_param_cz_icon( $s, $v ) {
		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'icon',
			'title' => '',
			'after'	=> '<input type="hidden" name="'.$s['param_name'].'" class="wpb_vc_param_value '.$s['param_name'].' '.$s['type'].'_field" value="'.$v.'" />',
			'attributes' => array(
				'class' => 'csf-onload wpb_vc_param_value '.esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ).''
			),
		);

		if ( function_exists('csf_add_field') ) {
			return '<div class="csf-onload">' . csf_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}
	}

	public static function vc_param_image_select( $s, $v ) {
		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'image_select',
			'options' => isset( $s['options'] ) ? $s['options'] : [],
			'radio' => true,
			'title' => '',
			'after'	=> '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />',
			'attributes' => array(
				'class' 			=> 'csf-onload',
				'data-depend-id' 	=> esc_attr( $s['param_name'] )
			),
		);

		if ( function_exists('csf_add_field') ) {
			return '<div class="csf-onload">' . csf_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}
	}

	public static function vc_param_cz_slider( $s, $v ) {
		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'slider',
			'options' => isset( $s['options'] ) ? $s['options'] : array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 120 ),
			'title' => '',
			'after'	=> '<input type="hidden" name="'.$s['param_name'].'" class="wpb_vc_param_value '.$s['param_name'].' '.$s['type'].'_field" value="'.$v.'" />',
			'attributes' => array(
				'class' => 'csf-onload wpb_vc_param_value '.esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ).''
			),
		);

		if ( function_exists('csf_add_field') ) {
			return '<div class="csf-onload">' . csf_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}
	}

	/**
	 * Enqueue and register plugin assets
	 * 
	 * @return string
	 */
	public static function wp_enqueue_scripts_1() {

		// Update plugin version.
		if( version_compare( get_option( 'xtra_plugin_version' ), self::$ver, '<' ) ) {

			// Autoptimize fix.
			if( class_exists( 'autoptimizeCache' ) ) {
				autoptimizeCache::clearall();
			}

			update_option( 'xtra_plugin_version', self::$ver );
		}

		wp_register_script( 'codevz-particles', self::$url . 'assets/js/particles.min.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-slick', self::$url . 'assets/js/slick.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-grid', self::$url . 'assets/js/grid.js', [ 'jquery' ], self::$ver, true );

		wp_register_script( 'codevz-soundmanager', self::$url . 'assets/soundmanager/script/soundmanager.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-bar-ui', self::$url . 'assets/soundmanager/script/bar-ui.js', [ 'jquery' ], self::$ver, true );
		wp_register_style(  'codevz-bar-ui', self::$url . 'assets/soundmanager/css/bar-ui.css' );

		wp_register_script( 'codevz-tooltip', self::$url . 'assets/js/tooltips.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-countdown', self::$url . 'assets/js/countdown.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-modernizer', self::$url . 'assets/js/modernizer.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-360-degree', self::$url . 'assets/js/360_degree.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-animated-text', self::$url . 'assets/js/animated_text.js', [ 'jquery' ], self::$ver, true );
		wp_register_script( 'codevz-marquee', self::$url . 'assets/js/marquee.js', [ 'jquery' ], self::$ver, true );
	
	}

	public static function wp_enqueue_scripts_11a() {

		// Woocommerce
		if ( function_exists( 'is_woocommerce' ) ) {
			wp_enqueue_style( 'xtra-woocommerce', self::$url . 'assets/css/woocommerce.css', [], self::$ver );
		}
		
		// DWQA plugin
		if ( function_exists( 'dwqa' ) ) {
			wp_enqueue_style( 'xtra-dwqa', self::$url . 'assets/css/dwqa.css', [], self::$ver );
		}

	}

	public static function wp_enqueue_scripts_11() {

		wp_enqueue_script( 'codevz-plugin', self::$url . 'assets/js/codevzplus.js', [ 'jquery' ], self::$ver, true );
		wp_enqueue_style( 'codevz-plugin', self::$url . 'assets/css/codevzplus.css', false, self::$ver );

		// Custom JS
		$js = self::option( 'js' );
		if ( $js ) {
			wp_add_inline_script( 'codevz-plugin', 'jQuery(document).ready(function($) {' . $js . '});' );
		}

		wp_localize_script( 'codevz-plugin', 'xtra_strings', array(
			'wishlist_url' 		=> get_site_url() . '/wishlist',
			'add_wishlist' 		=> esc_html__( 'Add to Wishlist', 'codevz' ),
			'added_wishlist' 	=> esc_html__( 'Added to Wishlist', 'codevz' )
		) );
	}

	public static function wp_enqueue_scripts_999() {

		$post_id = isset( self::$post->ID ) ? self::$post->ID : get_the_id();

		if ( $post_id && ! self::$vc_editable ) {

			// Page builder styles.
			$styles = get_post_meta( $post_id, 'cz_sc_styles', 1 );

			// If dynamic styles is empty, regenerate it again.
			if ( ! $styles && is_page() ) {

				$content = get_post_field( 'post_content', $post_id );

				if ( self::contains( $content, 'sk_' ) ) {
				
					// Regenrate dynamic styles.
					self::$vc_editable = true;
					self::save_post( $post_id );
					
					$styles = get_post_meta( $post_id, 'cz_sc_styles', 1 );

				}

			}

			// Responsive page builder tablet styles
			$tablet = get_post_meta( $post_id, 'cz_sc_styles_tablet', 1 );
			if ( $tablet ) {
				if ( self::contains( $tablet, '@media' ) ) {
					$styles .= $tablet;
				} else {
					$styles .= '@media screen and (max-width:' . self::option( 'tablet_breakpoint', '768px' ) . '){' . $tablet . '}';
				}
			}

			// Responsive page builder mobile styles
			$mobile = get_post_meta( $post_id, 'cz_sc_styles_mobile', 1 );
			if ( $mobile ) {
				if ( self::contains( $mobile, '@media' ) ) {
					$styles .= $mobile;
				} else {
					$styles .= '@media screen and (max-width:' . self::option( 'mobile_breakpoint', '480px' ) . '){' . $mobile . '}';
				}
			}

			wp_add_inline_style( 'codevz-plugin', "\n\n/* PageBuilder */" . $styles );
		}
	}

	/**
	 *
	 * Custom JS/CSS for VC popup box
	 * 
	 * @return string
	 * 
	 */
	public static function vc_edit_form_fields_after_render( $output = '' ) {

		$echo = $output ? false : true;

		$body_font = self::option( '_css_body_typo' );
		
		$body_font = empty( $body_font[0]['font-family'] ) ? '' : $body_font[0]['font-family'];
		
		$body_font = explode( ':', $body_font );
		
		ob_start(); ?>

		<script type="text/javascript">

			(function($){

				$( '.wpb_edit_form_elements' ).csf_reload_script();

				$( '.vc_param_group-list' ).on( 'click', function() {
					var en = $( this );
					setTimeout(function() {
						$( '.vc_param', en ).each(function() {
							$( this ).csf_reload_script();
						});
					}, 4000 );
				});

				setTimeout(function() {
					$( '#wpb_tinymce_content_ifr' ).contents().find( 'body' ).css({
						'background': 'rgba(167, 167, 167, 0.25)',
						'font-family': '<?php echo empty( $body_font[0] ) ? 'Open Sans' : $body_font[0]; ?>'
					});

					<?php 

						$disable = array_flip( (array) self::option( 'disable' ) );

						if ( ! isset( $disable['videos'] ) ) {
					?>

					// Elements video turoials
					var el = $( '[data-vc-shortcode]' ).attr( 'data-vc-shortcode' );
					var videos = {
						cz_2_buttons: 'FFCoaubH34M',
						cz_360_degree: 'AQTj8-bSHnI',
						cz_accordion:'VYzFWA_4iCM',
						cz_animated_text:'qbclDC43uS8',
						cz_banner:'l3ee8IIXbzA',
						cz_before_after:'cQCRTkNsB9I',
						cz_button:'TWkG6HtdSoo',
						cz_carousel:'R_iFLdOv2E8',
						cz_contact_form_7:'eIZa-QfOPWo',
						cz_content_box:'t26HZ_9tJ2c',
						cz_countdown:'R20yLL03jQI',
						cz_counter:'I9-Rjkygpmw',
						cz_free_line:'B3PyMvibmvA',
						cz_free_position_element:'0js4hNd-kh8',
						cz_gallery:'j5tD0NRSw7g',
						cz_gap:'s4M2nD2Pq9M',
						cz_gradient_title:'S5fzvQ3wO0g',
						cz_history_line:'n-p0416Qtnw',
						cz_hotspot:'QDPdMrVP0WA',
						cz_image:'Tw8SfSGRQdY',
						cz_image_hover_zoom:'yk05SzAovfM',
						cz_login_register:'t2K2Jp8LbHA',
						cz_music_player:'ajdB15T7Eos',
						cz_news_ticker:'wK3G2RtnAl8',
						cz_parallax_group:'MApojPfkwXk',
						cz_particles:'4Fxr4fAKYmM',
						cz_popup:'5QL5_EGEMTE',
						cz_posts:'lU0gjnueZDI',
						cz_process_line_vertical:'EE8MZbbJixw',
						cz_process_road:'eY5UM0ucfOE',
						cz_progress_bar:'XDoUabdAVn0',
						cz_quote:'nSRgDyiMm0U',
						cz_separator:'UzVfzx1w75M',
						cz_service_box:'biplj6KgTrU',
						cz_show_more_less:'4CeGd5Z-oZs',
						cz_social_icons:'kmJ82T9TISk',
						cz_stylish_list:'ANbqrPdkj1o',
						cz_svg:'aNgPan2wmHk',
						cz_tabs:'7PmbBFXMi6A',
						cz_team:'_94XN1VnYMA',
						cz_testimonials:'IeCYG7y3fUk',
						cz_timeline:'7ZPnUppKEi0',
						cz_title:'NRMXChwRxto',
						cz_video_popup:'ugEf_JIY6JY',
						cz_working_hours:'JQm3m71pTr0',
					};

					if ( videos[ el ] != 'undefined' && videos[ el ] ) {
						if ( ! $( '.cz_video_tutorial' ).length ) {
							$( '.vc_ui-dropdown-trigger' ).before( '<a class="cz_video_tutorial" target="_blank" href="https://www.youtube.com/watch?v=' + videos[ el ] + '"><i class="fa fa-play"></i> <?php esc_html_e( 'Video Tutorial', 'codevz' ); ?></a>' );
						} else {
							$( '.cz_video_tutorial' ).attr( 'href', 'https://www.youtube.com/watch?v=' + videos[ el ] );
						}
					}

					<?php } ?>

				}, 200 );

			})(jQuery);

		</script>

	<?php 

		$output .= ob_get_clean();

		if ( $echo ) {
			echo $output;
		} else {
			return $output;
		}
	}

	/**
	 *
	 * Enable some features for WP Editor
	 * 
	 * @param $i is array of default WP Editor features
	 * @return array
	 * 
	 */ 
	public static function mce_buttons_2( $i ) {
		array_shift( $i );
		array_unshift( $i, 'styleselect', 'fontselect', 'fontsizeselect', 'backcolor' );

		return $i;
	}

	/**
	 *
	 * Customize some features of WP Editor
	 * 
	 * @param $i is array of default WP Editor features values
	 * @return array
	 * 
	 */
	public static function tiny_mce_before_init( $i ) {
		$i['fontsize_formats'] = '6px 7px 8px 9px 10px 11px 12px 13px 14px 15px 16px 17px 18px 19px 20px 22px 24px 26px 28px 30px 32px 34px 36px 38px 40px 42px 44px 46px 48px 50px 52px 54px 56px 58px 60px 62px 64px 66px 68px 70px 72px 74px 76px 78px 80px 82px 84px 86px 88px 90px 92px 94px 96px 98px 100px 102px 104px 106px 108px 110px 120px 130px 140px 150px 160px 170px 180px 190px 200px 1em 2em 3em 4em 5em 6em 7em 8em 9em 10em 11em 12em 13em 14em 15em 16em 17em 18em 19em 20em';

		$primary_color = self::option( 'site_color', '#4e71fe' );
		$secondary_color = self::option( 'site_color_sec' );

			$colors = '"000000", "Black",
              "993300", "Burnt orange",
              "333300", "Dark olive",
              "003300", "Dark green",
              "003366", "Dark azure",
              "000080", "Navy Blue",
              "333399", "Indigo",
              "333333", "Very dark gray",
              "800000", "Maroon",
              "FF6600", "Orange",
              "808000", "Olive",
              "008000", "Green",
              "008080", "Teal",
              "0000FF", "Blue",
              "666699", "Grayish blue",
              "666666", "Gray",
              "FF0000", "Red",
              "FF9900", "Amber",
              "99CC00", "Yellow green",
              "339966", "Sea green",
              "33CCCC", "Turquoise",
              "3366FF", "Royal blue",
              "800080", "Purple",
              "AAAAAA", "Medium gray",
              "FF00FF", "Magenta",
              "FFCC00", "Gold",
              "FFFF00", "Yellow",
              "00FF00", "Lime",
              "00FFFF", "Aqua",
              "00CCFF", "Sky blue",
              "993366", "Red violet",
              "FFFFFF", "White",
              "FF99CC", "Pink",
              "FFCC99", "Peach",
              "FFFF99", "Light yellow",
              "CCFFCC", "Pale green",
              "CCFFFF", "Pale cyan"';

		$colors .= ',"' . $primary_color . '", "Primary Color"';
		$colors .= $secondary_color ? ',"' . $secondary_color . '", "Secondary Color"' : '';

		// Build colour grid default+custom colors
		$i['textcolor_map'] = '[' . str_replace( '#', '', $colors ) . ']';
		$i['textcolor_rows'] = 6;

		// Fonts for WP Editor from theme options
		$i['font_formats'] = get_option( 'codevz_wp_editor_google_fonts' );

		// New style_formats
		$style_formats = array(
			array(
				'title' => esc_html__( '100 | Thin', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '100' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '200 | Extra Light', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '200' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '300 | Light', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '300' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '400 | Normal', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '400' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '500 | Medium', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '500' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '600 | Semi Bold', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '600' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '700 | Bold', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '700' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '800 | Extra Bold', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '800' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '900 | High Bold', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '900' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 0.6',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '0.6' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 0.8',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '0.8' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.1',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.1' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.2',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.2' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.3',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.3' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.4',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.4' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.5',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.5' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.6',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.6' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.7',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.7' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.8',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.8' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 1.9',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.9' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz' ) . ' 2',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '2' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' -2px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '-2px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' -1px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '-1px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 0px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '0px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 1px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '1px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 2px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '2px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 3px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '3px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 4px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '4px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 5px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '5px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 6px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '6px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 7px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '7px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 8px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '8px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz' ) . ' 10px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '10px' )
			),
			array(
				'title' => esc_html__( 'Margin 0px', 'codevz' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin' => '0px' )
			),
			array(
				'title' => esc_html__( 'Margin top 10px', 'codevz' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-top' => '10px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin top 20px', 'codevz' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-top' => '20px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin top 30px', 'codevz' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-top' => '30px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin bottom 10px', 'codevz' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-bottom' => '10px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin bottom 20px', 'codevz' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-bottom' => '20px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin bottom 30px', 'codevz' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-bottom' => '30px', 'display' => 'inline-block' )
			),
			array(
				'title'  => esc_html__( 'Highlight', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_highlight',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 7px 2px',
					'background' 	=> 'rgba(167, 167, 167, 0.26)',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Border solid', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_brsolid',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '4px 8px 5px',
					'border' 		=> '1px solid',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Border dotted', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_brdotted',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '4px 8px 5px',
					'border' 		=> '1px dotted',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Border dashed', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_brdashed',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '4px 8px 5px',
					'border' 		=> '1px dashed',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Underline', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_underline',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-bottom' => '1px solid'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Underline Dashed', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_underline cz_underline_dashed',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-bottom' => '1px dashed'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Topline', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_topline',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-top' 	=> '1px solid'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Topline Dashed', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_topline cz_topline_dashed',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-top' 	=> '1px dashed'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Blockquote Center', 'codevz' ),
				'inline' => 'span',
				'classes' => 'blockquote',
				'styles' => array(
					'width' 		=> '75%',
					'margin' 		=> '0 auto',
					'display' 		=> 'table',
					'text-align' 	=> 'center',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Blockquote Left', 'codevz' ),
				'inline' => 'span',
				'classes' => 'blockquote',
				'styles' => array(
					'float' 		=> 'left',
					'width' 		=> '40%',
					'margin' 		=> '0 20px 20px 0',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Blockquote Right', 'codevz' ),
				'inline' => 'span',
				'classes' => 'blockquote',
				'styles' => array(
					'float' 		=> 'right',
					'width' 		=> '40%',
					'margin' 		=> '0 0 20px 20px',
				),
				'wrapper' => false
			),	
			array(
				'title'  => esc_html__( 'Float Right', 'codevz' ),
				'inline' => 'span',
				'styles' => array( 'float' => 'right' ),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Dropcap', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_dropcap',
				'styles' => array(
					'float' 		=> self::$is_rtl ? 'right' : 'left',
					'margin' 		=> self::$is_rtl ? '5px 0 0 12px' : '5px 12px 0 0',
					'width' 		=> '2em',
					'height' 		=> '2em',
					'line-height' 	=> '2em',
					'text-align' 	=> 'center',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Dropcap Border', 'codevz' ),
				'inline' => 'span',
				'classes' => 'cz_dropcap',
				'styles' => array(
					'float' 		=> self::$is_rtl ? 'right' : 'left',
					'margin' 		=> self::$is_rtl ? '5px 0 0 12px' : '5px 12px 0 0',
					'width' 		=> '2em',
					'height' 		=> '2em',
					'line-height' 	=> '2em',
					'text-align' 	=> 'center',
					'border' 		=> '2px solid',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Sup', 'codevz' ),
				'inline' => 'sup',
				'styles' => [],
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Sub', 'codevz' ),
				'inline' => 'sub',
				'styles' => [],
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Small', 'codevz' ),
				'inline' => 'small',
				'styles' => [],
				'wrapper' => false
			),
		);
		$i['style_formats'] = json_encode( $style_formats );

		return $i;
	}

	/**
	 *
	 * Filter for moving animation param into new tab Advanced
	 * 
	 * @param $i is default css_animation settings
	 * @return array
	 * 
	 */
	public static function vc_map_add_css_animation( $i ) {
		$i['group'] = esc_html__( 'Advanced', 'codevz' );
		return $i;
	}

	/**
	 *
	 * Useful shortcodes
	 * 
	 * @return string
	 * 
	 */
	public static function br( $a, $c = '' ) {
		return '<br class="clr" />';
	}

	public static function shortcode_get_current_year( $a, $c = '' ) {
		return current_time( 'Y' );
	}

	public static function shortcode_translate( $a, $c = '' ) {
		if ( isset( $a['lang'] ) ) {

			$lang = get_locale();

			if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
				$lang = ICL_LANGUAGE_CODE;

			} else if ( function_exists( 'pll_current_language' ) ) {
				$lang = pll_current_language();

			} else if ( class_exists( 'qtrans_getSortedLanguages' ) ) {
				global $q_config;
				$lang = isset( $q_config['language'] ) ? $q_config['language'] : $lang;
			}

			if ( self::contains( $lang, $a['lang'] ) ) {
				return $c;
			}
		}
	}

	/**
	 *
	 * Add loop animations to vc animations list
	 * 
	 * @return string
	 * 
	 */
	public static function vc_param_animation_style_list( $i ) {
		return wp_parse_args( array(
			array(
				'label' => esc_html__( 'Loop Animations', 'codevz' ),
				'values' => array(
					esc_html__( 'Spinner', 'codevz' ) => array(
						'value' => 'cz_loop_spinner',
						'type' => 'in',
					),
					esc_html__( 'Pulse', 'codevz' ) => array(
						'value' => 'cz_loop_pulse',
						'type' => 'in',
					),
					esc_html__( 'Tada', 'codevz' ) => array(
						'value' => 'cz_loop_tada',
						'type' => 'in',
					),
					esc_html__( 'Flash', 'codevz' ) => array(
						'value' => 'cz_loop_flash',
						'type' => 'in',
					),
					esc_html__( 'Swing', 'codevz' ) => array(
						'value' => 'cz_loop_swing',
						'type' => 'in',
					),
					esc_html__( 'Jello', 'codevz' ) => array(
						'value' => 'cz_loop_jello',
						'type' => 'in',
					),
					esc_html__( 'Animation 1', 'codevz' ) => array(
						'value' => 'cz_infinite_anim_1',
						'type' => 'in',
					),
					esc_html__( 'Animation 2', 'codevz' ) => array(
						'value' => 'cz_infinite_anim_2',
						'type' => 'in',
					),
					esc_html__( 'Animation 3', 'codevz' ) => array(
						'value' => 'cz_infinite_anim_3',
						'type' => 'in',
					),
					esc_html__( 'Animation 4', 'codevz' ) => array(
						'value' => 'cz_infinite_anim_4',
						'type' => 'in',
					),
					esc_html__( 'Animation 5', 'codevz' ) => array(
						'value' => 'cz_infinite_anim_5',
						'type' => 'in',
					),
				),
			),
			array(
				'label' => esc_html__( 'Block Reveal', 'codevz' ),
				'values' => array(
					esc_html__( 'Right', 'codevz' ) => array(
						'value' => 'cz_brfx_right',
						'type' => 'in',
					),
					esc_html__( 'Left', 'codevz' ) => array(
						'value' => 'cz_brfx_left',
						'type' => 'in',
					),
					esc_html__( 'Up', 'codevz' ) => array(
						'value' => 'cz_brfx_up',
						'type' => 'in',
					),
					esc_html__( 'Down', 'codevz' ) => array(
						'value' => 'cz_brfx_down',
						'type' => 'in',
					),
				),
			),
		), $i );
	}

	/**
	 * Required for admin
	 * 
	 * @return string
	 */
	public static function admin_enqueue_scripts() {
		wp_add_inline_script( 'csf', 'var codevz_primary_color = "' . self::option( 'site_color', '#4e71fe' ) . '", codevz_secondary_color = "' . self::option( 'site_color_sec' ) . '";', 'before' );
	}

	/**
	 * Add/Remove custom sidebar
	 * 
	 * @return string
	 */
	public static function custom_sidebars() {

		if ( ! empty( $_GET['sidebar_name'] ) ) {

			$name 		= sanitize_title_with_dashes( $_GET['sidebar_name'] );
			$options 	= get_option( 'codevz_theme_options' );
			$sidebars 	= is_array( $options['custom_sidebars'] ) ? $options['custom_sidebars'] : [];

			if ( isset( $_GET['add_sidebar'] ) ) {

				$sidebars[] = 'cz-custom-' . $name;
				$options['custom_sidebars'] = $sidebars;
				update_option( 'codevz_theme_options', $options );

				echo 'done';
			
			} else if ( isset( $_GET['remove_sidebar'] ) ) {

				foreach ( $sidebars as $key => $sidebar ) {
					if ( $sidebar == $name ) {
						unset( $sidebars[ $key ] );
					}
				}

				$options['custom_sidebars'] = $sidebars;
				update_option( 'codevz_theme_options', $options );

				echo 'done';
			}
		}

		wp_die();
	}

	/**
	 * Generates unique ID
	 * 
	 * @return string
	 */
	public static function uniqid( $prefix = 'cz' ) {
		return $prefix . rand( 1111, 9999 );
	}

	/**
	 * Check if string contains specific value(s)
	 * 
	 * @return string
	 */
	public static function contains( $v = '', $a = [] ) {
		if ( $v ) {
			foreach ( (array) $a as $k ) {
				if ( $k && strpos( (string) $v, (string) $k ) !== false ) {
					return 1;
					break;
				}
			}
		}
		
		return null;
	}

	/**
	 * Shortcode output
	 * 
	 * @param $atts, content and live js functions names
	 * @return string|null
	 */
	public static function _out( $a, $c = '', $s = '' ) {

		// Parallax
		$m = $p = '';
		$ph = empty( $a['parallax_h'] ) ? '' : $a['parallax_h'];
		$pp = empty( $a['parallax'] ) ? '' : $a['parallax'];
		$pp .= empty( $a['parallax_stop'] ) ? '' : ' cz_parallax_stop';
		if ( ! empty( $a['mparallax'] ) && self::contains( $ph, 'mouse' ) ) {
			$m = '<div class="cz_mparallax_' . $a['mparallax'] . '">';
		}
		if ( $pp ) {
			$d = ( $ph == 'true' || $ph === 'truemouse' ) ? 'h' : 'v';
			$p = '<div class="clr cz_parallax_' . $d . '_' . $pp . '">';
		}

		// Front-end JS
		if ( self::$vc_editable ) {
			$o = '';
			foreach ( (array) $s as $v ) {
				$o .= self::contains( $v, ')' ) ? 'Codevz_Plus.' . $v . ';' : ( $v ? 'Codevz_Plus.' . $v . '();' : '' );
			}
			$c .= '<script type="text/javascript">if(typeof Codevz_Plus!=="undefined"){' . $o . 'Codevz_Plus.parallax();Codevz_Plus.runScroll();Codevz_Plus.fix_wp_editor_google_fonts();}</script>';
			$p = $p ? $p : '<div class="cz_wrap clr">';
		}

		return $m . $p . $c . ( $p ? '</div>' : '' ) . ( $m ? '</div>' : '' );
	}

	/**
	 * Generate inline data style or style tag depend on define
	 * 
	 * @param CSS
	 * @return string|null
	 */
	public static function data_stlye( &$d = '', &$t = '', &$m = '' ) {
		$out = '';

		// Page builder styles
		$d = empty( $d ) ? '' : $d;

		// Page builder tablet styles
		if ( ! empty( $t ) && substr( $t, 0, 1 ) !== '@' ) {
			$t = '@media screen and (max-width:' . self::option( 'tablet_breakpoint', '768px' ) . '){' . $t . '}';
		}

		// Page builder mobile styles
		if ( ! empty( $m ) && substr( $m, 0, 1 ) !== '@' ) {
			$m = '@media screen and (max-width:' . self::option( 'mobile_breakpoint', '480px' ) . '){' . $m . '}';
		}

		if ( ! self::$is_admin && ! self::$vc_editable && ! is_customize_preview() ) {
			$out .= ( $d || $t || $m ) ? " data-cz-style='" . str_replace( "'", '"', $d . $t . $m ) . "'" : '';
		} else {
			$out .= $d ? '><style class="cz_d_css" data-noptimize>' . $d . "</style" : '';
			$out .= $t ? '><style class="cz_t_css" data-noptimize>' . $t . "</style" : '';
			$out .= $m ? '><style class="cz_m_css" data-noptimize>' . $m . "</style" : '';
		}

		return $out;
	}

	/**
	 *
	 * Generate titl data attributes for shortcode
	 * 
	 * @param $atts array
	 * @return string|null
	 * 
	 */
	public static function tilt( $atts ) {
		if ( ! empty( $atts['tilt'] ) ) {
			$out = ' data-tilt';
			$out .= ( $atts['glare'] != '0' ) ? ' data-tilt-maxGlare="' . $atts['glare'] . '" data-tilt-glare="true"' : '';
			$out .= ( $atts['scale'] != '1' ) ? ' data-tilt-scale="' . $atts['scale'] . '"' : '';

			return $out;
		}
	}

	/**
	 *
	 * Generate class attribute for element related to $atts
	 * 
	 * @param $atts array and classes array
	 * @return string|null
	 * 
	 */
	public static function classes( $a, $o = [], $i = 0 ) {
		$o[] = $i ? '' : ( isset( $a['class'] ) ? esc_attr( $a['class'] ) : '' );
		$o[] = empty( $a['hide_on_d'] ) ? '' : 'hide_on_desktop';
		$o[] = empty( $a['hide_on_t'] ) ? '' : 'hide_on_tablet';
		$o[] = empty( $a['hide_on_m'] ) ? '' : 'hide_on_mobile';

		// Check animation name
		if ( ! empty( $a['css_animation'] ) && $a['css_animation'] !== 'none' ) {
			
			// WPBakery old versions
			wp_enqueue_script( 'waypoints' );
			wp_enqueue_style( 'animate-css' );

			// WPBakery after v6.x
			wp_enqueue_script( 'vc_waypoints' );
			wp_enqueue_style( 'vc_animate-css' );

			// Classes
			$o[] = 'wpb_animate_when_almost_visible ' . $a['css_animation'];
		}

		return ' class="' . implode( ' ', array_filter( $o ) ) . '"';
	}

	/**
	 *
	 * Generate link attributes for element related to vc_build_link array
	 * 
	 * @param vc_build_link array
	 * @return string|null
	 * 
	 */
	public static function link_attrs( $a = '' ) {
		if ( $a ) {
			$a = vc_build_link( $a );
			$url = empty( $a['url'] ) ? '' : ' href="' . do_shortcode( $a['url'] ) . '"';
			$rel = empty( $a['rel'] ) ? '' : ' rel="nofollow"';
			$title = empty( $a['title'] ) ? '' : ' title="' . esc_attr( $a['title'] ) . '"';
			$target = empty( $a['target'] ) ? '' : ' target="_blank"';

			return $url . $rel . $title . $target;
		} else {
			return ' href="#"';
		}
	}

	/**
	 *
	 * Lazyload src attributes
	 * 
	 * @return string
	 *
	 */
	public static function lazyload( $c ) {
		$is_cart = ( function_exists( 'is_cart' ) && is_cart() );

		// Skip feeds, previews, mobile, done before
		if ( self::$is_admin || is_feed() || is_preview() || $is_cart || ! empty( $_GET ) ) {
			return $c;
		}

		preg_match_all( '/<img(.*?)>/', $c, $matches, PREG_SET_ORDER, 0);
		foreach ( $matches as $key ) {
			if ( isset( $key[0] ) && ! self::contains( $key[0], [ 'data:image', 'data-thumb', 'data-bg', 'data-ww=', 'rev-slide' ] ) ) {
				$new = preg_replace( '/ src=/', ' src="data:image/svg+xml,%3Csvg%20xmlns%3D&#39;http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg&#39;%20width=&#39;_w_&#39;%20height=&#39;_h_&#39;%20viewBox%3D&#39;0%200%20_w_%20_h_&#39;%2F%3E" data-czlz data-src=', $key[0] );

				preg_match_all( '/(?<=width="|height="|width=\'|height=\')(\d*)/', $new, $matches );
				if ( isset( $matches[0][0] ) && isset( $matches[0][1] ) ) {
					$new = str_replace( array( '_w_', '_h_' ), array( $matches[0][0], $matches[0][1] ), $new );
				}

				$c = str_replace( $key[0], $new, $c );
			}
		}

		return str_replace( 'srcset', 'data-srcset', str_replace( 'sizes=', 'data-sizes=', $c ) );
	}

	/**
	 * Remove query args for better seo, caching systems
	 * 
	 * @return string
	 */
	public static function remove_query_args( $i ) {
		return esc_url( remove_query_arg( 'ver', $i ) );
	}

	/**
	 * Remove type attr from scripts and stylesheets
	 * 
	 * @return string
	 */
	public static function remove_type_attr( $t, $h ) {
		return preg_replace( "/ type=['\"]text\/(javascript|css)['\"]/", '', $t );
	}

	/**
	 * Custom default colors for WP Colorpicker
	 * 
	 * @return string
	 */
	public static function wp_color_palettes() {
		if ( wp_script_is( 'wp-color-picker', 'enqueued' ) ) {
	?>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				var primary_color = typeof codevz_primary_color == 'string' ? codevz_primary_color : '',
					secondary_color = typeof codevz_secondary_color == 'string' ? codevz_secondary_color : '',
					palettes = ['#000000','#FFFFFF','#E53935','#FF5722','#FFEB3B','#8BC34A','#3F51B5','#9C27B0',primary_color];

				if ( secondary_color ) {
					palettes.push( secondary_color );
				}

				jQuery.wp.wpColorPicker.prototype.options = {
					hide: true,
					palettes: palettes
				};
			});
		</script>
	<?php
		}
	}

	/**
	 * Set settings for post types
	 * 
	 * @var  $query current page/post query
	 * @return array
	 */
	public static function action_pre_get_posts( $query ) {

		if ( is_admin() || empty( $query ) ) {
			return $query;
		}

		$query->query[ 'post_type' ] = isset( $query->query[ 'post_type' ] ) ? $query->query[ 'post_type' ] : 'post';

		// Set new settings for post types
		$cpt = (array) get_option( 'codevz_post_types' );
		$cpt[] = 'portfolio';

		// Custom post type UI
		if ( function_exists( 'cptui_get_post_type_slugs' ) ) {
			$cptui = cptui_get_post_type_slugs();
			if ( is_array( $cptui ) ) {
				$cpt = wp_parse_args( $cptui, $cpt );
			}
		}
		
		foreach ( $cpt as $name ) {
			$ppp = self::option( 'posts_per_page_' . $name );
			$is_cpt = ( is_post_type_archive( $name ) && $query->query[ 'post_type' ] === $name );

			// Tax
			$is_tax = false;

			if ( ! empty( $query->tax_query->queries[0]['taxonomy'] ) ) {

				$taxonomy = $query->tax_query->queries[0]['taxonomy'];

				if ( isset( $query->query[ $taxonomy ] ) && self::contains( $taxonomy, $name ) ) {
					$is_tax = true;
				}
			}

			if ( $ppp && ! is_admin() && ( $is_cpt || $is_tax ) ) {
				$query->set( 'posts_per_page', $ppp );
			}
		}

		// Search
		$search = self::option( 'search_cpt' );
		if ( $search && $query->is_main_query() && $query->is_search() ) {
			$query->set( 'post_type', explode( ',', str_replace( ' ', '', $search ) ) );
		}

		return $query;
	}

	/**
	 * Modify category widget output
	 * 
	 * @return string
	 */
	public static function wp_list_categories( $i ) {
		$i = preg_replace( '/cat-item\scat-item-(.?[0-9])\s/', '', $i );
		$i = preg_replace( '/current-cat/', 'current', $i );
		$i = preg_replace( '/\sclass="cat-item\scat-item-(.?[0-9])"/', '', $i );
		$i = preg_replace( '/\stitle="(.*?)"/', '', $i );
		$i = preg_replace( '/\sclass=\'children\'/', '', $i );
		$i = str_replace( '</a> (', '</a><span>(', $i );

		return str_replace( ')', ')</span>', $i );
	}

	/**
	 * Modify archive widget output
	 * 
	 * @return string
	 */
	public static function get_archives_link( $i ) {
		$i = str_replace( '</a>&nbsp;(', '</a><span>(', $i );

		return str_replace( ')', ')</span>', $i );
	}

	/**
	 * Maintenance mode redirect
	 * 
	 * @return string
	 */
	public static function maintenance_mode( $i ) {

		// Get option.
		$mt = self::option( 'maintenance_mode' );

		// Simple.
		if ( $mt === 'simple' && ! is_user_logged_in() ) {

			wp_die( self::option( 'maintenance_message', esc_html__( 'We are under maintenance mode, We will back soon.', 'codevz' ) ) );

		// Custom page.
		} else if ( $mt && $mt !== 'none' ) {

			$mt = get_page_by_title( $mt, 'object', 'page' );

			if ( ! is_user_logged_in() && ! is_page( $mt ) ) {

				wp_redirect( get_the_permalink( $mt ) );
				exit;

			}

		}

		return $i;
	}

	/**
	 * Ajax search process
	 * 
	 * @return string
	 */
	public static function ajax_search() {

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'ajax_search_nonce' ) ) {
			wp_die( '<b class="ajax_search_error">Try again ...</b>' );
		}

		$l = empty( $_GET['posts_per_page'] ) ? 4 : (int) $_GET['posts_per_page'];
		$s = sanitize_text_field( $_GET['s'] );
		$c = empty( $_GET['post_type'] ) ? [ 'any' ] : explode( ',', str_replace( ' ', '', $_GET['post_type'] ) );
		$nt = empty( $_GET['no_thumbnail'] ) ? 0 : 1;

		$posts = get_posts(
			[
				's'              => $s,
				'post_type' 	 => $c,
				'posts_per_page' => $l,
				'fields'         => 'ids'
			]
		);

		if ( ! empty( $posts ) ) {

			$i = 0;
			foreach( $posts as $post_id ) {

				$post = get_post( $post_id );

				$cpt = self::get_post_type( $post_id );
				if ( $cpt === 'page' || $cpt === 'dwqa-answer' ) {
					continue;
				}

				echo '<div id="post-' . esc_attr( $post_id ) . '" class="item_small">';
				if ( has_post_thumbnail( $post_id ) && ! $nt ) {
					echo '<a class="theme_img_hover" href="' . esc_url( get_the_permalink( $post_id ) ) . '"><img src="' . esc_url( get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ) . '" width="80" height="80" /></a>';
				}
				echo apply_filters( 'cz_ajax_search_instead_img', '' );
				echo '<div class="item-details">';
				echo '<h3><a href="' . esc_url( get_the_permalink( $post_id ) ) . '" rel="bookmark">' . $post->post_title . '</a></h3>';
				echo '<span class="cz_search_item_cpt mr4"><i class="fa fa-folder-o mr4"></i>' . ucwords( ( $cpt === 'dwqa-question' ) ? 'Questions' : $cpt ) . '</span><span><i class="fa fa-clock-o mr4"></i>' . esc_html( get_the_date( false, $post_id ) ) . '</span>';
				echo '</div></div>';

				$i++;
			}

			if ( $i === 0 ) {

				echo '<b class="ajax_search_error">' . esc_html( self::option( 'not_found', 'Not found!' ) ) . '</b>';
			
			} else if ( count( $posts ) >= $l ) {

				unset( $_GET['action'] );
				unset( $_GET['nonce'] );
				echo '<a class="va_results" href="' . esc_url( home_url( '/' ) ) . '?' . http_build_query( $_GET ) . '"> ... </a>';

			}

		} else {

			echo '<b class="ajax_search_error">' . esc_html( self::option( 'not_found', 'Not found!' ) ) . '</b>';

		}

		wp_die();
	}

	/**
	 * Generate social icons
	 * @return string
	 */
	public static function social( $out = '' ) {

		$social = self::option( 'social' );
		if ( is_array( $social ) ) {
			$tooltip = self::option( 'social_tooltip' );
			$tooltip = $tooltip ? ' ' . $tooltip : '';
			$social_inline_title = self::option( 'social_inline_title' ) ? ' cz_social_inline_title' : '';
			$out .= '<div class="cz_social ' . esc_attr( self::option( 'social_color_mode' ) . ' ' . self::option( 'social_hover_fx' ) . $social_inline_title . $tooltip ) . '">';
			foreach ( $social as $soci ) {
				$social_link_class = 'cz-' . str_replace( self::$social_fa_upgrade, '', esc_attr( $soci['icon'] ) );

				$target = ( Codevz_Plus::contains( $soci['link'], [ $_SERVER['HTTP_HOST'], 'tel:', 'mailto:' ] ) || $soci['link'] === '#' ) ? '' : ' target="_blank"';

				$out .= '<a class="' . esc_attr( $social_link_class ) . '" href="' . esc_attr( $soci['link'] ) . '" ' . ( $tooltip ? 'data-' : '' ) . 'title="' . esc_attr( do_shortcode( $soci['title'] ) ) . '"' . $target . '><i class="' . esc_attr( $soci['icon'] ) . '"></i><span>' . esc_html( do_shortcode( $soci['title'] ) ) . '</span></a>';
			}
			$out .= '</div>';
		}

		return $out;
	}

	/**
	 * Content box effects
	 * @return array
	 */
	public static function fx( $hover = '' ) {
		$i = array(
			esc_html__( 'Select', 'codevz' ) 		=> '',
			esc_html__( 'Zoom 1', 'codevz') 		=> 'fx_zoom_0' . $hover,
			esc_html__( 'Zoom 2', 'codevz') 		=> 'fx_zoom_1' . $hover,
			esc_html__( 'Zoom 3', 'codevz') 		=> 'fx_zoom_2' . $hover,
			esc_html__( 'Move up', 'codevz') 		=> 'fx_up' . $hover,
			esc_html__( 'Move right', 'codevz') 	=> 'fx_right' . $hover,
			esc_html__( 'Move down', 'codevz') 		=> 'fx_down' . $hover,
			esc_html__( 'Move left', 'codevz') 		=> 'fx_left' . $hover,
			esc_html__( 'Border inner', 'codevz') 	=> 'fx_inner_line' . $hover,
			esc_html__( 'Grayscale', 'codevz') 		=> 'fx_grayscale' . $hover,
			esc_html__( 'Remove Grayscale', 'codevz') => 'fx_remove_grayscale' . $hover,
			esc_html__( 'Skew left', 'codevz') 		=> 'fx_skew_left' . $hover,
			esc_html__( 'Skew right', 'codevz') 	=> 'fx_skew_right' . $hover,
			esc_html__( 'Bob loop', 'codevz') 		=> 'fx_bob' . $hover,
			esc_html__( 'Low opacity', 'codevz') 	=> 'fx_opacity' . $hover,
		);

		if ( $hover ) {
			$i = array_merge( $i, array(
				esc_html__( 'Full opacity', 'codevz') 		=> 'fx_full_opacity',
				esc_html__( '360° Z', 'codevz') 			=> 'fx_z_hover',
				esc_html__( 'Bounce', 'codevz') 			=> 'fx_bounce_hover',
				esc_html__( 'Shine', 'codevz') 				=> 'fx_shine_hover',
				esc_html__( 'Grow rotate right', 'codevz') 	=> 'fx_grow_rotate_right_hover',
				esc_html__( 'Grow rotate left', 'codevz') 	=> 'fx_grow_rotate_left_hover',
				esc_html__( 'Wobble skew', 'codevz') 		=> 'fx_wobble_skew_hover',
			) );
		}

		return $i;
	}
	
	/**
	 * Get RGB numbers of HEX color
	 * @var Hex color code
	 * @return string
	 */
	public static function hex2rgba( $c = '', $o = '1' ) {
		if ( empty( $c[0] ) ) {
			return '';
		}
		
		$c = substr( $c, 1 );
		if ( strlen( $c ) == 6 ) {
			list( $r, $g, $b ) = array( $c[0] . $c[1], $c[2] . $c[3], $c[4] . $c[5] );
		} elseif ( strlen( $c ) == 3 ) {
			list( $r, $g, $b ) = array( $c[0] . $c[0], $c[1] . $c[1], $c[2] . $c[2] );
		} else {
			return false;
		}
		$r = hexdec( $r );
		$g = hexdec( $g );
		$b = hexdec( $b );

		return 'rgba(' . implode( ',', array( $r, $g, $b ) ) . ',' . $o . ')';
	}

	/**
	 *
	 * Enqueue google font
	 * 
	 * @return string|null
	 * 
	 */
	public static function load_font( $f = '' ) {
		if ( ! $f || self::contains( $f, 'custom_' ) ) {
			return;
		} else {
			$f = self::contains( $f, ';' ) ? self::get_string_between( $f, 'font-family:', ';' ) : $f;
			$f = str_replace( '=', ':', $f );
		}

		$defaults = array(
			'FontAwesome' 		=> 'FontAwesome', 
			'Font Awesome 5 Free' => 'Font Awesome 5 Free', 
			'czicons' 			=> 'czicons', 
			'fontelo' 			=> 'fontelo',
			'Arial' 			=> 'Arial',
			'Arial Black' 		=> 'Arial Black',
			'Comic Sans MS' 	=> 'Comic Sans MS',
			'Impact' 			=> 'Impact',
			'Lucida Sans Unicode' => 'Lucida Sans Unicode',
			'Tahoma' 			=> 'Tahoma',
			'Trebuchet MS' 		=> 'Trebuchet MS',
			'Verdana' 			=> 'Verdana',
			'Courier New' 		=> 'Courier New',
			'Lucida Console' 	=> 'Lucida Console',
			'Georgia, serif' 	=> 'Georgia, serif',
			'Palatino Linotype' => 'Palatino Linotype',
			'Times New Roman' 	=> 'Times New Roman'
		);

		// Custom fonts
		$custom_fonts = (array) self::option( 'custom_fonts' );
		foreach ( $custom_fonts as $a ) {
			if ( ! empty( $a['font'] ) ) {
				$defaults[ $a['font'] ] = $a['font'];
			}
		}

		$f = self::contains( $f, ':' ) ? $f : $f . ':200,300,400,500,600,700';
		$f = explode( ':', $f );
		$p = empty( $f[1] ) ? '' : ':' . $f[1];
		
		if ( ! empty( $f[0] ) && ! isset( $defaults[ $f[0] ] ) ) {
			wp_enqueue_style( 'google-font-' . sanitize_title_with_dashes( $f[0] ), 'https://fonts.googleapis.com/css?family=' . str_replace( ' ', '+', ucfirst( $f[0] ) ) . $p );
		}

		if ( ! isset( $GLOBALS[ 'xtra_ignore_fonts' ] ) ) {
			$GLOBALS[ 'xtra_ignore_fonts' ] = true;
			wp_localize_script( 'codevz-plugin', 'xtra_ignore_fonts', $defaults );
		}
	}

	/**
	 *
	 * SK Style + load font
	 * 
	 * @return string
	 *
	 */
	public static function sk_inline_style( $sk = '', $important = false ) {
		$sk = str_replace( 'CDVZ', '', $sk );
		
		if ( self::contains( $sk, 'font-family' ) ) {
			self::load_font( $sk );

			// Extract font + params && Fix font for CSS
			$font = $o_font = self::get_string_between( $sk, 'font-family:', ';' );
			$font = str_replace( '=', ':', $font );
			
			if ( self::contains( $font, ':' ) ) {
				$font = explode( ':', $font );
				if ( ! empty( $font[0] ) ) {
					$sk = str_replace( $o_font, "'" . $font[0] . "'", $sk );
				}
			} else {
				$sk = str_replace( $font, "'" . $font . "'", $sk );
			}
		}

		if ( $important ) {
			$sk = str_replace( ';', ' !important;', $sk );
		}

		if ( self::$is_rtl ) {
			return str_replace( 'RTL', '', $sk );
		} else if ( self::contains( $sk, 'RTL' ) ) {
			return strstr( $sk, 'RTL', true );
		} else {
			return $sk;
		}
	}

	/**
	 *
	 * Return full CSS with selector and fixes plus loading fonts
	 * 
	 * @return string|null
	 * 
	 */
	public static function sk_style( $atts = [], $ids = [], $device = '' ) {
		$out = $rtl = '';
		foreach ( (array) $ids as $id => $selector ) {
			$is_array = is_array( $selector );
			$val = empty( $atts[ $id . $device ] ) ? '' : str_replace( "``", '"', $atts[ $id . $device ] );

			if ( $val ) {
				$val = str_replace( 'CDVZ', '', $val );

				// RTL
				if ( self::contains( $val, 'RTL' ) ) {
					$rtl = self::get_string_between( $val, 'RTL', 'RTL' );
					$val = str_replace( array( $rtl, 'RTL' ), '', $val );
				}

				// Fix and load google font
				if ( self::contains( $val, 'font-family' ) ) {
					self::load_font( $val ); // Enqueue font

					// Extract font + params && Fix font for CSS
					$font = $o_font = self::get_string_between( $val, 'font-family:', ';' );
					$font = str_replace( '=', ':', $font );
					$font = str_replace( 'custom_', '', $font );
					
					if ( self::contains( $font, ':' ) ) {
						$font = explode( ':', $font );
						if ( ! empty( $font[0] ) ) {
							$val = str_replace( $o_font, "'" . $font[0] . "'", $val );
						}
					} else {
						$val = str_replace( $font, "'" . $font . "'", $val );
					}
				}

				if ( $is_array ) {
					if ( ! $device ) {
						$val .= $selector[1];
					}
					$selector = $selector[0];
				}

				// SVG background layer
				if ( self::contains( $id, 'svg_bg' ) ) {
					$type = self::contains( $val, '_class_svg_type' ) ? self::get_string_between( $val, '_class_svg_type:', ';' ) : '';
					$size = ( $type === 'circle' ) ? '3' : '1';
					$size = self::contains( $val, '_class_svg_size' ) ? self::get_string_between( $val, '_class_svg_size:', ';' ) : '1';
					$color = self::contains( $val, '_class_svg_color' ) ? self::get_string_between( $val, '_class_svg_color:', ';' ) : '#222';
					$color = self::contains( $color, 'rgba' ) ? $color : self::hex2rgba( $color );

					if ( $type === 'circle' ) {
						$base = base64_encode( "<svg xmlns='http://www.w3.org/2000/svg' width='20' height='24'><circle cx='6' cy='6' r='" . $size . "' fill='none' stroke='" . $color . "' stroke-width='1' /></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					} else if ( $type === 'dots' ) {
						$base = base64_encode( "<svg xmlns='http://www.w3.org/2000/svg' width='20' height='24'><circle cx='6' cy='6' r='" . $size . "' fill='" . $color . "' /></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					} else if ( $type === 'x' ) {
						$base = base64_encode( "<svg width='24' height='24' xmlns='http://www.w3.org/2000/svg'><path d='M4.01,15.419L15.419,4.01l0.57,0.57L4.581,15.99Z' stroke='" . $color . "' stroke-width='" . $size . "'></path><path d='M15.419,15.99L4.01,4.581l0.57-.57L15.99,15.419Z' stroke='" . $color . "' stroke-width='" . $size . "'></path></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					} else if ( $type === 'line' ) {
						$base = base64_encode( "<svg width='24' height='24' xmlns='http://www.w3.org/2000/svg'><path d='M4.01,15.419L15.419,4.01l0.57,0.57L4.581,15.99Z' stroke='" . $color . "' stroke-width='" . $size . "'></path></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					}

					// Remove unwanted in css
					if ( self::contains( $val, '_class_' ) ) {
						$val = preg_replace( '/_class_[\s\S]+?;/', '', $val );
					}
				}

				// Append CSS
				$out .= $selector . '{' . $val . '}';

				// RTL
				if ( $rtl ) {
					$sp = self::contains( $selector, array( '.cz-cpt-', '.cz-page-', '.home', 'body', '.woocommerce' ) ) ? '' : ' ';
					$out .= '.rtl' . $sp . preg_replace( '/,\s+|,/', ',.rtl' . $sp, $selector ) . '{' . $rtl . '}';
				}
				$rtl = 0;

			} else if ( $is_array && ! $device && $selector[1] ) {
				$out .= $selector[0] . '{' . $selector[1] . '}';
			}
		}

		return str_replace( ';}', '}', $out );
	}

	/**
	 * Fix: Remove extra <p> and </p> from content of elements
	 * 
	 * @return string
	 */
	public static function fix_extra_p( $content = '' ) {
		return preg_replace( '/^<\/p>\n|<p>$/', '', $content );
	}

	/**
	 * Get string between two string
	 * 
	 * @return string
	 */
	public static function get_string_between( $c = '', $s, $e, $m = 0 ) {
		if ( $c ) {
			if ( $m ) {
				preg_match_all( '~' . preg_quote( $s, '~' ) . '(.*?)' . preg_quote( $e, '~' ) . '~s', $c, $matches );
				return $matches[0];
			}

			$r = explode( $s, $c );
			if ( isset( $r[1] ) ) {
				$r = explode( $e, $r[1] );
				return $r[0];
			}
		}

		return;
	}

	/**
	 * Get image by id or url
	 * 
	 * @var $i image ID or image url
	 * @var $s image size
	 * @var $url only return url of image
	 * @var $c custom class for image
	 * @return string
	 */
	public static function get_image( $i = '', $s = 0, $url = 0, $c = '' ) {

		if( function_exists( 'wpb_getImageBySize' ) && ! self::contains( $i, '.' ) ) {

			$i = wpb_getImageBySize(
				[
					'attach_id' 	=> empty( $i ) ? 1 : $i,
					'thumb_size' 	=> empty( $s ) ? 'full' : $s,
					'class' 		=> $c
				]
			);

			$i = $i['thumbnail'];

		}

		if( empty( $i ) ) {
		
			$i = '<img src="' . self::$url . 'assets/img/p.svg' . '" class="xtra-placeholder ' . $c . '" width="1000" height="1000" alt="image" />';
		
		} else if( ! self::contains( $i, 'src' ) ) {
		
			$i = '<img src="' . $i . '" class="' . $c . '" width="500" height="500" alt="image" />';
		
		}

		return $url ? self::get_string_between( $i, 'src="', '"' ) : $i;
	}

	/**
	 * Get post data
	 * 
	 * @return string
	 */
	public static function get_post_data( $id, $w = 'date', $s = 0, $c = '', $ic = '', $tc = '' ) {

		$cls = $w;
		$w = self::contains( $w, ' ' ) ? substr( $w, 0, strpos( $w, ' ' ) ) : $w;

		if ( $w === 'date' || $w === 'date_1' ) {

			$date = get_the_date();
			$out = $s ? $date : '<a href="' . get_the_permalink( $id ) . '">' . $date . '</a>';

		} else if ( $w === 'cats' || $w === 'cats_1' ) {

			$cpt = get_post_type( $id );
			$tax = ( empty( $cpt ) || $cpt === 'post' ) ? 'category' : $cpt . '_cat';
			$cats = get_the_term_list( $id, $tax, '', ', ', '');
			if ( is_string( $cats ) ) {
				$out = $s ? strip_tags( $cats ) : $cats;
			}
			
		} else if ( self::contains( $w, array( 'cats_2', 'cats_3', 'cats_4', 'cats_5', 'cats_6', 'cats_7' ) ) ) {

			$out = self::get_cats( $id, $w, $s, $tc );
			
		} else if ( $w === 'tags' ) {

			$out = self::get_tags( $id, $s, $tc );
			
		} else if ( $w === 'author' ) {

			$author = get_the_author_meta( 'display_name', $id );
			$out = $s ? $author : '<a href="' . get_author_posts_url( $id ) . '">' . $author . '</a>';
			
		} else if ( $w === 'author_avatar' || $w === 'author_full_date' || $w === 'author_icon_date' ) {

			$author = get_the_author_meta( 'display_name', $id );
			$avatar = ( $ic && $w === 'author_icon_date' ) ? '<i class="cz_sub_icon fa ' . $ic . '"></i>' : get_avatar( $id, 30 );
			$link = get_author_posts_url( $id );

			if ( $s ) {
				$out = '<span class="cz_post_author_avatar">' . $avatar . '</span>';
				$out .= '<span class="cz_post_inner_meta">';
				$out .= '<span class="cz_post_author_name">' . $author . '</span>';
				if ( $w === 'author_full_date' || $w === 'author_icon_date' ) {
					$out .= '<span class="cz_post_date">' . get_the_date() . '</span>';
				}
				$out .= '</span>';
			} else {
				$out = '<a class="cz_post_author_avatar" href="' . $link . '">' . $avatar . '</a>';
				$out .= '<span class="cz_post_inner_meta">';
				$out .= '<a class="cz_post_author_name" href="' . $link . '">' . $author . '</a>';
				if ( $w === 'author_full_date' || $w === 'author_icon_date' ) {
					$out .= '<span class="cz_post_date">' . get_the_date() . '</span>';
				}
				$out .= '</span>';
			}
			
		} else if ( $w === 'price' ) {

			if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {

				global $woocommerce;

				$cx = get_woocommerce_currency_symbol();
				$price = get_post_meta( $id, '_regular_price', true );
				$sale = get_post_meta( $id, '_sale_price', true );

				$out = $sale ? '<del><span>' . $cx . '</span>' . $price . '</del> ' . '<span>' . $cx . '</span>' . $sale : '<span>' . $cx . '</span>' . $price;

			} else {
				$out = '---';
			}
			
		} else if ( $w === 'comments' ) {

			$cm = number_format_i18n( get_comments_number( $id ) );
			$out = $s ? $cm . ' ' . $c : '<a href="' . get_the_permalink( $id ) . '#comments">' . $cm . ' ' . $c . '</a>';
			
		} else if ( $w === 'custom_text' ) {
			
			$out = $s;
		
		} else if ( $w === 'custom_meta' ) {
			
			$out = (string) get_post_meta( $id, $s, true );
		
		}

		// Prefix
		$pre = ( $ic && ! self::contains( $w, 'author_' ) ) ? '<i class="cz_sub_icon mr8 fa ' . $ic . '"></i>' : '';
		$pre .= ( $c && $w !== 'comments' ) ? '<span class="cz_data_sub_prefix mr4">' . $c . '</span>' : '';

		// Out
		return isset( $out ) ? '<span class="cz_post_data cz_data_' . $cls . '">' . $pre . $out . '</span>' : '';
	}

	/**
	 * Get post categories include colors
	 * 
	 * @return string
	 */
	public static function get_cats( $id, $style = '', $no_link = 0, $l = 10, $out = [] ) {

		$cpt = get_post_type( $id );
		$tax = ( empty( $cpt ) || $cpt === 'post' ) ? 'category' : $cpt . '_cat';

		if ( $terms = get_the_terms( $id, $tax ) ) {
			foreach ( $terms as $term ) {
				if ( isset( $term->term_id ) ) {
					$color = get_term_meta( $term->term_id, 'codevz_cat_meta', true );
					$opacity = self::contains( $style, array( '6', '7' ) ) ? '1' : '0.1';
					$color = empty( $color['color'] ) ? '' : ' style="color:' . $color['color'] . ';border-color:' . $color['color'] . ';background: ' . self::hex2rgba( $color['color'], $opacity ) . '"';
					$out[] = $no_link ? '<span' . $color . '>' . $term->name . '</span>' : '<a href="' . get_term_link( $term ) . '"' . $color . '>' . $term->name . '</a>';
				}
			}
		}

		$out = implode( '', array_slice( $out, 0, $l ) );

		return $out ? '<span class="cz_cats_2 cz_' . $style . '">' . $out . '</span>' : '';
	}

	/**
	 * Get post tags
	 * 
	 * @return string
	 */
	public static function get_tags( $id, $no_link = 0, $l = 10, $out = [] ) {
		$tax = get_object_taxonomies( get_post_type( $id ), 'objects' );

		foreach ( $tax as $tax_slug => $taks ) {
			$terms = get_the_terms( $id, $tax_slug );

			if ( ! empty( $terms ) && self::contains( $taks->label, 'Tags' ) ) {
				foreach ( $terms as $term ) {
					$out[] = $no_link ? '#' . esc_html( $term->name ) . ' ' : '<a href="' . esc_url( get_term_link( $term->slug, $tax_slug ) ) . '">#' . esc_html( $term->name ) . '</a> ';
				}
			}
		}

		$out = implode( '', array_slice( $out, 0, $l ) );

		return $out ? '<span class="cz_ptags">' . $out . '</span>' : '';
	}

	/**
	 * Limit words of string
	 * 
	 * @return string
	 */
	public static function limit_words( $string = '', $length = 12, $read_more = null ) {

		// Get read more
		$read_more_a = self::get_string_between( $string, '<a', '</a>', 1 );
		if ( isset( $read_more_a[0] ) ) {
			$read_more_a = $read_more_a[0];
			$string = str_replace( $read_more_a, '', $string );
		}
		
		$count = count( preg_split( '~[^\p{L}\p{N}\']+~u', $string ) ) - 1;

		// String length
		$length--;
		if ( $count > $length ) {
			$string = strip_tags( $string );
			$string = preg_replace( '/((\w+\W*){' . $length . '}(\w+))(.*)/u', '${1}', $string ) . ' ...';
		}

		// Add read more
		if ( $read_more ) {
			$string .= $read_more_a;
		}

		// Out
		return str_replace( [ '... ', 'Array' ], '', $string );
	}

	/**
	 * Register new Post types
	 * 
	 * @return object
	 */
	public static function post_types() {
		$options 	= (array) self::option();
		$post_types = (array) get_option( 'codevz_post_types' );

		$post_types[99] = 'portfolio';

		if ( self::option( 'disable_portfolio' ) ) {
			unset( $post_types[99] );
		}

		foreach ( $post_types as $cpt ) {

			if ( empty( $cpt ) ) {
				continue;
			}

			$cpt = strtolower( str_replace( ' ', '_', $cpt ) );

			$opt = array(
				'slug' 			=> empty( $options[ 'slug_' . $cpt ] ) ? $cpt : $options[ 'slug_' . $cpt ], 
				'title' 		=> empty( $options[ 'title_' . $cpt ] ) ? ucwords( $cpt ) : $options[ 'title_' . $cpt ], 
				'cat_slug' 		=> empty( $options[ 'cat_' . $cpt ] ) ? $cpt . '/cat' : $options[ 'cat_' . $cpt ], 
				'cat_title' 	=> empty( $options[ 'cat_title_' . $cpt ] ) ? 'Categories' : $options[ 'cat_title_' . $cpt ], 
				'tags_slug' 	=> empty( $options[ 'tags_' . $cpt ] ) ? $cpt . '/tags' : $options[ 'tags_' . $cpt ], 
				'tags_title' 	=> empty( $options[ 'tags_title_' . $cpt ] ) ? 'Tags' : $options[ 'tags_title_' . $cpt ]
			);

			register_taxonomy( $cpt . '_cat', $cpt, 
				array(
					'hierarchical'		=> true,
					'labels'			=> array(
						'name'				=> $opt['cat_title'],
						'menu_name'			=> $opt['cat_title']
					),
					'show_ui'			=> true,
					'show_admin_column'	=> true,
					'show_in_rest'		=> true,
					'query_var'			=> true,
					'rewrite'			=> array( 'slug' => $opt['cat_slug'], 'with_front' => false ),
				)
			);

			register_taxonomy( $cpt . '_tags', $cpt, 
				array(
					'hierarchical'		=> false,
					'labels'			=> array(
						'name'				=> $opt['tags_title'],
						'menu_name'			=> $opt['tags_title']
					),
					'show_ui'			=> true,
					'show_admin_column'	=> true,
					'show_in_rest'		=> true,
					'query_var'			=> true,
					'rewrite'			=> array( 'slug' => $opt['tags_slug'], 'with_front' => false ),
				)
			);

			$cpt_label = str_replace( '_', ' ', $opt['title'] );
			register_post_type( $cpt, 
				array(
					'labels'		=> array(
						'name'			=> $cpt_label,
						'menu_name'		=> $cpt_label
					),
					'public'			=> true,
					//'menu_icon'		=> $icon,
					'supports'			=> array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'author', 'post-formats' ),
					'has_archive'		=> true,
					'show_in_rest'		=> true,
					'rewrite'			=> array( 'slug' => $opt['slug'], 'with_front' => false )
			 	)
			);
		}
	}

	/**
	 *
	 * Set short codes ID and extract styles then update post meta 'cz_sc_styles'
	 * 
	 * @return string
	 * 
	 */
	public static function save_post( $post_id = '' ) {
		if ( empty( $post_id ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// Get content
		$content = get_post_field( 'post_content', $post_id );

		// Extract Short codes
		$shortcodes = (array) self::get_string_between( $content, '[cz_', ']', 1 );
		if ( ! empty( $shortcodes ) ) {
			$styles = $tablet = $mobile = '';
			foreach ( $shortcodes as $sc ) {
				if ( ! empty( $sc ) ) {
					$do_shortcode = do_shortcode( $sc );
					$styles .= self::get_string_between( $do_shortcode, '<style class="cz_d_css" data-noptimize>', '</style>' );
					$tablet .= self::get_string_between( $do_shortcode, '<style class="cz_t_css" data-noptimize>', '</style>' );
					$mobile .= self::get_string_between( $do_shortcode, '<style class="cz_m_css" data-noptimize>', '</style>' );
				}
			}

			// Update meta box for new styles
			delete_post_meta( $post_id, 'cz_sc_styles' );
			update_post_meta( $post_id, 'cz_sc_styles', $styles );
			if ( $tablet ) {
				delete_post_meta( $post_id, 'cz_sc_styles_tablet' );
				update_post_meta( $post_id, 'cz_sc_styles_tablet', $tablet );
			}
			if ( $mobile ) {
				delete_post_meta( $post_id, 'cz_sc_styles_mobile' );
				update_post_meta( $post_id, 'cz_sc_styles_mobile', $mobile );
			}
			
		}
	}

	/**
	 * Plugin white label.
	 * 
	 * @since 3.2.0
	 */
	public static function white_label( $plugins ) {

		if ( isset( $plugins['codevz-plus/codevz-plus.php']['Name'] ) ) {

			$name 			= self::option( 'white_label_plugin_name' );
			$description 	= self::option( 'white_label_plugin_description' );
			$author 		= self::option( 'white_label_author' );
			$link 			= self::option( 'white_label_link' );

			if ( $name ) {
				$plugins['codevz-plus/codevz-plus.php']['Name'] = $name;
				$plugins['codevz-plus/codevz-plus.php']['Title'] = $name;
			}
			
			if ( $description ) {
				$plugins['codevz-plus/codevz-plus.php']['Description'] = $description;
			}
			
			if ( $author ) {
				$plugins['codevz-plus/codevz-plus.php']['Author'] = $author;
			}
			
			if ( $link ) {
				$plugins['codevz-plus/codevz-plus.php']['PluginURI'] = $link;
				$plugins['codevz-plus/codevz-plus.php']['AuthorURI'] = $link;
			}

		}

		return $plugins;
	}

} // Codevz_Plus

// Run
Codevz_Plus::instance();


/**
 * VC initial action
 * @return object
 */
function codevz_vc_before_init() {

	// Codevz Elements
	foreach( glob( Codevz_Plus::$dir . 'wpbakery/*.php' ) as $i ) {
		require_once( $i );
		$name = str_replace( '.php', '', basename( $i ) );
		$class = 'Codevz_WPBakery_' . $name;
		$new_class = new $class( 'cz_' . $name );
		$new_class->in();
	}

	// Elements container
	if ( class_exists( 'WPBakeryShortCodesContainer' ) ) {
		class WPBakeryShortCode_cz_acc_child extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_accordion extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_carousel extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_content_box extends WPBakeryShortCodesContainer {}  
		class WPBakeryShortCode_cz_free_position_element extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_history_line extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_parallax extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_popup extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_process_line_vertical extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_show_more_less extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_speech_bubble extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_tab extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_tabs extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_timeline extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_timeline_item extends WPBakeryShortCodesContainer {}
	}

	// Activate VC for post types
	$vc_cpts = (array) get_option( 'codevz_post_types' );
	$vc_cpts[] = 'page';
	$vc_cpts[] = 'post';
	$vc_cpts[] = 'portfolio';
	$vc_cpts[] = 'product';
	vc_set_default_editor_post_types( $vc_cpts );
}
add_action( 'vc_before_init', 'codevz_vc_before_init' );
