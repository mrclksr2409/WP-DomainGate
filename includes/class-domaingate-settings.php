<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Einstellungsseite unter "Einstellungen → DomainGate".
 */
class DomainGate_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/domaingate.php' ), array( $this, 'action_links' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'DomainGate', 'domaingate' ),
			__( 'DomainGate', 'domaingate' ),
			'manage_options',
			'domaingate',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'domaingate',
			DOMAINGATE_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Normalisiert die Eingabe auf einen Eintrag pro Zeile und verwirft ungültige Einträge.
	 */
	public function sanitize( $value ) {
		$valid = array();
		foreach ( DomainGate_Validator::parse_list( $value ) as $entry ) {
			$check = 0 === strpos( $entry, '*.' ) ? substr( $entry, 2 ) : $entry;
			if ( preg_match( '/^([a-z0-9-]+\.)+[a-z0-9-]{2,}$/', $check ) ) {
				$valid[] = $entry;
			} else {
				add_settings_error(
					DOMAINGATE_OPTION,
					'domaingate_invalid',
					/* translators: %s: ungültiger Eintrag */
					sprintf( __( 'Ungültiger Eintrag ignoriert: %s', 'domaingate' ), esc_html( $entry ) )
				);
			}
		}
		return implode( "\n", $valid );
	}

	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=domaingate' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'domaingate' ) . '</a>' );
		return $links;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$value = get_option( DOMAINGATE_OPTION, '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DomainGate', 'domaingate' ); ?></h1>
			<?php if ( ! get_option( 'users_can_register' ) && ! is_multisite() ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'Hinweis: Die Registrierung ist derzeit unter „Einstellungen → Allgemein“ deaktiviert.', 'domaingate' ); ?>
				</p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'domaingate' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="domaingate_domains"><?php esc_html_e( 'Erlaubte Domains', 'domaingate' ); ?></label></th>
						<td>
							<textarea id="domaingate_domains" name="<?php echo esc_attr( DOMAINGATE_OPTION ); ?>" rows="8" cols="50" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Eine Domain pro Zeile, z. B. domain.de oder *@domain.de. Mit *.domain.de werden alle Subdomains erlaubt. Ist die Liste leer, ist keine Einschränkung aktiv.', 'domaingate' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
