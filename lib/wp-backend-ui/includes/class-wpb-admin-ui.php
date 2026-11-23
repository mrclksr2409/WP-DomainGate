<?php
/**
 * WP-Backend UI — screen registration, asset loading and markup helpers.
 *
 * @package WP_Backend_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared admin UI for all plugins.
 *
 * Only loaded through wp-backend-ui.php, never directly.
 */
final class WPB_Admin_UI {

	/**
	 * Library version (also used as asset version).
	 */
	const VERSION = '1.1.0';

	/**
	 * Asset handle (shared by every plugin bundling the library).
	 */
	const HANDLE = 'wpb-admin-ui';

	/**
	 * Body class that activates the core layer of the stylesheet.
	 */
	const BODY_CLASS = 'wpb-admin';

	/**
	 * Absolute path of the loaded loader file.
	 *
	 * @var string
	 */
	private static $file = '';

	/**
	 * Registered ?page= slugs.
	 *
	 * @var string[]
	 */
	private static $pages = array();

	/**
	 * Registered screen IDs.
	 *
	 * @var string[]
	 */
	private static $screens = array();

	/**
	 * Hook everything up. Called once by wpb_admin_ui_boot().
	 *
	 * @param string $file Loader file of the copy that won the version check.
	 * @return void
	 */
	public static function init( $file ) {
		self::$file = $file;

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Register screens that get the shared UI.
	 *
	 * @param array $args {
	 *     @type string[] $pages   Admin page slugs as used in ?page= (e.g. 'auto-quill-settings').
	 *     @type string[] $screens Screen IDs (e.g. 'settings_page_pixel-diet', 'edit-post').
	 * }
	 * @return void
	 */
	public static function register( array $args ) {
		if ( ! empty( $args['pages'] ) ) {
			self::$pages = array_unique( array_merge( self::$pages, array_map( 'sanitize_key', (array) $args['pages'] ) ) );
		}
		if ( ! empty( $args['screens'] ) ) {
			self::$screens = array_unique( array_merge( self::$screens, array_map( 'sanitize_key', (array) $args['screens'] ) ) );
		}
	}

	/**
	 * Whether the current admin request is a registered screen.
	 *
	 * @return bool
	 */
	public static function is_active_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && in_array( $page, self::$pages, true ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && in_array( $screen->id, self::$screens, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add the scope class on registered screens.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( self::is_active_screen() ) {
			$classes .= ' ' . self::BODY_CLASS;
		}
		return $classes;
	}

	/**
	 * Enqueue assets on registered screens.
	 *
	 * @return void
	 */
	public static function maybe_enqueue() {
		if ( self::is_active_screen() ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Enqueue the stylesheet and script.
	 *
	 * Call this directly to use the .wpb-* components on screens that are not
	 * registered (e.g. a meta box in the post editor). The core layer only
	 * applies to registered screens.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( wp_style_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( self::HANDLE, self::asset_url( 'assets/css/wpb-admin.css' ), array(), self::VERSION );
		wp_enqueue_script( self::HANDLE, self::asset_url( 'assets/js/wpb-admin.js' ), array(), self::VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'wpbAdminUIL10n',
			array(
				'copied'           => __( 'Copied to clipboard.', 'wp-backend-ui' ),
				'copyFailed'       => __( 'Copy failed.', 'wp-backend-ui' ),
				'show'             => __( 'Show', 'wp-backend-ui' ),
				'hide'             => __( 'Hide', 'wp-backend-ui' ),
				'close'            => __( 'Close', 'wp-backend-ui' ),
				'remove'           => __( 'Remove', 'wp-backend-ui' ),
				'search'           => __( 'Search', 'wp-backend-ui' ),
				'searching'        => __( 'Searching…', 'wp-backend-ui' ),
				'noResults'        => __( 'No results found.', 'wp-backend-ui' ),
				'loadFailed'       => __( 'Could not load results.', 'wp-backend-ui' ),
				'reorder'          => __( 'Move (drag or use the arrow keys)', 'wp-backend-ui' ),
				/* translators: 1: new position, 2: number of items. */
				'moved'            => __( 'Moved to position %1$d of %2$d.', 'wp-backend-ui' ),
				'mediaUnavailable' => __( 'The media library is not available on this screen.', 'wp-backend-ui' ),
			)
		);
	}

	/**
	 * URL of a file inside the library.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function asset_url( $path ) {
		return plugins_url( ltrim( $path, '/' ), self::$file );
	}

	/**
	 * Render the page header. Use it as the first element inside .wrap.
	 *
	 * Also prints <hr class="wp-header-end">, so WordPress moves admin notices
	 * below the header instead of into it.
	 *
	 * @param array $args {
	 *     @type string $title    Page title. Default: get_admin_page_title().
	 *     @type string $subtitle Optional one-line description.
	 *     @type string $icon     Dashicon slug (e.g. 'dashicons-email-alt') or image URL.
	 *     @type string $version  Optional version shown as badge.
	 *     @type array  $actions  List of array( 'label', 'url', 'primary' => bool, 'target' => '_blank' ).
	 * }
	 * @return void
	 */
	public static function header( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'    => get_admin_page_title(),
				'subtitle' => '',
				'icon'     => '',
				'version'  => '',
				'actions'  => array(),
			)
		);
		?>
		<div class="wpb-header">
			<div class="wpb-header__brand">
				<?php if ( '' !== $args['icon'] ) : ?>
					<?php if ( 0 === strpos( $args['icon'], 'dashicons-' ) ) : ?>
						<span class="wpb-header__icon dashicons <?php echo esc_attr( $args['icon'] ); ?>" aria-hidden="true"></span>
					<?php else : ?>
						<span class="wpb-header__icon" aria-hidden="true"><img src="<?php echo esc_url( $args['icon'] ); ?>" alt="" /></span>
					<?php endif; ?>
				<?php endif; ?>
				<div>
					<h1 class="wpb-header__title">
						<?php echo esc_html( $args['title'] ); ?>
						<?php if ( '' !== $args['version'] ) : ?>
							<span class="wpb-badge"><?php echo esc_html( 'v' . ltrim( $args['version'], 'v' ) ); ?></span>
						<?php endif; ?>
					</h1>
					<?php if ( '' !== $args['subtitle'] ) : ?>
						<p class="wpb-header__subtitle"><?php echo esc_html( $args['subtitle'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( ! empty( $args['actions'] ) ) : ?>
				<div class="wpb-header__actions">
					<?php foreach ( $args['actions'] as $action ) : ?>
						<?php
						$action = wp_parse_args(
							$action,
							array(
								'label'   => '',
								'url'     => '#',
								'primary' => false,
								'target'  => '',
							)
						);
						?>
						<a class="button<?php echo $action['primary'] ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $action['url'] ); ?>"<?php echo '' !== $action['target'] ? ' target="' . esc_attr( $action['target'] ) . '" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $action['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<hr class="wp-header-end" />
		<?php
	}

	/**
	 * Render server-side tabs (one URL per tab).
	 *
	 * @param array  $tabs   Map of tab key => label.
	 * @param string $active Active tab key.
	 * @param array  $args {
	 *     @type string $base_url  URL the tab argument is added to. Default: current admin page.
	 *     @type string $query_arg Query argument name. Default 'tab'.
	 *     @type string $label     Accessible label for the nav element.
	 * }
	 * @return void
	 */
	public static function tabs( array $tabs, $active, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'base_url'  => self::current_page_url(),
				'query_arg' => 'tab',
				'label'     => __( 'Secondary menu', 'wp-backend-ui' ),
			)
		);

		echo '<nav class="nav-tab-wrapper wpb-tabs" aria-label="' . esc_attr( $args['label'] ) . '">';
		foreach ( $tabs as $key => $label ) {
			$is_active = (string) $key === (string) $active;
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( add_query_arg( $args['query_arg'], $key, $args['base_url'] ) ),
				$is_active ? ' nav-tab-active' : '',
				$is_active ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Open a card. Close it with card_end().
	 *
	 * @param array $args {
	 *     @type string $title       Card title.
	 *     @type string $description Text below the title.
	 *     @type string $actions     Trusted HTML for the header's right side (escape before passing).
	 *     @type string $class       Extra CSS classes for the card.
	 *     @type bool   $flush       Remove body padding (for tables). Default false.
	 * }
	 * @return void
	 */
	public static function card_start( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'       => '',
				'description' => '',
				'actions'     => '',
				'class'       => '',
				'flush'       => false,
			)
		);

		echo '<section class="' . esc_attr( trim( 'wpb-card ' . $args['class'] ) ) . '">';

		if ( '' !== $args['title'] || '' !== $args['actions'] ) {
			echo '<header class="wpb-card__header"><div>';
			if ( '' !== $args['title'] ) {
				echo '<h2 class="wpb-card__title">' . esc_html( $args['title'] ) . '</h2>';
			}
			if ( '' !== $args['description'] ) {
				echo '<p class="wpb-card__description">' . esc_html( $args['description'] ) . '</p>';
			}
			echo '</div>';
			if ( '' !== $args['actions'] ) {
				echo '<div class="wpb-row">' . wp_kses_post( $args['actions'] ) . '</div>';
			}
			echo '</header>';
		}

		echo '<div class="wpb-card__body' . ( $args['flush'] ? ' wpb-card__body--flush' : '' ) . '">';
	}

	/**
	 * Close a card opened with card_start().
	 *
	 * @param string $footer Optional trusted footer HTML (escape before passing).
	 * @return void
	 */
	public static function card_end( $footer = '' ) {
		echo '</div>';
		if ( '' !== $footer ) {
			echo '<footer class="wpb-card__footer">' . $footer . '</footer>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- documented as trusted, may contain form controls.
		}
		echo '</section>';
	}

	/**
	 * Badge markup.
	 *
	 * @param string $label   Text.
	 * @param string $variant neutral|success|warning|error|info|accent.
	 * @param bool   $dot     Show a status dot.
	 * @return string Escaped HTML.
	 */
	public static function badge( $label, $variant = 'neutral', $dot = false ) {
		$classes = 'wpb-badge';
		if ( 'neutral' !== $variant ) {
			$classes .= ' wpb-badge--' . sanitize_html_class( $variant );
		}
		if ( $dot ) {
			$classes .= ' wpb-badge--dot';
		}
		return '<span class="' . esc_attr( $classes ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Toggle switch markup (a styled checkbox, works with the Settings API).
	 *
	 * @param string $name    Input name.
	 * @param bool   $checked Current state.
	 * @param string $label   Visible label.
	 * @param array  $args {
	 *     @type string $id    Input ID.
	 *     @type string $value Submitted value. Default '1'.
	 *     @type bool   $disabled
	 * }
	 * @return string Escaped HTML.
	 */
	public static function toggle( $name, $checked, $label, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'       => '',
				'value'    => '1',
				'disabled' => false,
			)
		);

		return sprintf(
			'<label class="wpb-toggle"><input type="checkbox" name="%1$s" value="%2$s"%3$s%4$s%5$s /><span class="wpb-toggle__track" aria-hidden="true"></span><span>%6$s</span></label>',
			esc_attr( $name ),
			esc_attr( $args['value'] ),
			'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '',
			checked( $checked, true, false ),
			disabled( $args['disabled'], true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Inline alert markup (stays where it is printed, unlike .notice).
	 *
	 * @param string $message Text.
	 * @param string $variant info|success|warning|error.
	 * @param string $title   Optional bold title.
	 * @return string Escaped HTML.
	 */
	public static function alert( $message, $variant = 'info', $title = '' ) {
		$icons = array(
			'info'    => 'dashicons-info-outline',
			'success' => 'dashicons-yes-alt',
			'warning' => 'dashicons-warning',
			'error'   => 'dashicons-dismiss',
		);
		$icon  = isset( $icons[ $variant ] ) ? $icons[ $variant ] : $icons['info'];
		$role  = 'error' === $variant ? 'alert' : 'status';

		return sprintf(
			'<div class="wpb-alert wpb-alert--%1$s" role="%2$s"><span class="dashicons %3$s" aria-hidden="true"></span><div class="wpb-alert__body">%4$s<p>%5$s</p></div></div>',
			esc_attr( sanitize_html_class( $variant ) ),
			esc_attr( $role ),
			esc_attr( $icon ),
			'' !== $title ? '<strong class="wpb-alert__title">' . esc_html( $title ) . '</strong>' : '',
			esc_html( $message )
		);
	}

	/**
	 * Drag handle for a sortable list item (also works with the arrow keys).
	 *
	 * @param string $label Accessible label. Default: generic move hint.
	 * @return string Escaped HTML.
	 */
	public static function sortable_handle( $label = '' ) {
		if ( '' === $label ) {
			$label = __( 'Move (drag or use the arrow keys)', 'wp-backend-ui' );
		}
		return '<button type="button" class="wpb-sortable__handle" aria-label="' . esc_attr( $label ) . '"><span class="dashicons dashicons-move" aria-hidden="true"></span></button>';
	}

	/**
	 * Open an accordion section. Close it with accordion_end().
	 *
	 * Buttons and links inside the header do not toggle the section.
	 *
	 * @param array $args {
	 *     @type string $title   Section title.
	 *     @type string $meta    Trusted HTML right of the title, e.g. a badge (escape before passing).
	 *     @type string $actions Trusted HTML for buttons in the header (escape before passing).
	 *     @type bool   $open    Initially open. Default false.
	 *     @type string $name    Sections with the same name open exclusively.
	 *     @type string $id      Element ID.
	 *     @type string $class   Extra CSS classes.
	 *     @type bool   $sortable Make this section an item of a sortable list, with a drag handle. Default false.
	 * }
	 * @return void
	 */
	public static function accordion_start( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'    => '',
				'meta'     => '',
				'actions'  => '',
				'open'     => false,
				'name'     => '',
				'id'       => '',
				'class'    => '',
				'sortable' => false,
			)
		);

		$classes = trim( 'wpb-accordion ' . ( $args['sortable'] ? 'wpb-sortable__item ' : '' ) . $args['class'] );

		printf(
			'<details class="%1$s"%2$s%3$s%4$s>',
			esc_attr( $classes ),
			$args['open'] ? ' open' : '',
			'' !== $args['name'] ? ' name="' . esc_attr( $args['name'] ) . '"' : '',
			'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''
		);
		echo '<summary class="wpb-accordion__header">';
		if ( $args['sortable'] ) {
			echo self::sortable_handle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in sortable_handle().
		}
		echo '<span class="wpb-accordion__title">' . esc_html( $args['title'] ) . '</span>';
		if ( '' !== $args['meta'] ) {
			echo '<span class="wpb-accordion__meta">' . $args['meta'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- documented as trusted.
		}
		if ( '' !== $args['actions'] ) {
			echo '<span class="wpb-accordion__actions">' . $args['actions'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- documented as trusted, may contain form controls.
		}
		echo '</summary><div class="wpb-accordion__body">';
	}

	/**
	 * Close an accordion section opened with accordion_start().
	 *
	 * @return void
	 */
	public static function accordion_end() {
		echo '</div></details>';
	}

	/**
	 * Searchable select with chips (enhances a native <select>).
	 *
	 * A remote source receives ?search=term and returns JSON, either a plain list
	 * or the wp_send_json_success() envelope, of array( 'value' => …, 'label' => … ).
	 * List the current selection in $options, so its labels are known on load.
	 *
	 * @param string $name     Input name. '[]' is added automatically when multiple.
	 * @param array  $options  Map of value => label.
	 * @param mixed  $selected Selected value or list of values.
	 * @param array  $args {
	 *     @type string $id          Element ID.
	 *     @type bool   $multiple    Allow several values. Default true.
	 *     @type string $placeholder Placeholder text, also the accessible label unless $label is set.
	 *     @type string $label       Accessible label.
	 *     @type string $source      URL for remote search (include a nonce).
	 *     @type bool   $disabled
	 * }
	 * @return string Escaped HTML.
	 */
	public static function combobox( $name, array $options, $selected = array(), array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'          => '',
				'multiple'    => true,
				'placeholder' => '',
				'label'       => '',
				'source'      => '',
				'disabled'    => false,
			)
		);

		if ( $args['multiple'] && '[]' !== substr( $name, -2 ) ) {
			$name .= '[]';
		}

		$selected = array_map( 'strval', (array) $selected );

		$html = '<select data-wpb-combobox name="' . esc_attr( $name ) . '"';
		$html .= $args['multiple'] ? ' multiple' : '';
		$html .= '' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '';
		$html .= '' !== $args['placeholder'] ? ' data-wpb-combobox-placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '';
		$html .= '' !== $args['label'] ? ' aria-label="' . esc_attr( $args['label'] ) . '"' : '';
		$html .= '' !== $args['source'] ? ' data-wpb-combobox-source="' . esc_url( $args['source'] ) . '"' : '';
		$html .= disabled( $args['disabled'], true, false ) . '>';

		if ( ! $args['multiple'] ) {
			$html .= '<option value=""></option>';
		}
		foreach ( $options as $value => $label ) {
			$html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				in_array( (string) $value, $selected, true ) ? ' selected' : '',
				esc_html( $label )
			);
		}

		return $html . '</select>';
	}

	/**
	 * Media library picker. Stores the attachment ID in a hidden input.
	 *
	 * Loads the media library on its own. Not for use inside an open .wpb-modal:
	 * a modal dialog stays above the media library.
	 *
	 * @param string $name          Input name.
	 * @param int    $attachment_id Current attachment ID, 0 for none.
	 * @param array  $args {
	 *     @type string $type         Limit the library, e.g. 'image'. Default: all types.
	 *     @type string $title        Title of the media dialog.
	 *     @type string $button       Confirm button of the media dialog.
	 *     @type string $select_label Label of the select button.
	 *     @type string $remove_label Label of the remove button.
	 *     @type string $id           ID of the hidden input.
	 * }
	 * @return string Escaped HTML.
	 */
	public static function media( $name, $attachment_id = 0, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'type'         => '',
				'title'        => __( 'Select a file', 'wp-backend-ui' ),
				'button'       => __( 'Use this file', 'wp-backend-ui' ),
				'select_label' => __( 'Select', 'wp-backend-ui' ),
				'remove_label' => __( 'Remove', 'wp-backend-ui' ),
				'id'           => '',
			)
		);

		wp_enqueue_media();

		$attachment_id = absint( $attachment_id );
		$image         = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : false;
		$file          = $attachment_id ? get_attached_file( $attachment_id ) : false;
		$has_file      = $attachment_id && ( $image || $file );

		return sprintf(
			'<div class="wpb-media%1$s" data-wpb-media-type="%2$s" data-wpb-media-title="%3$s" data-wpb-media-button="%4$s">'
			. '<input type="hidden" class="wpb-media__value" name="%5$s" value="%6$s"%7$s />'
			. '<div class="wpb-media__preview"%8$s><img src="%9$s" alt=""%10$s /><span class="wpb-media__name">%11$s</span></div>'
			. '<div class="wpb-media__actions"><button type="button" class="button" data-wpb-media-select>%12$s</button>'
			. '<button type="button" class="button wpb-button-danger" data-wpb-media-remove>%13$s</button></div></div>',
			$has_file ? '' : ' is-empty',
			esc_attr( $args['type'] ),
			esc_attr( $args['title'] ),
			esc_attr( $args['button'] ),
			esc_attr( $name ),
			esc_attr( $has_file ? (string) $attachment_id : '' ),
			'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '',
			$has_file ? '' : ' hidden',
			esc_url( $image ? $image : '' ),
			$image ? '' : ' hidden',
			esc_html( $file ? wp_basename( $file ) : '' ),
			esc_html( $args['select_label'] ),
			esc_html( $args['remove_label'] )
		);
	}

	/**
	 * Render a repeater: rows that users add, remove and optionally sort.
	 *
	 * The callback prints the fields of one row. Use $index in input names, e.g.
	 * name="answers[<?php echo esc_attr( $index ); ?>][title]". For the template
	 * row, $row is an empty array and $index is the placeholder '__index__'.
	 *
	 * @param array $args {
	 *     @type array    $rows      Existing rows.
	 *     @type callable $row       Prints one row: function( array $row, $index ).
	 *     @type string   $add_label Label of the add button.
	 *     @type bool     $sortable  Allow reordering. Default true.
	 *     @type string   $group     Sortable group, to move rows between repeaters.
	 *     @type int      $min       Rows that cannot be removed. Default 0.
	 *     @type int      $max       Maximum number of rows, 0 for no limit.
	 *     @type string   $id        Element ID.
	 * }
	 * @return void
	 */
	public static function repeater( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'rows'      => array(),
				'row'       => null,
				'add_label' => __( 'Add row', 'wp-backend-ui' ),
				'sortable'  => true,
				'group'     => '',
				'min'       => 0,
				'max'       => 0,
				'id'        => '',
			)
		);

		if ( ! is_callable( $args['row'] ) ) {
			return;
		}

		$rows = array_values( (array) $args['rows'] );

		printf(
			'<div class="wpb-repeater" data-wpb-repeater data-wpb-repeater-next="%1$d" data-wpb-repeater-min="%2$d" data-wpb-repeater-max="%3$d"%4$s>',
			count( $rows ),
			absint( $args['min'] ),
			absint( $args['max'] ),
			'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''
		);

		echo '<template>';
		self::repeater_row( $args, array(), '__index__' );
		echo '</template>';

		echo '<div class="wpb-repeater__items"' . ( $args['sortable'] ? ' data-wpb-sortable="' . esc_attr( $args['group'] ) . '"' : '' ) . '>';
		foreach ( $rows as $index => $row ) {
			self::repeater_row( $args, (array) $row, $index );
		}
		echo '</div>';

		echo '<div class="wpb-repeater__footer"><button type="button" class="button" data-wpb-repeater-add>' . esc_html( $args['add_label'] ) . '</button></div>';
		echo '</div>';
	}

	/**
	 * Print one repeater row.
	 *
	 * @param array      $args  Arguments of repeater().
	 * @param array      $row   Row data.
	 * @param int|string $index Row index or the template placeholder.
	 * @return void
	 */
	private static function repeater_row( array $args, array $row, $index ) {
		echo '<div class="wpb-repeater__item' . ( $args['sortable'] ? ' wpb-sortable__item' : '' ) . '">';
		if ( $args['sortable'] ) {
			echo self::sortable_handle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in sortable_handle().
		}
		echo '<div class="wpb-repeater__fields">';
		call_user_func( $args['row'], $row, $index );
		echo '</div>';
		printf(
			'<button type="button" class="wpb-repeater__remove" data-wpb-repeater-remove aria-label="%s"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>',
			esc_attr__( 'Remove row', 'wp-backend-ui' )
		);
		echo '</div>';
	}

	/**
	 * Current admin page URL without volatile arguments.
	 *
	 * @return string
	 */
	private static function current_page_url() {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$base = admin_url( $pagenow ? $pagenow : 'admin.php' );

		return '' !== $page ? add_query_arg( 'page', $page, $base ) : $base;
	}
}
