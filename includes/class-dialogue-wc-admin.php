<?php
/**
 * Settings page: WooCommerce → Dialogue. Connect, status, sync, disconnect.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens and connection flow.
 */
class Dialogue_WC_Admin {

	const PAGE = 'dialogue-for-woocommerce';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_dialogue_wc_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_dialogue_wc_disconnect', array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_dialogue_wc_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DIALOGUE_WC_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice_not_connected' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
	}

	/**
	 * Suggested text for the site's privacy policy (Settings → Privacy → Policy guide).
	 *
	 * @return void
	 */
	public static function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'This store uses Dialogue (nowdialogue.com) for product recommendations, a shopping assistant and A/B tests.', 'dialogue-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'Dialogue\'s script sets first-party cookies (dai_v_h, dai_v_s) with a random visitor id and session id, used to recognise returning visitors and to connect orders to Dialogue visits. If the store uses a consent banner that supports the WP Consent API, these cookies are only set after the visitor allows statistics cookies.', 'dialogue-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'When an order is paid, the store sends Dialogue the order number, status, totals, currency, coupon codes, the products bought (ids, names, quantities, prices), the random visitor id and whether the order was placed on mobile or desktop. Names, email addresses, phone numbers, addresses and IP addresses are not sent.', 'dialogue-for-woocommerce' ) . '</p>'
			. '<p>' . sprintf(
				/* translators: %s: privacy policy URL */
				esc_html__( 'Dialogue privacy policy: %s', 'dialogue-for-woocommerce' ),
				'<a href="' . esc_url( Dialogue_WC_Settings::PRIVACY_URL ) . '">' . esc_html( Dialogue_WC_Settings::PRIVACY_URL ) . '</a>'
			) . '</p>';
		wp_add_privacy_policy_content( __( 'Dialogue for WooCommerce', 'dialogue-for-woocommerce' ), wp_kses_post( $content ) );
	}

	/**
	 * Settings page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Menu entry.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Dialogue', 'dialogue-for-woocommerce' ), __( 'Dialogue', 'dialogue-for-woocommerce' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Styles for our page only.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'dialogue-wc-admin', DIALOGUE_WC_URL . 'assets/css/admin.css', array(), DIALOGUE_WC_VERSION );
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'dialogue-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Reminder to connect, on the dashboard and plugins screens only.
	 *
	 * @return void
	 */
	public static function notice_not_connected() {
		if ( ! current_user_can( self::CAP ) || Dialogue_WC_Settings::is_connected() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Dialogue is installed but not connected yet.', 'dialogue-for-woocommerce' ),
			esc_url( self::page_url() ),
			esc_html__( 'Connect now', 'dialogue-for-woocommerce' )
		);
	}

	/**
	 * Common checks for our form handlers.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'dialogue-for-woocommerce' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back to the settings page with a message code.
	 *
	 * @param string $code Message code.
	 * @return void
	 */
	private static function back( $code ) {
		wp_safe_redirect( self::page_url( array( 'dialogue_msg' => $code ) ) );
		exit;
	}

	/**
	 * Step 1: register the site with Dialogue, then send the admin to Dialogue to log in (or sign up)
	 * and choose a plan. Dialogue then sends them to WooCommerce's own "approve access" screen
	 * (/wc-auth/v1/authorize), WooCommerce posts read-only API keys to Dialogue and brings the admin
	 * back here, where the connection is finished.
	 *
	 * @return void
	 */
	public static function handle_connect() {
		self::guard( 'dialogue_wc_connect' );

		$return_url = self::page_url(
			array(
				'dialogue_return' => '1',
				'_dnonce'         => wp_create_nonce( 'dialogue_wc_return' ),
			)
		);
		$result     = Dialogue_WC_Api_Client::post_public(
			'/api/woocommerce/connect/start',
			array(
				'site_url'       => home_url( '/' ),
				'rest_url'       => rest_url(),
				'auth_url'       => home_url( '/wc-auth/v1/authorize' ),
				'return_url'     => $return_url,
				'store_name'     => get_bloginfo( 'name' ),
				'currency'       => get_woocommerce_currency(),
				'locale'         => determine_locale(),
				'timezone'       => wp_timezone_string(),
				'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'wp_version'     => get_bloginfo( 'version' ),
				'plugin_version' => DIALOGUE_WC_VERSION,
			)
		);
		if ( is_wp_error( $result ) || empty( $result['site_id'] ) || empty( $result['secret'] ) || empty( $result['connect_url'] ) ) {
			$msg = is_wp_error( $result ) ? $result->get_error_message() : __( 'Unexpected answer from Dialogue.', 'dialogue-for-woocommerce' );
			/* translators: %s: error */
			Dialogue_WC_Log::add( 'error', sprintf( __( 'Could not start the connection: %s', 'dialogue-for-woocommerce' ), $msg ) );
			self::back( 'start_failed' );
		}
		$connect_url = esc_url_raw( (string) $result['connect_url'] );
		$host        = strtolower( (string) wp_parse_url( $connect_url, PHP_URL_HOST ) );
		if ( 'https' !== wp_parse_url( $connect_url, PHP_URL_SCHEME ) || ! in_array( $host, Dialogue_WC_Settings::dialogue_hosts(), true ) ) {
			Dialogue_WC_Log::add( 'error', __( 'Could not start the connection: unexpected Dialogue address.', 'dialogue-for-woocommerce' ) );
			self::back( 'start_failed' );
		}

		Dialogue_WC_Settings::set_secret( (string) $result['secret'] );
		Dialogue_WC_Settings::update_connection(
			array(
				'status'     => Dialogue_WC_Settings::STATUS_PENDING,
				'site_id'    => sanitize_text_field( (string) $result['site_id'] ),
				'last_error' => '',
			)
		);

		add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'allow_dialogue_hosts' ) );
		wp_safe_redirect( $connect_url );
		exit;
	}

	/**
	 * Lets wp_safe_redirect() send the admin to Dialogue's connect page.
	 *
	 * @param string[] $hosts Allowed hosts.
	 * @return string[]
	 */
	public static function allow_dialogue_hosts( $hosts ) {
		return array_merge( (array) $hosts, Dialogue_WC_Settings::dialogue_hosts() );
	}

	/**
	 * Step 2 (back from WooCommerce): ask Dialogue to finish (verify the keys, create or link the store).
	 *
	 * @return string Message code.
	 */
	public static function maybe_finish_connect() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below with our own nonce.
		if ( empty( $_GET['dialogue_return'] ) ) {
			return '';
		}
		$nonce   = isset( $_GET['_dnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_dnonce'] ) ) : '';
		$success = isset( $_GET['success'] ) ? sanitize_text_field( wp_unslash( $_GET['success'] ) ) : '';
		// phpcs:enable
		if ( ! wp_verify_nonce( $nonce, 'dialogue_wc_return' ) || ! current_user_can( self::CAP ) ) {
			return 'bad_nonce';
		}
		if ( '1' !== $success ) {
			Dialogue_WC_Settings::update_connection( array( 'status' => Dialogue_WC_Settings::STATUS_DISCONNECTED ) );
			return 'denied';
		}
		return self::finalize();
	}

	/**
	 * Finish the connection.
	 *
	 * @return string Message code.
	 */
	public static function finalize() {
		$result = Dialogue_WC_Api_Client::signed( 'POST', '/api/woocommerce/connect/finalize', array( 'site_url' => home_url( '/' ) ), 30 );
		if ( is_wp_error( $result ) || empty( $result['store_id'] ) ) {
			$msg = is_wp_error( $result ) ? $result->get_error_message() : __( 'Unexpected answer from Dialogue.', 'dialogue-for-woocommerce' );
			Dialogue_WC_Settings::update_connection( array( 'last_error' => $msg ) );
			/* translators: %s: error */
			Dialogue_WC_Log::add( 'error', sprintf( __( 'Could not finish the connection: %s', 'dialogue-for-woocommerce' ), $msg ) );
			return 'finish_failed';
		}
		Dialogue_WC_Settings::update_connection(
			array(
				'status'        => Dialogue_WC_Settings::STATUS_CONNECTED,
				'store_id'      => absint( $result['store_id'] ),
				'user_id'       => isset( $result['user_id'] ) ? absint( $result['user_id'] ) : 0,
				'key_id'        => isset( $result['key_id'] ) ? absint( $result['key_id'] ) : 0,
				'dashboard_url' => isset( $result['dashboard_url'] ) ? esc_url_raw( (string) $result['dashboard_url'] ) : '',
				'bundle_url'    => isset( $result['bundle_url'] ) ? esc_url_raw( (string) $result['bundle_url'] ) : '',
				'connected_at'  => gmdate( 'c' ),
				'last_error'    => '',
			)
		);
		Dialogue_WC_Log::add( 'info', __( 'Connected to Dialogue. Products are being imported.', 'dialogue-for-woocommerce' ) );
		return 'connected';
	}

	/**
	 * Ask Dialogue to re-import all products now.
	 *
	 * @return void
	 */
	public static function handle_sync() {
		self::guard( 'dialogue_wc_sync' );
		$result = Dialogue_WC_Api_Client::signed( 'POST', '/api/woocommerce/sync', array( 'mode' => 'full' ) );
		self::back( is_wp_error( $result ) ? 'sync_failed' : 'sync_started' );
	}

	/**
	 * Disconnect: tell Dialogue, remove the API key WooCommerce created for Dialogue, forget the secret.
	 *
	 * @return void
	 */
	public static function handle_disconnect() {
		global $wpdb;
		self::guard( 'dialogue_wc_disconnect' );
		$conn = Dialogue_WC_Settings::connection();
		Dialogue_WC_Api_Client::signed( 'POST', '/api/woocommerce/disconnect', array( 'reason' => 'merchant' ), 10 );
		if ( ! empty( $conn['key_id'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- WooCommerce has no API to delete a REST key.
			$wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => absint( $conn['key_id'] ) ), array( '%d' ) );
		}
		Dialogue_WC_Queue::clear( array( Dialogue_WC_Products::FLUSH_HOOK ) );
		Dialogue_WC_Settings::reset();
		Dialogue_WC_Log::add( 'info', __( 'Disconnected from Dialogue.', 'dialogue-for-woocommerce' ) );
		self::back( 'disconnected' );
	}

	/**
	 * Human messages for the codes above.
	 *
	 * @param string $code Code.
	 * @return array{0: string, 1: string} [type, text]
	 */
	private static function message( $code ) {
		$map = array(
			'connected'     => array( 'success', __( 'Your store is connected to Dialogue. Products are being imported; this can take a few minutes.', 'dialogue-for-woocommerce' ) ),
			'denied'        => array( 'warning', __( 'Access was not approved, so the store is not connected. Click "Connect to Dialogue" to try again.', 'dialogue-for-woocommerce' ) ),
			'needs_login'   => array( 'warning', __( 'Please finish logging in to Dialogue (and choosing a plan), then click "Connect to Dialogue" again.', 'dialogue-for-woocommerce' ) ),
			'start_failed'  => array( 'error', __( 'Could not reach Dialogue. Check that your site can make outgoing connections and try again.', 'dialogue-for-woocommerce' ) ),
			'finish_failed' => array( 'error', __( 'Access was approved but Dialogue could not finish connecting. Please try again, or contact Dialogue support.', 'dialogue-for-woocommerce' ) ),
			'bad_nonce'     => array( 'error', __( 'This link has expired. Please click "Connect to Dialogue" again.', 'dialogue-for-woocommerce' ) ),
			'disconnected'  => array( 'success', __( 'Your store was disconnected from Dialogue.', 'dialogue-for-woocommerce' ) ),
			'sync_started'  => array( 'success', __( 'Product import started. Changes show in Dialogue within a few minutes.', 'dialogue-for-woocommerce' ) ),
			'sync_failed'   => array( 'error', __( 'Could not start the product import. Please try again in a minute.', 'dialogue-for-woocommerce' ) ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : array( '', '' );
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$code = self::maybe_finish_connect();
		if ( '' === $code && isset( $_GET['dialogue_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			$code = sanitize_key( wp_unslash( $_GET['dialogue_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		list( $type, $text ) = self::message( $code );
		$conn                = Dialogue_WC_Settings::connection();
		$connected           = Dialogue_WC_Settings::is_connected();
		?>
		<div class="wrap dialogue-wc">
			<h1><?php esc_html_e( 'Dialogue', 'dialogue-for-woocommerce' ); ?></h1>
			<?php if ( $text ) : ?>
				<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $text ); ?></p></div>
			<?php endif; ?>

			<div class="dialogue-wc-card">
				<?php if ( $connected ) : ?>
					<p class="dialogue-wc-status dialogue-wc-status--on"><?php esc_html_e( 'Connected', 'dialogue-for-woocommerce' ); ?></p>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Dialogue store ID', 'dialogue-for-woocommerce' ); ?></th><td><code><?php echo esc_html( (string) $conn['store_id'] ); ?></code></td></tr>
						<?php if ( $conn['connected_at'] ) : ?>
							<tr><th scope="row"><?php esc_html_e( 'Connected since', 'dialogue-for-woocommerce' ); ?></th><td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $conn['connected_at'] ) ) ); ?></td></tr>
						<?php endif; ?>
					</table>
					<p><?php esc_html_e( 'Dialogue loads on your store automatically, your products stay in sync, and paid orders are counted toward Dialogue results. No customer names, emails or addresses are sent.', 'dialogue-for-woocommerce' ); ?></p>
					<p class="dialogue-wc-actions">
						<a class="button button-primary" href="<?php echo esc_url( $conn['dashboard_url'] ? $conn['dashboard_url'] : Dialogue_WC_Settings::DEFAULT_DASHBOARD ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Dialogue dashboard', 'dialogue-for-woocommerce' ); ?></a>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dialogue-wc-inline">
						<input type="hidden" name="action" value="dialogue_wc_sync" />
						<?php wp_nonce_field( 'dialogue_wc_sync' ); ?>
						<?php submit_button( __( 'Sync products now', 'dialogue-for-woocommerce' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dialogue-wc-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Disconnect your store from Dialogue? Dialogue will stop showing on your store.', 'dialogue-for-woocommerce' ) ); ?>');">
						<input type="hidden" name="action" value="dialogue_wc_disconnect" />
						<?php wp_nonce_field( 'dialogue_wc_disconnect' ); ?>
						<?php submit_button( __( 'Disconnect', 'dialogue-for-woocommerce' ), 'delete', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<p class="dialogue-wc-status"><?php echo esc_html( Dialogue_WC_Settings::STATUS_PENDING === $conn['status'] ? __( 'Waiting for approval', 'dialogue-for-woocommerce' ) : __( 'Not connected', 'dialogue-for-woocommerce' ) ); ?></p>
					<p><?php esc_html_e( 'Connect your store to add Dialogue\'s AI shopping assistant, product recommendations, Reels and A/B tests. You will log in to Dialogue (or create an account), choose a plan, and approve read-only access on WooCommerce\'s own screen. Dialogue only reads your products and never changes your store.', 'dialogue-for-woocommerce' ); ?></p>
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: 1: terms URL, 2: privacy policy URL */
								__( 'By connecting you agree to Dialogue\'s <a href="%1$s" target="_blank" rel="noopener noreferrer">Terms of Service</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.', 'dialogue-for-woocommerce' ),
								array(
									'a' => array(
										'href'   => array(),
										'target' => array(),
										'rel'    => array(),
									),
								)
							),
							esc_url( Dialogue_WC_Settings::TERMS_URL ),
							esc_url( Dialogue_WC_Settings::PRIVACY_URL )
						);
						?>
					</p>
					<?php if ( $conn['last_error'] ) : ?>
						<p class="dialogue-wc-error"><?php echo esc_html( $conn['last_error'] ); ?></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="dialogue_wc_connect" />
						<?php wp_nonce_field( 'dialogue_wc_connect' ); ?>
						<?php submit_button( __( 'Connect to Dialogue', 'dialogue-for-woocommerce' ), 'primary large', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>

			<?php $entries = Dialogue_WC_Log::entries(); ?>
			<?php if ( $entries ) : ?>
				<h2><?php esc_html_e( 'Recent activity', 'dialogue-for-woocommerce' ); ?></h2>
				<table class="widefat striped dialogue-wc-log">
					<tbody>
					<?php foreach ( array_slice( $entries, 0, 15 ) as $e ) : ?>
						<tr class="dialogue-wc-log--<?php echo esc_attr( $e['level'] ); ?>">
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $e['time'] ) ); ?></td>
							<td><?php echo esc_html( $e['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
