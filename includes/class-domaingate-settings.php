<?php
/**
 * Settings page and option handling for DomainGate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DomainGate_Settings {

	const OPTION_KEY = 'domaingate_settings';
	const PAGE_SLUG  = 'domaingate';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DOMAINGATE_FILE ), array( $this, 'action_links' ) );
	}

	public static function defaults() {
		return array(
			'enabled'         => 1,
			'allowed_domains' => '',
			'beta_updates'    => 0,
		);
	}

	public static function get_settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	public static function set_defaults_on_activate() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, self::defaults() );
		}
	}

	public function register_settings() {
		register_setting(
			'domaingate_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'domaingate_section_main',
			__( 'Domain-Whitelist', 'domaingate' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field( 'enabled', __( 'Plugin aktiv', 'domaingate' ), array( $this, 'field_enabled' ), self::PAGE_SLUG, 'domaingate_section_main' );
		add_settings_field( 'allowed_domains', __( 'Erlaubte Domains', 'domaingate' ), array( $this, 'field_allowed_domains' ), self::PAGE_SLUG, 'domaingate_section_main' );

		add_settings_section(
			'domaingate_section_updates',
			__( 'Updates', 'domaingate' ),
			array( $this, 'render_section_updates' ),
			self::PAGE_SLUG
		);

		add_settings_field( 'beta_updates', __( 'Beta-Updates', 'domaingate' ), array( $this, 'field_beta_updates' ), self::PAGE_SLUG, 'domaingate_section_updates' );
	}

	public function register_menu() {
		add_options_page(
			__( 'DomainGate', 'domaingate' ),
			__( 'DomainGate', 'domaingate' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		return array_merge(
			array( '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'domaingate' ) . '</a>' ),
			$links
		);
	}

	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		$out['enabled']      = ! empty( $input['enabled'] ) ? 1 : 0;
		$out['beta_updates'] = ! empty( $input['beta_updates'] ) ? 1 : 0;

		// Switching the update channel drops the cached update state so the
		// next check reads the version from the other branch.
		$current = self::get_settings();
		if ( $out['beta_updates'] !== (int) $current['beta_updates'] ) {
			delete_site_option( 'external_updates-' . DomainGate_Updater::SLUG );
			delete_site_transient( 'update_plugins' );
		}

		$valid = array();
		$raw   = isset( $input['allowed_domains'] ) ? $input['allowed_domains'] : '';
		foreach ( DomainGate_Validator::parse_list( $raw ) as $entry ) {
			if ( DomainGate_Validator::is_valid_entry( $entry ) ) {
				$valid[] = $entry;
			} else {
				add_settings_error(
					self::OPTION_KEY,
					'domaingate_invalid',
					/* translators: %s: invalid entry */
					sprintf( __( 'Ungültiger Eintrag ignoriert: %s', 'domaingate' ), $entry ),
					'warning'
				);
			}
		}
		$out['allowed_domains'] = implode( "\n", $valid );

		return $out;
	}

	public function render_section_intro() {
		echo '<p>' . esc_html__( 'Neue Benutzer können sich nur registrieren, wenn ihre E-Mail-Adresse zu einer der hier eingetragenen Domains gehört.', 'domaingate' ) . '</p>';

		if ( ! is_multisite() && ! get_option( 'users_can_register' ) ) {
			// Alert output is escaped by WPB_Admin_UI::alert().
			echo WPB_Admin_UI::alert( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				__( 'Die Registrierung ist unter „Einstellungen → Allgemein“ derzeit deaktiviert. DomainGate greift erst, wenn sie dort aktiviert ist.', 'domaingate' ),
				'warning'
			);
		}
	}

	public function field_enabled() {
		$s = self::get_settings();
		// Toggle output is escaped by WPB_Admin_UI::toggle().
		echo WPB_Admin_UI::toggle( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			self::OPTION_KEY . '[enabled]',
			1 === (int) $s['enabled'],
			__( 'Registrierungen auf die erlaubten Domains beschränken', 'domaingate' )
		);
	}

	public function field_allowed_domains() {
		$s = self::get_settings();
		printf(
			'<textarea id="domaingate-allowed-domains" name="%1$s[allowed_domains]" rows="8" class="large-text code">%2$s</textarea>',
			esc_attr( self::OPTION_KEY ),
			esc_textarea( $s['allowed_domains'] )
		);
		echo '<p class="description">' . esc_html__( 'Eine Domain pro Zeile, z. B. domain.de oder *@domain.de. Mit *.domain.de sind alle Subdomains erlaubt (nicht domain.de selbst). Ist die Liste leer, wird niemand blockiert.', 'domaingate' ) . '</p>';
	}

	public function render_section_updates() {
		echo '<p>' . esc_html__( 'Updates werden direkt aus GitHub geladen.', 'domaingate' ) . '</p>';
	}

	public function field_beta_updates() {
		$s = self::get_settings();
		// Toggle output is escaped by WPB_Admin_UI::toggle().
		echo WPB_Admin_UI::toggle( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			self::OPTION_KEY . '[beta_updates]',
			1 === (int) $s['beta_updates'],
			__( 'Beta-Versionen installieren (Branch „beta“ statt „main“)', 'domaingate' )
		);
		echo '<p class="description">' . esc_html__( 'Beta-Versionen enthalten neue Funktionen vor dem offiziellen Release und können Fehler enthalten. Nach dem Zurückschalten auf stabile Updates wird erst wieder aktualisiert, sobald die Version auf „main“ höher ist als die installierte Beta.', 'domaingate' ) . '</p>';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<?php
			WPB_Admin_UI::header(
				array(
					'title'    => 'DomainGate',
					'subtitle' => __( 'Erlaubt die Registrierung nur für E-Mail-Adressen aus freigegebenen Domains.', 'domaingate' ),
					'icon'     => 'dashicons-shield',
					'version'  => DOMAINGATE_VERSION,
				)
			);
			?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'domaingate_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
