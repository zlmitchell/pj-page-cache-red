<?php 

class RedisFullPageCache {
	private $redis_full_page_cache_options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'redis_full_page_cache_add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'redis_full_page_cache_page_init' ) );
        add_action( 'admin_bar_init', array( $this, 'purge') );
        add_action( 'admin_bar_menu', array( $this, 'redis_cache_toolbar_purge_link'), 100 );
	}

	/**
	 * Purge all urls.
	 * Purge current page cache when purging is requested from front
	 * and all urls when requested from admin dashboard.
	 */
	public function purge() {

		global $wp;

		$method = filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING );

		if ( 'POST' === $method ) {
			$action = filter_input( INPUT_POST, 'redis_full_page_cache_action', FILTER_SANITIZE_STRING );
		} else {
			$action = filter_input( INPUT_GET, 'redis_full_page_cache_action', FILTER_SANITIZE_STRING );
		}

		if ( empty( $action ) || 'done' === $action ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sorry, you do not have the necessary privileges to edit these options.' );
		}

		$current_url = $wp->request;
        $current_url_slashed = "/" . trailingslashit( $current_url );

        // If not on admin dashboard update the action to purge_current_page
		if ( ! is_admin() ) {
			$action       = 'purge_current_page';
			$redirect_url = $current_url . "?redis_full_page_cache_action=done";
		} else {
			$redirect_url = $current_url . "/wp-admin/?redis_full_page_cache_action=done";
		}

		switch ( $action ) {
			case 'purge':
                $purge = new Redis_Page_Cache();
                $purge->clear_all_cache();
				break;
			case 'purge_current_page':
                $purge = new Redis_Page_Cache();
				$purge->clear_cache_by_url( $current_url_slashed, $expire = true );
				break;
		}

		wp_redirect( esc_url_raw( trailingslashit(get_option( 'home' )) . $redirect_url ) );
		exit();

	}

	/**
	 * Function to add toolbar purge link.
	 *
	 * @param object $wp_admin_bar Admin bar object.
	 */
	public function redis_cache_toolbar_purge_link( $wp_admin_bar ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( is_admin() ) {
			$redis_full_page_cache_urls = 'all';
			$link_title        = __( 'Purge All Cache', 'redis-full-page-cache' );
		} else {
			$redis_full_page_cache_urls = 'current-url';
			$link_title        = __( 'Purge Current Page', 'redis-full-page-cache' );
		}

		$purge_url = add_query_arg(
			array(
				'redis_full_page_cache_action' => 'purge',
				'redis_full_page_cache_urls'   => $redis_full_page_cache_urls,
			)
		);

		$nonced_url = wp_nonce_url( $purge_url, 'redis-cache-purge_all' );

		$wp_admin_bar->add_menu(
			array(
				'id'    => 'redis-cache-purge-all',
				'title' => $link_title,
				'href'  => $nonced_url,
				'meta'  => array( 'title' => $link_title ),
			)
		);

	}

	public function redis_full_page_cache_add_plugin_page() {
		add_options_page(
			'Redis Full Page Cache', // page_title
			'Redis Full Page Cache', // menu_title
			'manage_options', // capability
			'redis-full-page-cache', // menu_slug
			array( $this, 'redis_full_page_cache_create_admin_page' ) // function
		);
	}

	public function redis_full_page_cache_create_admin_page() {
		$this->redis_full_page_cache_options = get_option( 'redis_full_page_cache_option_name' ); ?>

		<div class="wrap">
			<h2>Redis Full Page Cache</h2>
			<p>These settings add additional options when a page is purged</p>
            <div>Fields are comma seperated (e.g "/test/,/test2/")</div>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( 'redis_full_page_cache_option_group' );
					do_settings_sections( 'redis-full-page-cache-admin' );
					submit_button();
				?>
			</form>
		</div>
	<?php }

	public function redis_full_page_cache_page_init() {
		register_setting(
			'redis_full_page_cache_option_group', // option_group
			'redis_full_page_cache_option_name', // option_name
			array( $this, 'redis_full_page_cache_sanitize' ) // sanitize_callback
		);

		add_settings_section(
			'redis_full_page_cache_setting_section', // id
			'Settings', // title
			array( $this, 'redis_full_page_cache_section_info' ), // callback
			'redis-full-page-cache-admin' // page
		);

		add_settings_field(
			'always_purge_urls_0', // id
			'Always Purge URLs', // title
			array( $this, 'always_purge_urls_0_callback' ), // callback
			'redis-full-page-cache-admin', // page
			'redis_full_page_cache_setting_section' // section
		);

	}

	public function redis_full_page_cache_sanitize($input) {
		$sanitary_values = array();
		if ( isset( $input['always_purge_urls_0'] ) ) {
			$sanitary_values['always_purge_urls_0'] = sanitize_text_field( $input['always_purge_urls_0'] );
		}

		return $sanitary_values;
	}

	public function redis_full_page_cache_section_info() {
		
	}

	public function always_purge_urls_0_callback() {
        $overrides = '';
        if (getenv('ALWAYS_PURGE_URLS') !== null) {
            $overrides = ',' . getenv('ALWAYS_PURGE_URLS');
        }
		printf(
            '<div>These URLs will always be purged on each post update/publish</div>
            <input class="regular-text" type="text" name="redis_full_page_cache_option_name[always_purge_urls_0]" id="always_purge_urls_0" value="%s">
            <div><strong>Current Overrides: "/' . $overrides . '"</div></strong>',
			isset( $this->redis_full_page_cache_options['always_purge_urls_0'] ) ? esc_attr( $this->redis_full_page_cache_options['always_purge_urls_0']) : ''
		);
	}

}

$redis_full_page_cache = new RedisFullPageCache();