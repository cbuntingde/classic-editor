<?php
/**
 * Classic Editor
 *
 * Plugin Name: Classic Editor
 * Plugin URI:  https://wordpress.org/plugins/classic-editor/
 * Description: Enables the WordPress classic editor and the old-style Edit Post screen with TinyMCE, Meta Boxes, etc. Supports the older plugins that extend this screen.
 * Version:     1.7.0
 * Author:      WordPress Contributors
 * Author URI:  https://github.com/WordPress/classic-editor/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: classic-editor
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.2
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

if ( ! class_exists( Classic_Editor::class ) ) :
final class Classic_Editor {
	/** @var array<string, mixed> */
	private static array $settings = [];
	/** @var array<string, array{classic_editor: bool, block_editor: bool}> */
	private static array $supported_post_types = [];
	/** @var array<string, int> */
	private static array $user_last_editor = [];

	public function __construct() {
		// Static class only.
}

	public static function init_actions(): void {
		$block_editor = has_action( 'enqueue_block_assets' );
		$is_gutenberg = function_exists( 'gutenberg_register_scripts_and_styles' );

		register_activation_hook( __FILE__, [ self::class, 'activate' ] );

		self::$settings = self::get_settings();

		if ( is_multisite() ) {
			add_action( 'wpmu_options', [ self::class, 'network_settings' ] );
			add_action( 'update_wpmu_options', [ self::class, 'save_network_settings' ] );
		}

		if ( ! self::$settings['hide-settings-ui'] ) {
			add_filter( 'plugin_action_links', [ self::class, 'add_settings_link' ], 10, 2 );
			add_filter( 'network_admin_plugin_action_links', [ self::class, 'add_settings_link' ], 10, 2 );
			add_action( 'admin_init', [ self::class, 'register_settings' ] );

			if ( self::$settings['allow-users'] ) {
				add_action( 'personal_options_update', [ self::class, 'save_user_settings' ] );
				add_action( 'edit_user_profile_update', [ self::class, 'save_user_settings' ] );
				add_action( 'profile_personal_options', [ self::class, 'user_settings' ] );
				add_action( 'edit_user_profile', [ self::class, 'user_settings' ] );
			}
		}

		remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );
		add_action( 'admin_print_styles', [ self::class, 'safari_fix' ] );

		global $wp_version;

		if ( version_compare( $wp_version, '6.7.1', '>=' ) && version_compare( $wp_version, '6.8', '<' ) && is_admin() ) {
			add_filter( 'script_loader_src', [ self::class, 'fix_post_js' ], 11, 2 );
		}

		if ( ! $block_editor && ! $is_gutenberg ) {
			return;
		}

		if ( self::$settings['allow-users'] ) {
			add_filter( 'use_block_editor_for_post', [ self::class, 'choose_editor' ], 100, 2 );

			if ( $is_gutenberg ) {
				add_filter( 'gutenberg_can_edit_post', [ self::class, 'choose_editor' ], 100, 2 );

				if ( self::$settings['editor'] === 'classic' ) {
					self::remove_gutenberg_hooks( 'some' );
				}
			}

			add_filter( 'get_edit_post_link', [ self::class, 'get_edit_post_link' ] );
			add_filter( 'redirect_post_location', [ self::class, 'redirect_location' ] );
			add_action( 'edit_form_top', [ self::class, 'add_redirect_helper' ] );
			add_action( 'admin_head-edit.php', [ self::class, 'add_edit_php_inline_style' ] );
			add_action( 'edit_form_top', [ self::class, 'remember_classic_editor' ] );

			if ( version_compare( $wp_version, '5.8', '>=' ) ) {
				add_filter( 'block_editor_settings_all', [ self::class, 'remember_block_editor' ], 10, 2 );
			} else {
				add_filter( 'block_editor_settings', [ self::class, 'remember_block_editor' ], 10, 2 );
			}

			add_filter( 'display_post_states', [ self::class, 'add_post_state' ], 10, 2 );
			add_filter( 'page_row_actions', [ self::class, 'add_edit_links' ], 15, 2 );
			add_filter( 'post_row_actions', [ self::class, 'add_edit_links' ], 15, 2 );

			add_action( 'add_meta_boxes', [ self::class, 'add_meta_box' ], 10, 2 );
			add_action( 'add_meta_boxes', [ self::class, 'add_meta_box_bidirectional' ], 10, 2 );
			add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_block_editor_scripts' ] );

			add_action( 'wp_ajax_classic-editor-switch', [ self::class, 'ajax_switch_editor' ] );

			add_action( 'rest_api_init', [ self::class, 'register_rest_api' ] );

			add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend_scripts' ] );
			add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_scripts' ] );
		} else {
			if ( self::$settings['editor'] === 'classic' ) {
				add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );

				if ( $is_gutenberg ) {
					add_filter( 'gutenberg_can_edit_post_type', '__return_false', 100 );
					self::remove_gutenberg_hooks();
				}
			}
		}

		if ( $block_editor ) {
			add_action( 'admin_init', [ self::class, 'on_admin_init' ] );
		}

		if ( $is_gutenberg ) {
			remove_action( 'admin_menu', 'gutenberg_menu' );
			remove_action( 'admin_init', 'gutenberg_redirect_demo' );
			remove_action( 'wp_enqueue_scripts', 'gutenberg_register_scripts_and_styles' );
			remove_action( 'admin_enqueue_scripts', 'gutenberg_register_scripts_and_styles' );
			remove_action( 'admin_notices', 'gutenberg_wordpress_version_notice' );
		}
	}

	public static function register_rest_api(): void {
		register_rest_route(
			'classic-editor/v1',
			'/preferences',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ self::class, 'rest_update_preferences' ],
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'args'               => array(
					'editor'       => array(
						'type'              => 'string',
						'enum'              => array( 'classic', 'block' ),
						'validate_callback' => 'rest_validate_request_arg',
					),
					'remember_per_post' => array(
						'type'        => 'boolean',
						'description' => 'Remember editor choice per post',
					),
				),
			)
		);

		register_rest_route(
			'classic-editor/v1',
			'/preferences',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'rest_get_preferences' ],
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
	}

	public static function rest_get_preferences( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$preferences = get_user_meta( $user_id, 'classic-editor-preferences', true );

		if ( ! is_array( $preferences ) ) {
			$preferences = array(
				'editor'             => self::$settings['editor'] ?? 'classic',
				'remember_per_post'  => (bool) get_user_option( 'classic-editor-remember-per-post', $user_id ),
			);
		}

		return new WP_REST_Response( $preferences, 200 );
	}

	public static function rest_update_preferences( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$params = $request->get_json_params() ?? array();

		$preferences = array(
			'editor'            => $params['editor'] ?? self::$settings['editor'] ?? 'classic',
			'remember_per_post' => $params['remember_per_post'] ?? false,
		);

		update_user_meta( $user_id, 'classic-editor-preferences', $preferences );
		update_user_meta( $user_id, 'classic-editor-remember-per-post', (bool) $preferences['remember_per_post'] );

		return new WP_REST_Response( $preferences, 200 );
	}

	public static function enqueue_frontend_scripts(): void {
		if ( ! is_singular() ) {
			return;
		}

		$settings = self::get_settings();

		if ( $settings['editor'] !== 'block' && ! self::is_classic( (int) get_queried_object_id() ) ) {
			return;
		}

		wp_register_script(
			'classic-editor-switcher',
			plugins_url( 'js/switcher.js', __FILE__ ),
			array(),
			'1.7.0',
			array( 'in_footer' => true )
		);

		wp_localize_script(
			'classic-editor-switcher',
			'classicEditorData',
			array(
				'homeUrl'    => get_rest_url( 'classic-editor/v1' ),
				'postId'     => get_queried_object_id(),
				'_wpnonce'   => wp_create_nonce( 'wp_rest' ),
				'labels'     => array(
					'switchToBlock' => __( 'Edit in Block Editor', 'classic-editor' ),
					'switchToClassic' => __( 'Edit in Classic Editor', 'classic-editor' ),
				),
			)
		);

		wp_enqueue_script( 'classic-editor-switcher' );
	}

	public static function enqueue_admin_scripts( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php', 'options-writing.php', 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}

		wp_register_style(
			'classic-editor-admin',
			plugins_url( 'css/editor.css', __FILE__ ),
			array(),
			'1.7.0'
		);
		wp_enqueue_style( 'classic-editor-admin' );

		wp_register_script(
			'classic-editor-admin',
			plugins_url( 'js/admin.js', __FILE__ ),
			array( 'jquery' ),
			'1.7.0',
			array( 'in_footer' => true )
		);

		wp_localize_script(
			'classic-editor-admin',
			'classicEditorData',
			array(
				'homeUrl'          => get_rest_url( 'classic-editor/v1' ),
				'_wpnonce'         => wp_create_nonce( 'wp_rest' ),
				'allowUsers'       => self::$settings['allow-users'],
				'shortcutHint'     => __( 'Press Alt+Shift+E to switch editors', 'classic-editor' ),
				'labels'           => array(
					'classic'  => __( 'Classic Editor', 'classic-editor' ),
					'block'   => __( 'Block Editor', 'classic-editor' ),
					'switchToBlock' => __( 'Switch to block editor', 'classic-editor' ),
					'switchToClassic' => __( 'Switch to classic editor', 'classic-editor' ),
					'switchToBlockAria' => __( 'Switch to the block editor', 'classic-editor' ),
					'switchToClassicAria' => __( 'Switch to the classic editor', 'classic-editor' ),
				),
			)
		);

		wp_enqueue_script( 'classic-editor-admin' );
	}

	#[ReturnTypeWillChange]
	public static function remove_gutenberg_hooks( string $remove = 'all' ): void {
		remove_action( 'admin_menu', 'gutenberg_menu' );
		remove_action( 'admin_init', 'gutenberg_redirect_demo' );

		if ( $remove !== 'all' ) {
			return;
		}

		remove_action( 'wp_enqueue_scripts', 'gutenberg_register_scripts_and_styles' );
		remove_action( 'admin_enqueue_scripts', 'gutenberg_register_scripts_and_styles' );
		remove_action( 'admin_notices', 'gutenberg_wordpress_version_notice' );
		remove_action( 'rest_api_init', 'gutenberg_register_rest_widget_updater_routes' );
		remove_action( 'admin_print_styles', 'gutenberg_block_editor_admin_print_styles' );
		remove_action( 'admin_print_scripts', 'gutenberg_block_editor_admin_print_scripts' );
		remove_action( 'admin_print_footer_scripts', 'gutenberg_block_editor_admin_print_footer_scripts' );
		remove_action( 'admin_footer', 'gutenberg_block_editor_admin_footer' );
		remove_action( 'admin_enqueue_scripts', 'gutenberg_widgets_init' );
		remove_action( 'admin_notices', 'gutenberg_build_files_notice' );

		remove_filter( 'load_script_translation_file', 'gutenberg_override_translation_file' );
		remove_filter( 'block_editor_settings', 'gutenberg_extend_block_editor_styles' );
		remove_filter( 'default_content', 'gutenberg_default_demo_content' );
		remove_filter( 'default_title', 'gutenberg_default_demo_title' );
		remove_filter( 'block_editor_settings', 'gutenberg_legacy_widget_settings' );
		remove_filter( 'rest_request_after_callbacks', 'gutenberg_filter_oembed_result' );
		remove_filter( 'wp_refresh_nonces', 'gutenberg_add_rest_nonce_to_heartbeat_response_headers' );
		remove_filter( 'get_edit_post_link', 'gutenberg_revisions_link_to_editor' );
		remove_filter( 'wp_prepare_revision_for_js', 'gutenberg_revisions_restore' );
		remove_filter( 'body_class', 'gutenberg_add_responsive_body_class' );
		remove_filter( 'admin_url', 'gutenberg_modify_add_new_button_url' );
		remove_filter( 'register_post_type_args', 'gutenberg_filter_post_type_labels' );
	}

	/**
	 * @param string $refresh
	 * @param int $user_id
	 * @return array<string, mixed>
	 */
	private static function get_settings( string $refresh = 'no', int $user_id = 0 ): array {
		$settings = apply_filters( 'classic_editor_plugin_settings', false );

		if ( is_array( $settings ) ) {
			return array(
				'editor'         => isset( $settings['editor'] ) && $settings['editor'] === 'block' ? 'block' : 'classic',
				'allow-users'   => ! empty( $settings['allow-users'] ),
				'hide-settings-ui' => true,
			);
		}

		if ( ! empty( self::$settings ) && $refresh === 'no' ) {
			return self::$settings;
		}

		if ( is_multisite() ) {
			$defaults = array(
				'editor'       => get_network_option( null, 'classic-editor-replace' ) === 'block' ? 'block' : 'classic',
				'allow-users' => false,
			);

			$defaults = apply_filters( 'classic_editor_network_default_settings', $defaults );

			if ( get_network_option( null, 'classic-editor-allow-sites' ) !== 'allow' ) {
				$defaults['hide-settings-ui'] = true;
				return $defaults;
			}

			$editor_option        = get_option( 'classic-editor-replace' );
			$allow_users_option = get_option( 'classic-editor-allow-users' );

			if ( $editor_option ) {
				$defaults['editor'] = $editor_option;
			}
			if ( $allow_users_option ) {
				$defaults['allow-users'] = $allow_users_option === 'allow';
			}

			$editor      = $defaults['editor'] === 'block' ? 'block' : 'classic';
			$allow_users = ! empty( $defaults['allow-users'] );
		} else {
			$allow_users = get_option( 'classic-editor-allow-users' ) === 'allow';
			$option     = get_option( 'classic-editor-replace' );

			if ( $option === 'block' || $option === 'no-replace' ) {
				$editor = 'block';
			} else {
				$editor = 'classic';
			}
		}

		if ( ( ! isset( $GLOBALS['pagenow'] ) || $GLOBALS['pagenow'] !== 'options-writing.php' ) && $allow_users ) {
			$user_options = get_user_option( 'classic-editor-settings', $user_id );

			if ( in_array( $user_options, array( 'block', 'classic' ), true ) ) {
				$editor = $user_options;
			}
		}

		self::$settings = array(
			'editor'          => $editor,
			'hide-settings-ui' => false,
			'allow-users'    => $allow_users,
		);

		return self::$settings;
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	private static function is_classic( int $post_id = 0 ): bool {
		if ( ! $post_id ) {
			$post_id = self::get_edited_post_id();
		}

		if ( $post_id ) {
			$settings = self::get_settings();

			if ( $settings['allow-users'] && ! isset( $_GET['classic-editor__forget'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$user_preferences = get_user_meta( get_current_user_id(), 'classic-editor-preferences', true );

				if ( ! empty( $user_preferences['remember_per_post'] ) ) {
					$which = get_post_meta( $post_id, 'classic-editor-remember', true );

					if ( $which ) {
						return $which === 'classic-editor';
					}
				}

				return ! self::has_blocks( $post_id );
			}
		}

		if ( isset( $_GET['classic-editor'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * @return int
	 */
	private static function get_edited_post_id(): int {
		if (
			! empty( $_GET['post'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! empty( $_GET['action'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_GET['action'] === 'edit' && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! empty( $GLOBALS['pagenow'] ) &&
			$GLOBALS['pagenow'] === 'post.php'
		) {
			return absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return 0;
	}

	public static function register_settings(): void {
		register_setting(
			'writing',
			'classic-editor-replace',
			array(
				'sanitize_callback' => array( self::class, 'validate_option_editor' ),
				'type'           => 'string',
				'show_in_rest'   => true,
			)
		);

		register_setting(
			'writing',
			'classic-editor-allow-users',
			array(
				'sanitize_callback' => array( self::class, 'validate_option_allow_users' ),
				'type'              => 'string',
				'show_in_rest'      => true,
			)
		);

		$allowed_options = array(
			'writing' => array(
				'classic-editor-replace',
				'classic-editor-allow-users',
			),
		);

		if ( function_exists( 'add_allowed_options' ) ) {
			add_allowed_options( $allowed_options );
		} else {
			add_option_whitelist( $allowed_options );
		}

		$heading_1 = __( 'Default editor for all users', 'classic-editor' );
		$heading_2 = __( 'Allow users to switch editors', 'classic-editor' );

		add_settings_field( 'classic-editor-1', $heading_1, array( self::class, 'settings_1' ), 'writing' );
		add_settings_field( 'classic-editor-2', $heading_2, array( self::class, 'settings_2' ), 'writing' );
	}

	/**
	 * @param int $user_id
	 */
	public static function save_user_settings( int $user_id ): void {
		if (
			isset( $_POST['classic-editor-user-settings'] )
			&& isset( $_POST['classic-editor-replace'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classic-editor-user-settings'] ) ), 'allow-user-settings' )
		) {
			$user_id = (int) $user_id;

			if ( $user_id !== get_current_user_id() && ! current_user_can( 'edit_user', $user_id ) ) {
				return;
			}

			$editor = self::validate_option_editor( sanitize_text_field( wp_unslash( $_POST['classic-editor-replace'] ) ) );
			update_user_option( $user_id, 'classic-editor-settings', $editor );
		}
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function validate_option_editor( mixed $value ): string {
		if ( $value === 'block' ) {
			return 'block';
		}

		return 'classic';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function validate_option_allow_users( mixed $value ): string {
		if ( $value === 'allow' ) {
			return 'allow';
		}

		return 'disallow';
	}

	/**
	 * Settings field callback for default editor. Handles both WordPress settings API calls
	 * (which pass the option value as first arg) and direct calls with a user_id.
	 *
	 * @param mixed $value Option value (from WP settings API) or user ID (when called directly)
	 */
	public static function settings_1( mixed $value = 0 ): void {
		// If value is an array, WordPress is calling this as a settings field callback
		// In that case, use 0 for global settings (no user-specific)
		$user_id = is_array( $value ) ? 0 : (int) $value;
		$settings = self::get_settings( 'refresh', $user_id );

		?>
		<div class="classic-editor-options">
			<p>
				<input type="radio" name="classic-editor-replace" id="classic-editor-classic" value="classic" <?php checked( $settings['editor'], 'classic' ); ?> />
				<label for="classic-editor-classic"><?php echo esc_html_x( 'Classic editor', 'Editor Name', 'classic-editor' ); ?></label>
			</p>
			<p>
				<input type="radio" name="classic-editor-replace" id="classic-editor-block" value="block" <?php checked( $settings['editor'], 'block' ); ?> />
				<label for="classic-editor-block"><?php echo esc_html_x( 'Block editor', 'Editor Name', 'classic-editor' ); ?></label>
			</p>
		</div>
		<script>
		jQuery( 'document' ).ready( function( $ ) {
			if ( window.location.hash === '#classic-editor-options' ) {
				$( '.classic-editor-options' ).closest( 'td' ).addClass( 'highlight' );
			}
		} );
		</script>
		<?php
	}

	public static function settings_2(): void {
		$settings = self::get_settings( 'refresh' );

		?>
		<div class="classic-editor-options">
			<p>
				<input type="radio" name="classic-editor-allow-users" id="classic-editor-allow" value="allow" <?php checked( $settings['allow-users'], true ); ?> />
				<label for="classic-editor-allow"><?php esc_html_e( 'Yes', 'classic-editor' ); ?></label>
			</p>
			<p>
				<input type="radio" name="classic-editor-allow-users" id="classic-editor-disallow" value="disallow" <?php checked( $settings['allow-users'], false ); ?> />
				<label for="classic-editor-disallow"><?php esc_html_e( 'No', 'classic-editor' ); ?></label>
			</p>
		</div>
		<?php
	}

	public static function user_settings( WP_User|null $user = null ): void {
		$settings = self::get_settings( 'update' );

		if ( ! current_user_can( 'edit_posts' ) || ! $settings['allow-users'] ) {
			return;
		}

		$user_id = $user instanceof WP_User ? (int) $user->ID : 0;

		?>
		<table class="form-table">
			<tr class="classic-editor-user-options">
				<th scope="row"><?php esc_html_e( 'Default Editor', 'classic-editor' ); ?></th>
				<td>
				<?php wp_nonce_field( 'allow-user-settings', 'classic-editor-user-settings' ); ?>
				<?php self::settings_1( $user_id ); ?>
				</td>
			</tr>
		</table>
		<script>jQuery( 'tr.user-rich-editing-wrap' ).before( jQuery( 'tr.classic-editor-user-options' ) );</script>
		<?php
	}

	public static function network_settings(): void {
		$editor     = get_network_option( null, 'classic-editor-replace' );
		$is_checked = get_network_option( null, 'classic-editor-allow-sites' ) === 'allow';

		?>
		<h2 id="classic-editor-options"><?php esc_html_e( 'Editor Settings', 'classic-editor' ); ?></h2>
		<table class="form-table">
			<?php wp_nonce_field( 'allow-site-admin-settings', 'classic-editor-network-settings' ); ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Default editor for all sites', 'classic-editor' ); ?></th>
				<td>
					<p>
						<input type="radio" name="classic-editor-replace" id="classic-editor-classic" value="classic" <?php checked( $editor, 'classic', true ); ?> />
						<label for="classic-editor-classic"><?php echo esc_html_x( 'Classic Editor', 'Editor Name', 'classic-editor' ); ?></label>
					</p>
					<p>
						<input type="radio" name="classic-editor-replace" id="classic-editor-block" value="block" <?php checked( $editor, 'block', true ); ?> />
						<label for="classic-editor-block"><?php echo esc_html_x( 'Block editor', 'Editor Name', 'classic-editor' ); ?></label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Change settings', 'classic-editor' ); ?></th>
				<td>
					<input type="checkbox" name="classic-editor-allow-sites" id="classic-editor-allow-sites" value="allow" <?php checked( $is_checked, true ); ?> />
					<label for="classic-editor-allow-sites"><?php esc_html_e( 'Allow site admins to change settings', 'classic-editor' ); ?></label>
					<p class="description"><?php esc_html_e( 'By default the block editor is replaced with the classic editor and users cannot switch editors.', 'classic-editor' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_network_settings(): void {
		if (
			isset( $_POST['classic-editor-network-settings'] )
			&& current_user_can( 'manage_network_options' )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classic-editor-network-settings'] ) ), 'allow-site-admin-settings' )
		) {
			if ( isset( $_POST['classic-editor-replace'] ) && $_POST['classic-editor-replace'] === 'block' ) {
				update_network_option( null, 'classic-editor-replace', 'block' );
			} else {
				update_network_option( null, 'classic-editor-replace', 'classic' );
			}
			if ( isset( $_POST['classic-editor-allow-sites'] ) && $_POST['classic-editor-allow-sites'] === 'allow' ) {
				update_network_option( null, 'classic-editor-allow-sites', 'allow' );
			} else {
				update_network_option( null, 'classic-editor-allow-sites', 'disallow' );
			}
		}
	}

	public static function add_redirect_helper(): void {
		?>
		<input type="hidden" name="classic-editor" value="" />
		<?php
	}

	/**
	 * @param WP_Post $post
	 */
	public static function remember_classic_editor( WP_Post $post ): void {
		$post_type = get_post_type( $post );

		if ( $post_type && post_type_supports( $post_type, 'editor' ) ) {
			self::remember( $post->ID, 'classic-editor' );
		}
	}

	/**
	 * @param array $editor_settings
	 * @param mixed $context
	 * @return array
	 */
	public static function remember_block_editor( array $editor_settings, mixed $context ): array {
		$post = null;

		if ( $context instanceof WP_Post ) {
			$post = $context;
		} elseif ( is_object( $context ) && isset( $context->post ) && $context->post instanceof WP_Post ) {
			$post = $context->post;
		} else {
			return $editor_settings;
		}

		$post_type = get_post_type( $post );

		if ( $post_type && self::can_edit_post_type( $post_type ) ) {
			self::remember( $post->ID, 'block-editor' );
		}

		return $editor_settings;
	}

	/**
	 * @param int $post_id
	 * @param string $editor
	 */
	private static function remember( int $post_id, string $editor ): void {
		$user_preferences = get_user_meta( get_current_user_id(), 'classic-editor-preferences', true );

		if ( empty( $user_preferences['remember_per_post'] ) ) {
			return;
		}

		if ( get_post_meta( $post_id, 'classic-editor-remember', true ) !== $editor ) {
			update_post_meta( $post_id, 'classic-editor-remember', $editor );
		}
	}

	/**
	 * @param bool $use_block_editor
	 * @param WP_Post $post
	 * @return bool
	 */
	public static function choose_editor( bool $use_block_editor, WP_Post $post ): bool {
		$settings = self::get_settings();
		$editors  = self::get_enabled_editors_for_post( $post );

		if ( ! $editors['block_editor'] && ! $editors['classic_editor'] ) {
			return $use_block_editor;
		}

		if ( empty( $post->ID ) || $post->post_status === 'auto-draft' ) {
			if (
				( $settings['editor'] === 'classic' && ! isset( $_GET['classic-editor__forget'] ) )
				|| ( isset( $_GET['classic-editor'] ) && isset( $_GET['classic-editor__forget'] ) )
			) {
				$use_block_editor = false;
			}
		} elseif ( self::is_classic( (int) $post->ID ) ) {
			$use_block_editor = false;
		}

		if ( $use_block_editor && ! $editors['block_editor'] ) {
			$use_block_editor = false;
		} elseif ( ! $use_block_editor && ! $editors['classic_editor'] && $editors['block_editor'] ) {
			$use_block_editor = true;
		}

		return $use_block_editor;
	}

	/**
	 * @param string $location
	 * @return string
	 */
	public static function redirect_location( string $location ): string {
		if (
			isset( $_REQUEST['classic-editor'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ( isset( $_POST['_wp_http_referer'] ) && str_contains( sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) ), '&classic-editor' ) )
		) {
			$location = add_query_arg( 'classic-editor', '', $location );
		}

		return $location;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public static function get_edit_post_link( string $url ): string {
		$settings = self::get_settings();

		if ( isset( $_REQUEST['classic-editor'] ) || $settings['editor'] === 'classic' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url = add_query_arg( 'classic-editor', '', $url );
		}

		return $url;
	}

	/**
	 * @param string $post_type
	 * @param WP_Post $post
	 */
	public static function add_meta_box( string $post_type, WP_Post $post ): void {
		$editors = self::get_enabled_editors_for_post( $post );

		if ( ! $editors['block_editor'] || ! $editors['classic_editor'] ) {
			return;
		}

		add_meta_box(
			'classic-editor-switch-editor',
			__( 'Editor', 'classic-editor' ),
			array( self::class, 'do_meta_box' ),
			null,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}

	/**
	 * @param WP_Post $post
	 */
	public static function do_meta_box( WP_Post $post ): void {
		$edit_url = get_edit_post_link( $post->ID, 'raw' );
		$edit_url_classic = add_query_arg( 'classic-editor', '', $edit_url );
		$edit_url_block = remove_query_arg( 'classic-editor', $edit_url );
		$edit_url_block = add_query_arg( 'classic-editor__forget', '', $edit_url_block );

		?>
		<div class="switch-editor-link">
			<a href="<?php echo esc_url( $edit_url_block ); ?>" class="button">
				<span aria-hidden="true">&#8594;</span>
				<?php esc_html_e( 'Switch to block editor', 'classic-editor' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Enhanced meta box with both directions.
	 *
	 * @param string $post_type
	 * @param WP_Post $post
	 */
	public static function add_meta_box_bidirectional( string $post_type, WP_Post $post ): void {
		$editors = self::get_enabled_editors_for_post( $post );

		if ( ! $editors['block_editor'] || ! $editors['classic_editor'] ) {
			return;
		}

		add_meta_box(
			'classic-editor-switch-editor',
			__( 'Editor', 'classic-editor' ),
			array( self::class, 'do_meta_box_bidirectional' ),
			null,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}

	/**
	 * @param WP_Post $post
	 */
	public static function do_meta_box_bidirectional( WP_Post $post ): void {
		$edit_url = get_edit_post_link( $post->ID, 'raw' );
		$is_classic = self::is_classic( (int) $post->ID );

		?>
		<div class="switch-editor-link" style="margin: 10px 0;">
			<?php if ( $is_classic ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( 'classic-editor', $edit_url ) . '&classic-editor__forget=1' ); ?>" class="button button-secondary" style="display: block; text-align: center;">
					<span aria-hidden="true">&#8594;</span>
					<?php esc_html_e( 'Switch to block editor', 'classic-editor' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( add_query_arg( 'classic-editor', '', $edit_url ) ); ?>" class="button button-secondary" style="display: block; text-align: center;">
					<span aria-hidden="true">&#8594;</span>
					<?php esc_html_e( 'Switch to classic editor', 'classic-editor' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add AJAX handler for inline editor switching.
	 */
	public static function ajax_switch_editor(): void {
		check_ajax_referer( 'classic-editor-switch', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$editor = isset( $_POST['editor'] ) ? sanitize_text_field( wp_unslash( $_POST['editor'] ) ) : 'classic';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID' ), 400 );
		}

		if ( ! in_array( $editor, array( 'classic', 'block' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid editor' ), 400 );
		}

		$meta_key = 'classic-editor-remember';
		$value = 'classic' === $editor ? 'classic-editor' : 'block-editor';

		update_post_meta( $post_id, $meta_key, $value );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %s: editor name */
				__( 'Editor preference saved: %s', 'classic-editor' ),
				'classic' === $editor ? __( 'Classic Editor', 'classic-editor' ) : __( 'Block Editor', 'classic-editor' )
			),
		) );
	}

	public static function enqueue_block_editor_scripts(): void {
		if ( empty( $GLOBALS['post'] ) ) {
			return;
		}

		$editors = self::get_enabled_editors_for_post( $GLOBALS['post'] );

		if ( ! $editors['classic_editor'] ) {
			return;
		}

		wp_enqueue_script(
			'classic-editor-plugin',
			plugins_url( 'js/block-editor-plugin.js', __FILE__ ),
			array( 'wp-element', 'wp-components', 'lodash' ),
			'1.7.0',
			true
		);

		wp_localize_script(
			'classic-editor-plugin',
			'classicEditorPluginL10n',
			array( 'linkText' => __( 'Switch to classic editor', 'classic-editor' ) )
		);
	}

	/**
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public static function add_settings_link( array $links, string $file ): array {
		$settings = self::get_settings();

		if ( $file === 'classic-editor/classic-editor.php' && ! $settings['hide-settings-ui'] && current_user_can( 'manage_options' ) ) {
			if ( current_filter() === 'plugin_action_links' ) {
				$url = admin_url( 'options-writing.php#classic-editor-options' );
			} else {
				$url = admin_url( '/network/settings.php#classic-editor-options' );
			}

			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'classic-editor' )
			);
		}

		return $links;
	}

	/**
	 * @param string $post_type
	 * @return bool
	 */
	private static function can_edit_post_type( string $post_type ): bool {
		if ( function_exists( 'gutenberg_can_edit_post_type' ) ) {
			return (bool) gutenberg_can_edit_post_type( $post_type );
		}

		if ( function_exists( 'use_block_editor_for_post_type' ) ) {
			return (bool) use_block_editor_for_post_type( $post_type );
		}

		return false;
	}

	/**
	 * @param string $post_type
	 * @return array{classic_editor: bool, block_editor: bool}
	 */
	private static function get_enabled_editors_for_post_type( string $post_type ): array {
		if ( isset( self::$supported_post_types[ $post_type ] ) ) {
			return self::$supported_post_types[ $post_type ];
		}

		$classic_editor = post_type_supports( $post_type, 'editor' );
		$block_editor   = self::can_edit_post_type( $post_type );

		$editors = array(
			'classic_editor' => $classic_editor,
			'block_editor'  => $block_editor,
		);

		$editors = apply_filters( 'classic_editor_enabled_editors_for_post_type', $editors, $post_type );
		self::$supported_post_types[ $post_type ] = $editors;

		return $editors;
	}

	/**
	 * @param WP_Post|int $post
	 * @return array{classic_editor: bool, block_editor: bool}
	 */
	private static function get_enabled_editors_for_post( $post ): array {
		$post = get_post( $post );
		$post_type = $post instanceof WP_Post ? get_post_type( $post ) : null;

		if ( ! $post_type ) {
			return array(
				'classic_editor' => false,
				'block_editor'  => false,
			);
		}

		$editors = self::get_enabled_editors_for_post_type( $post_type );

		return apply_filters( 'classic_editor_enabled_editors_for_post', $editors, $post );
	}

	/**
	 * @param array $actions
	 * @param WP_Post $post
	 * @return array
	 */
	public static function add_edit_links( array $actions, WP_Post $post ): array {
		if ( array_key_exists( 'classic', $actions ) ) {
			unset( $actions['classic'] );
		}

		if ( ! array_key_exists( 'edit', $actions ) ) {
			return $actions;
		}

		$edit_url = get_edit_post_link( $post->ID, 'raw' );

		if ( ! $edit_url ) {
			return $actions;
		}

		$editors = self::get_enabled_editors_for_post( $post );

		if ( ! $editors['classic_editor'] || ! $editors['block_editor'] ) {
			return $actions;
		}

		$edit_url = add_query_arg( 'classic-editor__forget', '', $edit_url );
		$title   = _draft_or_post_title( $post->ID );

		$url  = remove_query_arg( 'classic-editor', $edit_url );
		$text = _x( 'Edit (block editor)', 'Editor Name', 'classic-editor' );
		/* translators: %s: post title */
		$label     = sprintf( __( 'Edit "%s" in the block editor', 'classic-editor' ), $title );
		$edit_block = sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( $url ), esc_attr( $label ), esc_html( $text ) );

		$url  = add_query_arg( 'classic-editor', '', $edit_url );
		$text = _x( 'Edit (classic editor)', 'Editor Name', 'classic-editor' );
		/* translators: %s: post title */
		$label       = sprintf( __( 'Edit "%s" in the classic editor', 'classic-editor' ), $title );
		$edit_classic = sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( $url ), esc_attr( $label ), esc_html( $text ) );

		$edit_actions = array(
			'classic-editor-block'  => $edit_block,
			'classic-editor-classic' => $edit_classic,
		);

		$edit_offset = array_search( 'edit', array_keys( $actions ), true );
		array_splice( $actions, $edit_offset, 1, $edit_actions );

		return $actions;
	}

	/**
	 * @param array $post_states
	 * @param WP_Post $post
	 * @return array
	 */
	public static function add_post_state( array $post_states, WP_Post $post ): array {
		if ( get_post_status( $post ) === 'trash' ) {
			return $post_states;
		}

		$editors = self::get_enabled_editors_for_post( $post );

		if ( ! $editors['classic_editor'] && ! $editors['block_editor'] ) {
			return $post_states;
		} elseif ( $editors['classic_editor'] && ! $editors['block_editor'] ) {
			$state = '<span class="classic-editor-forced-state">' . esc_html_x( 'classic editor', 'Editor Name', 'classic-editor' ) . '</span>';
		} elseif ( ! $editors['classic_editor'] && $editors['block_editor'] ) {
			$state = '<span class="classic-editor-forced-state">' . esc_html_x( 'block editor', 'Editor Name', 'classic-editor' ) . '</span>';
		} else {
			$last_editor = get_post_meta( $post->ID, 'classic-editor-remember', true );

			if ( $last_editor ) {
				$is_classic = $last_editor === 'classic-editor';
			} elseif ( ! empty( $post->post_content ) ) {
				$is_classic = ! self::has_blocks( (int) $post->ID );
			} else {
				$settings    = self::get_settings();
				$is_classic = $settings['editor'] === 'classic';
			}

			$state = $is_classic ? esc_html_x( 'Classic editor', 'Editor Name', 'classic-editor' ) : esc_html_x( 'Block editor', 'Editor Name', 'classic-editor' );
		}

		$post_states          = array_merge( array(), $post_states );
		$post_states['classic-editor-plugin'] = $state;

		return $post_states;
	}

	public static function add_edit_php_inline_style(): void {
		?>
		<style>
		.classic-editor-forced-state {
			font-style: italic;
			font-weight: 400;
			color: #72777c;
			font-size: small;
		}
		</style>
		<?php
	}

	public static function on_admin_init(): void {
		global $pagenow;

		if ( $pagenow !== 'post.php' ) {
			return;
		}

		$settings = self::get_settings();
		$post_id  = self::get_edited_post_id();

		if ( $post_id && ( $settings['editor'] === 'classic' || self::is_classic( $post_id ) ) ) {
			remove_action( 'admin_notices', array( 'WP_Privacy_Policy_Content', 'notice' ) );
			add_action( 'edit_form_after_title', array( 'WP_Privacy_Policy_Content', 'notice' ) );
		}
	}

	/**
	 * @param int|WP_Post|null $post
	 * @return bool
	 */
	private static function has_blocks( $post = null ): bool {
		if ( $post instanceof WP_Post ) {
			$post = $post->post_content;
		} elseif ( is_int( $post ) && $post > 0 ) {
			$wp_post = get_post( $post );
			$post   = $wp_post instanceof WP_Post ? $wp_post->post_content : '';
		}

		$post = is_string( $post ) ? $post : '';

		return str_contains( $post, '<!-- wp:' );
	}

	public static function activate(): void {
		register_uninstall_hook( __FILE__, array( self::class, 'uninstall' ) );

		if ( is_multisite() ) {
			add_network_option( null, 'classic-editor-replace', 'classic' );
			add_network_option( null, 'classic-editor-allow-sites', 'disallow' );
		}

		add_option( 'classic-editor-replace', 'classic' );
		add_option( 'classic-editor-allow-users', 'disallow' );

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	public static function uninstall(): void {
		if ( is_multisite() ) {
			delete_network_option( null, 'classic-editor-replace' );
			delete_network_option( null, 'classic-editor-allow-sites' );
		}

		delete_option( 'classic-editor-replace' );
		delete_option( 'classic-editor-allow-users' );

		delete_metadata( 'post', 0, 'classic-editor-remember', '', true );
	}

	public static function safari_fix(): void {
		global $current_screen;

		if ( isset( $current_screen->base ) && 'post' === $current_screen->base ) {
			$clear = is_rtl() ? 'right' : 'left';

			?>
			<style id="classic-editor-safari-fix">
			_::-webkit-full-page-media, _:future, :root #post-body #postbox-container-2 {
				clear: <?php echo esc_html( $clear ); ?>;
			}
			</style>
			<?php
		}
	}

	/**
	 * @param string $src
	 * @param string $handle
	 * @return string
	 */
	public static function fix_post_js( string $src, string $handle ): string {
		if ( $handle === 'post' && is_string( $src ) && ! str_contains( $src, 'ver=62504-20241121' ) ) {
			$suffix = wp_scripts_get_suffix();
			$src   = plugins_url( 'scripts/', __FILE__ ) . "post{$suffix}.js";
			$src   = add_query_arg( 'ver', '62504-20241121', $src );
		}

		return $src;
	}
}

add_action( 'plugins_loaded', array( Classic_Editor::class, 'init_actions' ) );

endif;