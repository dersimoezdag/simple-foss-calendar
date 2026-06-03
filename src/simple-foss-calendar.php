<?php
/**
 * Plugin Name: Simple FOSS Calendar
 * Description: Adds an accessible events calendar and upcoming-events list to any WordPress site.
 * Version: 0.1.24
 * Author: Simple FOSS Calendar Contributors
 * License: GPL-2.0-or-later
 * Text Domain: simple-foss-calendar
 * Domain Path: /languages
 *
 * @package SimpleFossCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFC_VERSION', '0.1.24' );
define( 'SFC_PLUGIN_FILE', __FILE__ );
define( 'SFC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Loads plugin translations.
 */
function sfc_load_textdomain() {
	load_plugin_textdomain( 'simple-foss-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'sfc_load_textdomain' );

/**
 * Registers the Event post type and event topic taxonomy.
 */
function sfc_register_content_types() {
	$event_labels = array(
		'name'                  => _x( 'Events', 'post type general name', 'simple-foss-calendar' ),
		'singular_name'         => _x( 'Event', 'post type singular name', 'simple-foss-calendar' ),
		'menu_name'             => _x( 'Events', 'admin menu', 'simple-foss-calendar' ),
		'name_admin_bar'        => _x( 'Event', 'add new on admin bar', 'simple-foss-calendar' ),
		'add_new'               => _x( 'Add New', 'event', 'simple-foss-calendar' ),
		'add_new_item'          => __( 'Add New Event', 'simple-foss-calendar' ),
		'new_item'              => __( 'New Event', 'simple-foss-calendar' ),
		'edit_item'             => __( 'Edit Event', 'simple-foss-calendar' ),
		'view_item'             => __( 'View Event', 'simple-foss-calendar' ),
		'all_items'             => __( 'All Events', 'simple-foss-calendar' ),
		'search_items'          => __( 'Search Events', 'simple-foss-calendar' ),
		'not_found'             => __( 'No events found.', 'simple-foss-calendar' ),
		'not_found_in_trash'    => __( 'No events found in Trash.', 'simple-foss-calendar' ),
		'featured_image'        => __( 'Event image', 'simple-foss-calendar' ),
		'set_featured_image'    => __( 'Set event image', 'simple-foss-calendar' ),
		'remove_featured_image' => __( 'Remove event image', 'simple-foss-calendar' ),
	);

	register_post_type(
		'sfc_event',
		array(
			'labels'       => $event_labels,
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'rewrite'      => array( 'slug' => 'events' ),
			'has_archive'  => true,
		)
	);

	register_taxonomy(
		'sfc_event_topic',
		'sfc_event',
		array(
			'labels'            => array(
				'name'          => _x( 'Event Topics', 'taxonomy general name', 'simple-foss-calendar' ),
				'singular_name' => _x( 'Event Topic', 'taxonomy singular name', 'simple-foss-calendar' ),
				'search_items'  => __( 'Search Event Topics', 'simple-foss-calendar' ),
				'all_items'     => __( 'All Event Topics', 'simple-foss-calendar' ),
				'edit_item'     => __( 'Edit Event Topic', 'simple-foss-calendar' ),
				'update_item'   => __( 'Update Event Topic', 'simple-foss-calendar' ),
				'add_new_item'  => __( 'Add New Event Topic', 'simple-foss-calendar' ),
				'new_item_name' => __( 'New Event Topic Name', 'simple-foss-calendar' ),
				'menu_name'     => __( 'Event Topics', 'simple-foss-calendar' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'event-topic' ),
		)
	);
}
add_action( 'init', 'sfc_register_content_types' );

/**
 * Registers event metadata for the REST API and block editor.
 */
function sfc_register_event_meta() {
	$meta_fields = array(
		'_sfc_start_date'          => array( 'type' => 'string', 'default' => '' ),
		'_sfc_start_time'          => array( 'type' => 'string', 'default' => '' ),
		'_sfc_end_date'            => array( 'type' => 'string', 'default' => '' ),
		'_sfc_end_time'            => array( 'type' => 'string', 'default' => '' ),
		'_sfc_location'            => array( 'type' => 'string', 'default' => '' ),
		'_sfc_external_url'        => array( 'type' => 'string', 'default' => '' ),
		'_sfc_color'               => array( 'type' => 'string', 'default' => '#ffffff' ),
		'_sfc_recurrence'          => array( 'type' => 'string', 'default' => 'none' ),
		'_sfc_recurrence_interval' => array( 'type' => 'integer', 'default' => 1 ),
		'_sfc_recurrence_until'    => array( 'type' => 'string', 'default' => '' ),
		'_sfc_all_day'             => array( 'type' => 'string', 'default' => '0' ),
	);

	foreach ( $meta_fields as $meta_key => $schema ) {
		register_post_meta(
			'sfc_event',
			$meta_key,
			array(
				'type'              => $schema['type'],
				'single'            => true,
				'default'           => $schema['default'],
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => $schema['type'],
						'default' => $schema['default'],
					),
				),
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sfc_sanitize_registered_event_meta',
			)
		);
	}
}
add_action( 'init', 'sfc_register_event_meta' );

/**
 * Sanitizes registered event metadata.
 *
 * @param mixed  $value    Submitted value.
 * @param string $meta_key Meta key.
 * @return mixed
 */
function sfc_sanitize_registered_event_meta( $value, $meta_key ) {
	if ( '_sfc_start_date' === $meta_key || '_sfc_end_date' === $meta_key || '_sfc_recurrence_until' === $meta_key ) {
		return sfc_sanitize_date( $value );
	}

	if ( '_sfc_start_time' === $meta_key || '_sfc_end_time' === $meta_key ) {
		return sfc_sanitize_time( $value );
	}

	if ( '_sfc_external_url' === $meta_key ) {
		return esc_url_raw( $value );
	}

	if ( '_sfc_color' === $meta_key ) {
		return sanitize_hex_color( $value ) ? sanitize_hex_color( $value ) : '#ffffff';
	}

	if ( '_sfc_recurrence' === $meta_key ) {
		return sfc_sanitize_recurrence( $value );
	}

	if ( '_sfc_recurrence_interval' === $meta_key ) {
		return max( 1, min( 99, absint( $value ) ) );
	}

	if ( '_sfc_all_day' === $meta_key ) {
		return ! empty( $value ) && '0' !== (string) $value ? '1' : '0';
	}

	return sanitize_text_field( $value );
}

/**
 * Flush rewrites after activation so event URLs work immediately.
 */
function sfc_activate() {
	sfc_register_content_types();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sfc_activate' );

/**
 * Flush rewrites after deactivation.
 */
function sfc_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sfc_deactivate' );

/**
 * Adds event details meta box.
 */
function sfc_add_event_meta_box() {
	add_meta_box(
		'sfc_event_details',
		__( 'Event Details', 'simple-foss-calendar' ),
		'sfc_render_event_meta_box',
		'sfc_event',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'sfc_add_event_meta_box' );

/**
 * Renders event metadata fields in the editor.
 *
 * @param WP_Post $post Current event post.
 */
function sfc_render_event_meta_box( $post ) {
	wp_nonce_field( 'sfc_save_event_meta', 'sfc_event_meta_nonce' );

	$start_date = get_post_meta( $post->ID, '_sfc_start_date', true );
	$start_time = get_post_meta( $post->ID, '_sfc_start_time', true );
	$end_date   = get_post_meta( $post->ID, '_sfc_end_date', true );
	$end_time   = get_post_meta( $post->ID, '_sfc_end_time', true );
	$location   = get_post_meta( $post->ID, '_sfc_location', true );
	$url        = get_post_meta( $post->ID, '_sfc_external_url', true );
	$all_day    = get_post_meta( $post->ID, '_sfc_all_day', true );
	$color      = get_post_meta( $post->ID, '_sfc_color', true );
	$recurrence = get_post_meta( $post->ID, '_sfc_recurrence', true );
	$interval   = get_post_meta( $post->ID, '_sfc_recurrence_interval', true );
	$until      = get_post_meta( $post->ID, '_sfc_recurrence_until', true );

	if ( empty( $color ) ) {
		$color = '#ffffff';
	}

	if ( empty( $recurrence ) ) {
		$recurrence = 'none';
	}

	if ( empty( $interval ) ) {
		$interval = 1;
	}

	?>
	<div class="sfc-admin-grid">
		<p>
			<label for="sfc_start_date"><strong><?php esc_html_e( 'Start date', 'simple-foss-calendar' ); ?></strong></label>
			<input required type="date" id="sfc_start_date" name="sfc_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
		</p>
		<p>
			<label for="sfc_start_time"><strong><?php esc_html_e( 'Start time', 'simple-foss-calendar' ); ?></strong></label>
			<input type="time" id="sfc_start_time" name="sfc_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
		</p>
		<p>
			<label for="sfc_end_date"><strong><?php esc_html_e( 'End date', 'simple-foss-calendar' ); ?></strong></label>
			<input type="date" id="sfc_end_date" name="sfc_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
		</p>
		<p>
			<label for="sfc_end_time"><strong><?php esc_html_e( 'End time', 'simple-foss-calendar' ); ?></strong></label>
			<input type="time" id="sfc_end_time" name="sfc_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
		</p>
		<p>
			<label for="sfc_location"><strong><?php esc_html_e( 'Location', 'simple-foss-calendar' ); ?></strong></label>
			<input type="text" id="sfc_location" name="sfc_location" value="<?php echo esc_attr( $location ); ?>" class="widefat" />
		</p>
		<p>
			<label for="sfc_external_url"><strong><?php esc_html_e( 'External URL', 'simple-foss-calendar' ); ?></strong></label>
			<input type="url" id="sfc_external_url" name="sfc_external_url" value="<?php echo esc_url( $url ); ?>" class="widefat" />
		</p>
		<p>
			<label for="sfc_color"><strong><?php esc_html_e( 'Calendar color', 'simple-foss-calendar' ); ?></strong></label>
			<input type="color" id="sfc_color" name="sfc_color" value="<?php echo esc_attr( $color ); ?>" />
		</p>
		<p>
			<label for="sfc_recurrence"><strong><?php esc_html_e( 'Repeat', 'simple-foss-calendar' ); ?></strong></label>
			<select id="sfc_recurrence" name="sfc_recurrence" class="widefat">
				<option value="none" <?php selected( $recurrence, 'none' ); ?>><?php esc_html_e( 'Does not repeat', 'simple-foss-calendar' ); ?></option>
				<option value="daily" <?php selected( $recurrence, 'daily' ); ?>><?php esc_html_e( 'Daily', 'simple-foss-calendar' ); ?></option>
				<option value="weekly" <?php selected( $recurrence, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'simple-foss-calendar' ); ?></option>
				<option value="monthly" <?php selected( $recurrence, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'simple-foss-calendar' ); ?></option>
				<option value="yearly" <?php selected( $recurrence, 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'simple-foss-calendar' ); ?></option>
			</select>
		</p>
		<p>
			<label for="sfc_recurrence_interval"><strong><?php esc_html_e( 'Repeat every', 'simple-foss-calendar' ); ?></strong></label>
			<input type="number" id="sfc_recurrence_interval" name="sfc_recurrence_interval" value="<?php echo esc_attr( $interval ); ?>" min="1" max="99" />
		</p>
		<p>
			<label for="sfc_recurrence_until"><strong><?php esc_html_e( 'Repeat until', 'simple-foss-calendar' ); ?></strong></label>
			<input type="date" id="sfc_recurrence_until" name="sfc_recurrence_until" value="<?php echo esc_attr( $until ); ?>" />
		</p>
		<p class="sfc-admin-checkbox">
			<label>
				<input type="checkbox" name="sfc_all_day" value="1" <?php checked( $all_day, '1' ); ?> />
				<?php esc_html_e( 'All-day event', 'simple-foss-calendar' ); ?>
			</label>
		</p>
	</div>
	<style>
		.sfc-admin-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 8px 16px;
		}
		.sfc-admin-grid label {
			display: block;
			margin-bottom: 6px;
		}
		.sfc-admin-checkbox {
			align-self: end;
		}
	</style>
	<?php
}

/**
 * Saves event metadata.
 *
 * @param int $post_id Current post ID.
 */
function sfc_save_event_meta( $post_id ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	if ( ! isset( $_POST['sfc_event_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfc_event_meta_nonce'] ) ), 'sfc_save_event_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['sfc_start_date'] ) && ! isset( $_POST['sfc_location'] ) ) {
		return;
	}

	$fields = array(
		'_sfc_start_date'   => array( 'request' => 'sfc_start_date', 'sanitize' => 'sfc_sanitize_date' ),
		'_sfc_start_time'   => array( 'request' => 'sfc_start_time', 'sanitize' => 'sfc_sanitize_time' ),
		'_sfc_end_date'     => array( 'request' => 'sfc_end_date', 'sanitize' => 'sfc_sanitize_date' ),
		'_sfc_end_time'     => array( 'request' => 'sfc_end_time', 'sanitize' => 'sfc_sanitize_time' ),
		'_sfc_location'     => array( 'request' => 'sfc_location', 'sanitize' => 'sanitize_text_field' ),
		'_sfc_external_url' => array( 'request' => 'sfc_external_url', 'sanitize' => 'esc_url_raw' ),
		'_sfc_color'        => array( 'request' => 'sfc_color', 'sanitize' => 'sanitize_hex_color' ),
		'_sfc_recurrence'   => array( 'request' => 'sfc_recurrence', 'sanitize' => 'sfc_sanitize_recurrence' ),
		'_sfc_recurrence_until' => array( 'request' => 'sfc_recurrence_until', 'sanitize' => 'sfc_sanitize_date' ),
	);

	foreach ( $fields as $meta_key => $field ) {
		$value     = isset( $_POST[ $field['request'] ] ) ? wp_unslash( $_POST[ $field['request'] ] ) : '';
		$sanitized = call_user_func( $field['sanitize'], $value );

		if ( '' === $sanitized || null === $sanitized ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		update_post_meta( $post_id, $meta_key, $sanitized );
	}

	$all_day = isset( $_POST['sfc_all_day'] ) ? '1' : '0';
	update_post_meta( $post_id, '_sfc_all_day', $all_day );

	$interval = isset( $_POST['sfc_recurrence_interval'] ) ? absint( wp_unslash( $_POST['sfc_recurrence_interval'] ) ) : 1;
	update_post_meta( $post_id, '_sfc_recurrence_interval', max( 1, min( 99, $interval ) ) );
}
add_action( 'save_post_sfc_event', 'sfc_save_event_meta' );

/**
 * Sanitizes a date field.
 *
 * @param string $date Submitted date.
 * @return string
 */
function sfc_sanitize_date( $date ) {
	$date = sanitize_text_field( $date );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
}

/**
 * Sanitizes a time field.
 *
 * @param string $time Submitted time.
 * @return string
 */
function sfc_sanitize_time( $time ) {
	$time = sanitize_text_field( $time );
	return preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time : '';
}

/**
 * Sanitizes recurrence frequency.
 *
 * @param string $recurrence Submitted recurrence.
 * @return string
 */
function sfc_sanitize_recurrence( $recurrence ) {
	$recurrence = sanitize_key( $recurrence );
	return in_array( $recurrence, array( 'none', 'daily', 'weekly', 'monthly', 'yearly' ), true ) ? $recurrence : 'none';
}

/**
 * Adds event date columns to the event list table.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function sfc_event_columns( $columns ) {
	$insert = array(
		'sfc_start'    => __( 'Starts', 'simple-foss-calendar' ),
		'sfc_location' => __( 'Location', 'simple-foss-calendar' ),
	);

	return array_slice( $columns, 0, 2, true ) + $insert + array_slice( $columns, 2, null, true );
}
add_filter( 'manage_sfc_event_posts_columns', 'sfc_event_columns' );

/**
 * Prints event list table column values.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function sfc_event_column_content( $column, $post_id ) {
	if ( 'sfc_start' === $column ) {
		echo esc_html( sfc_format_event_datetime( $post_id ) );
		sfc_print_quick_edit_event_data( $post_id );
	}

	if ( 'sfc_location' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_sfc_location', true ) );
	}
}
add_action( 'manage_sfc_event_posts_custom_column', 'sfc_event_column_content', 10, 2 );

/**
 * Prints hidden event metadata used to populate Quick Edit.
 *
 * @param int $post_id Event post ID.
 */
function sfc_print_quick_edit_event_data( $post_id ) {
	$data = array(
		'startDate' => get_post_meta( $post_id, '_sfc_start_date', true ),
		'startTime' => get_post_meta( $post_id, '_sfc_start_time', true ),
		'endDate'   => get_post_meta( $post_id, '_sfc_end_date', true ),
		'endTime'   => get_post_meta( $post_id, '_sfc_end_time', true ),
		'location'  => get_post_meta( $post_id, '_sfc_location', true ),
		'allDay'    => get_post_meta( $post_id, '_sfc_all_day', true ),
	);

	printf(
		'<span class="sfc-quick-edit-data" hidden data-start-date="%1$s" data-start-time="%2$s" data-end-date="%3$s" data-end-time="%4$s" data-location="%5$s" data-all-day="%6$s"></span>',
		esc_attr( $data['startDate'] ),
		esc_attr( $data['startTime'] ),
		esc_attr( $data['endDate'] ),
		esc_attr( $data['endTime'] ),
		esc_attr( $data['location'] ),
		esc_attr( $data['allDay'] )
	);
}

/**
 * Adds event fields to the post list Quick Edit form.
 *
 * @param string $column_name Current column name.
 * @param string $post_type   Current post type.
 */
function sfc_quick_edit_event_fields( $column_name, $post_type ) {
	if ( 'sfc_event' !== $post_type || 'sfc_start' !== $column_name ) {
		return;
	}

	wp_nonce_field( 'sfc_save_event_meta', 'sfc_event_meta_nonce' );
	?>
	<fieldset class="inline-edit-col-left sfc-quick-edit-fields">
		<div class="inline-edit-col">
			<span class="title"><?php esc_html_e( 'Event date, time and place', 'simple-foss-calendar' ); ?></span>
			<label>
				<span class="title"><?php esc_html_e( 'Start date', 'simple-foss-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="date" name="sfc_start_date" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'Start time', 'simple-foss-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="time" name="sfc_start_time" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'End date', 'simple-foss-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="date" name="sfc_end_date" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'End time', 'simple-foss-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="time" name="sfc_end_time" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'Location', 'simple-foss-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="sfc_location" value="" />
				</span>
			</label>
			<label class="inline-edit-group">
				<span class="title"><?php esc_html_e( 'All-day event', 'simple-foss-calendar' ); ?></span>
				<input type="checkbox" name="sfc_all_day" value="1" />
			</label>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'sfc_quick_edit_event_fields', 10, 2 );

/**
 * Makes start column sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function sfc_sortable_event_columns( $columns ) {
	$columns['sfc_start'] = 'sfc_start';
	return $columns;
}
add_filter( 'manage_edit-sfc_event_sortable_columns', 'sfc_sortable_event_columns' );

/**
 * Applies sorting by event start date in admin.
 *
 * @param WP_Query $query Query instance.
 */
function sfc_admin_event_ordering( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'sfc_start' !== $query->get( 'orderby' ) ) {
		return;
	}

	$query->set( 'meta_key', '_sfc_start_date' );
	$query->set( 'orderby', 'meta_value' );
}
add_action( 'pre_get_posts', 'sfc_admin_event_ordering' );

/**
 * Adds a duplicate action to event row actions.
 *
 * @param array   $actions Existing row actions.
 * @param WP_Post $post    Current post.
 * @return array
 */
function sfc_add_duplicate_event_action( $actions, $post ) {
	if ( 'sfc_event' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action'  => 'sfc_duplicate_event',
				'post_id' => $post->ID,
			),
			admin_url( 'admin.php' )
		),
		'sfc_duplicate_event_' . $post->ID
	);

	$actions['sfc_duplicate_event'] = sprintf(
		'<a href="%1$s" aria-label="%2$s">%3$s</a>',
		esc_url( $url ),
		esc_attr__( 'Duplicate this event', 'simple-foss-calendar' ),
		esc_html__( 'Duplicate', 'simple-foss-calendar' )
	);

	return $actions;
}
add_filter( 'post_row_actions', 'sfc_add_duplicate_event_action', 10, 2 );

/**
 * Handles event duplication.
 */
function sfc_duplicate_event() {
	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to duplicate this event.', 'simple-foss-calendar' ) );
	}

	check_admin_referer( 'sfc_duplicate_event_' . $post_id );

	$source = get_post( $post_id );
	if ( ! $source || 'sfc_event' !== $source->post_type ) {
		wp_die( esc_html__( 'Event could not be found.', 'simple-foss-calendar' ) );
	}

	$new_post_id = wp_insert_post(
		array(
			'post_author'           => get_current_user_id(),
			'post_content'          => $source->post_content,
			'post_excerpt'          => $source->post_excerpt,
			'post_name'             => '',
			'post_parent'           => $source->post_parent,
			'post_password'         => $source->post_password,
			'post_status'           => 'draft',
			'post_title'            => sprintf(
				/* translators: %s: Original event title. */
				__( '%s copy', 'simple-foss-calendar' ),
				$source->post_title
			),
			'post_type'             => 'sfc_event',
			'post_content_filtered' => $source->post_content_filtered,
		),
		true
	);

	if ( is_wp_error( $new_post_id ) ) {
		wp_die( esc_html( $new_post_id->get_error_message() ) );
	}

	sfc_copy_event_metadata( $post_id, $new_post_id );
	sfc_copy_event_terms( $post_id, $new_post_id );
	sfc_copy_event_thumbnail( $post_id, $new_post_id );

	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
	exit;
}
add_action( 'admin_action_sfc_duplicate_event', 'sfc_duplicate_event' );

/**
 * Copies event metadata to a duplicated event.
 *
 * @param int $source_id Source event ID.
 * @param int $target_id Target event ID.
 */
function sfc_copy_event_metadata( $source_id, $target_id ) {
	$meta = get_post_meta( $source_id );

	foreach ( $meta as $key => $values ) {
		if ( '_edit_lock' === $key || '_edit_last' === $key ) {
			continue;
		}

		delete_post_meta( $target_id, $key );

		foreach ( $values as $value ) {
			add_post_meta( $target_id, $key, maybe_unserialize( $value ) );
		}
	}
}

/**
 * Copies event taxonomies to a duplicated event.
 *
 * @param int $source_id Source event ID.
 * @param int $target_id Target event ID.
 */
function sfc_copy_event_terms( $source_id, $target_id ) {
	$taxonomies = get_object_taxonomies( 'sfc_event' );

	foreach ( $taxonomies as $taxonomy ) {
		$terms = wp_get_object_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) ) {
			continue;
		}

		wp_set_object_terms( $target_id, $terms, $taxonomy );
	}
}

/**
 * Copies the featured image to a duplicated event.
 *
 * @param int $source_id Source event ID.
 * @param int $target_id Target event ID.
 */
function sfc_copy_event_thumbnail( $source_id, $target_id ) {
	$thumbnail_id = get_post_thumbnail_id( $source_id );

	if ( $thumbnail_id ) {
		set_post_thumbnail( $target_id, $thumbnail_id );
	}
}

/**
 * Registers scripts, styles, shortcodes, and REST route.
 */
function sfc_register_frontend_assets() {
	wp_register_style(
		'sfc-calendar',
		SFC_PLUGIN_URL . 'assets/calendar.css',
		array(),
		SFC_VERSION
	);

	wp_register_script(
		'sfc-calendar',
		SFC_PLUGIN_URL . 'assets/calendar.js',
		array(),
		SFC_VERSION,
		true
	);

	wp_localize_script(
		'sfc-calendar',
		'sfcCalendarSettings',
		array(
			'restUrl'      => esc_url_raw( rest_url( 'simple-foss-calendar/v1/events' ) ),
			'locale'       => str_replace( '_', '-', get_locale() ),
			'firstWeekday' => absint( get_option( 'start_of_week', 1 ) ),
			'labels'       => array(
				'next'      => __( 'Next month', 'simple-foss-calendar' ),
				'previous'  => __( 'Previous month', 'simple-foss-calendar' ),
				'today'     => __( 'Today', 'simple-foss-calendar' ),
				'loading'   => __( 'Loading events...', 'simple-foss-calendar' ),
				'noEvents'  => __( 'No events this month.', 'simple-foss-calendar' ),
				'viewEvent' => __( 'View event', 'simple-foss-calendar' ),
			),
		)
	);
}
add_action( 'init', 'sfc_register_frontend_assets' );

/**
 * Registers editor assets and dynamic blocks.
 */
function sfc_register_blocks() {
	wp_register_script(
		'sfc-upcoming-events-block',
		SFC_PLUGIN_URL . 'assets/upcoming-events-block.js',
		array( 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-server-side-render' ),
		SFC_VERSION,
		true
	);

	wp_set_script_translations( 'sfc-upcoming-events-block', 'simple-foss-calendar', SFC_PLUGIN_DIR . 'languages' );
	sfc_add_block_editor_locale_data();

	register_block_type(
		'simple-foss-calendar/upcoming-events',
		array(
			'api_version'     => 2,
			'title'           => __( 'Upcoming Events', 'simple-foss-calendar' ),
			'description'     => __( 'Shows a styled list of upcoming events.', 'simple-foss-calendar' ),
			'category'        => 'widgets',
			'icon'            => 'calendar-alt',
			'editor_script'   => 'sfc-upcoming-events-block',
			'render_callback' => 'sfc_render_upcoming_events_block',
			'attributes'      => array(
				'category'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'maxEvents' => array(
					'type'    => 'number',
					'default' => 6,
				),
				'showPlace' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTime'  => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'displayStyle' => array(
					'type'    => 'string',
					'default' => 'list',
				),
			),
		)
	);
}
add_action( 'init', 'sfc_register_blocks' );

/**
 * Registers admin editor assets.
 */
function sfc_register_admin_assets() {
	wp_register_script(
		'sfc-event-editor',
		SFC_PLUGIN_URL . 'assets/event-editor.js',
		array( 'wp-api-fetch', 'wp-components', 'wp-compose', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins' ),
		SFC_VERSION,
		true
	);

	wp_set_script_translations( 'sfc-event-editor', 'simple-foss-calendar', SFC_PLUGIN_DIR . 'languages' );

	wp_register_script(
		'sfc-shortcode-generator',
		SFC_PLUGIN_URL . 'assets/shortcode-generator.js',
		array(),
		SFC_VERSION,
		true
	);

	wp_register_script(
		'sfc-quick-edit',
		SFC_PLUGIN_URL . 'assets/quick-edit.js',
		array( 'inline-edit-post' ),
		SFC_VERSION,
		true
	);
}
add_action( 'admin_init', 'sfc_register_admin_assets' );

/**
 * Enqueues shortcode generator assets on the event list screen.
 *
 * @param string $hook Current admin hook.
 */
function sfc_enqueue_shortcode_generator_assets( $hook ) {
	if ( 'edit.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'sfc_event' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script( 'sfc-shortcode-generator' );
	wp_enqueue_script( 'sfc-quick-edit' );
}
add_action( 'admin_enqueue_scripts', 'sfc_enqueue_shortcode_generator_assets' );

/**
 * Enqueues the event editor sidebar for event posts.
 *
 * @param string $hook Current admin hook.
 */
function sfc_enqueue_event_editor_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'sfc_event' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script( 'sfc-event-editor' );
	sfc_add_event_editor_locale_data();
}
add_action( 'admin_enqueue_scripts', 'sfc_enqueue_event_editor_assets' );

/**
 * Renders a shortcode generator below the event list table.
 */
function sfc_render_shortcode_generator( $which ) {
	if ( 'bottom' !== $which ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'edit-sfc_event' !== $screen->id ) {
		return;
	}

	$topics = get_terms(
		array(
			'taxonomy'   => 'sfc_event_topic',
			'hide_empty' => false,
		)
	);
	?>
	<div class="sfc-shortcode-generator postbox" style="margin-top: 20px; max-width: 960px;">
		<div class="postbox-header">
			<h2><?php esc_html_e( 'Shortcode Generator', 'simple-foss-calendar' ); ?></h2>
		</div>
		<div class="inside">
			<p><?php esc_html_e( 'Use these shortcodes in pages, posts, widgets, or template areas to show your events.', 'simple-foss-calendar' ); ?></p>

			<div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
				<div>
					<h3><?php esc_html_e( 'Upcoming events list', 'simple-foss-calendar' ); ?></h3>
					<p>
						<label for="sfc_shortcode_category"><strong><?php esc_html_e( 'Event category slug', 'simple-foss-calendar' ); ?></strong></label>
						<select id="sfc_shortcode_category" class="widefat" data-sfc-shortcode-field="category">
							<option value=""><?php esc_html_e( 'All event topics', 'simple-foss-calendar' ); ?></option>
							<?php if ( ! is_wp_error( $topics ) ) : ?>
								<?php foreach ( $topics as $topic ) : ?>
									<option value="<?php echo esc_attr( $topic->slug ); ?>"><?php echo esc_html( $topic->name . ' (' . $topic->slug . ')' ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</p>
					<p>
						<label for="sfc_shortcode_max_events"><strong><?php esc_html_e( 'Maximum events', 'simple-foss-calendar' ); ?></strong></label>
						<input id="sfc_shortcode_max_events" type="number" min="1" max="50" value="6" class="small-text" data-sfc-shortcode-field="max-events" />
					</p>
					<p>
						<label for="sfc_shortcode_style"><strong><?php esc_html_e( 'Style', 'simple-foss-calendar' ); ?></strong></label>
						<select id="sfc_shortcode_style" class="widefat" data-sfc-shortcode-field="style">
							<option value="list"><?php esc_html_e( 'List', 'simple-foss-calendar' ); ?></option>
							<option value="minimal-list"><?php esc_html_e( 'Minimal list', 'simple-foss-calendar' ); ?></option>
							<option value="calendar"><?php esc_html_e( 'Calendar', 'simple-foss-calendar' ); ?></option>
						</select>
					</p>
					<p>
						<label><input type="checkbox" checked data-sfc-shortcode-field="show-place" /> <?php esc_html_e( 'Show place', 'simple-foss-calendar' ); ?></label><br />
						<label><input type="checkbox" checked data-sfc-shortcode-field="show-time" /> <?php esc_html_e( 'Show time', 'simple-foss-calendar' ); ?></label>
					</p>
					<p>
						<label for="sfc_shortcode_events"><strong><?php esc_html_e( 'Generated shortcode', 'simple-foss-calendar' ); ?></strong></label>
						<input id="sfc_shortcode_events" class="widefat code" type="text" readonly data-sfc-shortcode-output="events" value='[simple_foss_events max-events="6" show-place="true" show-time="true" style="list"]' />
					</p>
					<p><button type="button" class="button" data-sfc-copy-shortcode="sfc_shortcode_events"><?php esc_html_e( 'Copy shortcode', 'simple-foss-calendar' ); ?></button></p>
				</div>

				<div>
					<h3><?php esc_html_e( 'Full month calendar', 'simple-foss-calendar' ); ?></h3>
					<p>
						<label for="sfc_shortcode_calendar_topic"><strong><?php esc_html_e( 'Event category slug', 'simple-foss-calendar' ); ?></strong></label>
						<select id="sfc_shortcode_calendar_topic" class="widefat" data-sfc-calendar-field="topic">
							<option value=""><?php esc_html_e( 'All event topics', 'simple-foss-calendar' ); ?></option>
							<?php if ( ! is_wp_error( $topics ) ) : ?>
								<?php foreach ( $topics as $topic ) : ?>
									<option value="<?php echo esc_attr( $topic->slug ); ?>"><?php echo esc_html( $topic->name . ' (' . $topic->slug . ')' ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</p>
					<p>
						<label><input type="checkbox" checked data-sfc-calendar-field="show_legend" /> <?php esc_html_e( 'Show legend', 'simple-foss-calendar' ); ?></label>
					</p>
					<p>
						<label for="sfc_shortcode_calendar"><strong><?php esc_html_e( 'Generated shortcode', 'simple-foss-calendar' ); ?></strong></label>
						<input id="sfc_shortcode_calendar" class="widefat code" type="text" readonly data-sfc-shortcode-output="calendar" value='[simple_foss_calendar show_legend="true"]' />
					</p>
					<p><button type="button" class="button" data-sfc-copy-shortcode="sfc_shortcode_calendar"><?php esc_html_e( 'Copy shortcode', 'simple-foss-calendar' ); ?></button></p>
				</div>
			</div>

			<p style="margin-top: 16px;">
				<strong><?php esc_html_e( 'Quick examples', 'simple-foss-calendar' ); ?></strong><br />
				<code>[simple_foss_events max-events="5" style="minimal-list"]</code><br />
				<code>[simple_foss_calendar]</code>
			</p>
		</div>
	</div>
	<?php
}
add_action( 'manage_posts_extra_tablenav', 'sfc_render_shortcode_generator' );

/**
 * Enqueues event styles on single event pages.
 */
function sfc_enqueue_single_event_assets() {
	if ( is_singular( 'sfc_event' ) ) {
		wp_enqueue_style( 'sfc-calendar' );
	}
}
add_action( 'wp_enqueue_scripts', 'sfc_enqueue_single_event_assets' );

/**
 * Adds lightweight JavaScript translations for the event editor sidebar.
 */
function sfc_add_event_editor_locale_data() {
	$locale = determine_locale();

	if ( 0 !== strpos( $locale, 'de_' ) && 'de' !== $locale ) {
		return;
	}

	$locale_data = array(
		''                    => array(
			'domain' => 'simple-foss-calendar',
			'lang'   => $locale,
		),
		'Event date, time and place' => array( 'Veranstaltungsdatum, Uhrzeit und Ort' ),
		'Start date'          => array( 'Startdatum' ),
		'Start time'          => array( 'Startzeit' ),
		'End date'            => array( 'Enddatum' ),
		'End time'            => array( 'Endzeit' ),
		'All-day event'       => array( 'Ganztägige Veranstaltung' ),
		'Location'            => array( 'Ort' ),
		'External URL'        => array( 'Externe URL' ),
		'Calendar color'      => array( 'Kalenderfarbe' ),
		'Repeat'              => array( 'Wiederholen' ),
		'Does not repeat'     => array( 'Wiederholt sich nicht' ),
		'Daily'               => array( 'Täglich' ),
		'Weekly'              => array( 'Wöchentlich' ),
		'Monthly'             => array( 'Monatlich' ),
		'Yearly'              => array( 'Jährlich' ),
		'Repeat every'        => array( 'Wiederholen alle' ),
		'Repeat until'        => array( 'Wiederholen bis' ),
	);

	wp_add_inline_script(
		'sfc-event-editor',
		'wp.i18n.setLocaleData(' . wp_json_encode( $locale_data ) . ', "simple-foss-calendar");',
		'before'
	);
}

/**
 * Adds lightweight JavaScript translations for the editor block labels.
 */
function sfc_add_block_editor_locale_data() {
	$locale = determine_locale();

	if ( 0 !== strpos( $locale, 'de_' ) && 'de' !== $locale ) {
		return;
	}

	$locale_data = array(
		''                              => array(
			'domain' => 'simple-foss-calendar',
			'lang'   => $locale,
		),
		'Event list settings'           => array( 'Einstellungen der Veranstaltungsliste' ),
		'Event category slug'           => array( 'Veranstaltungskategorie-Slug' ),
		'Leave empty to show all event topics.' => array( 'Leer lassen, um alle Veranstaltungsthemen anzuzeigen.' ),
		'Maximum events'                => array( 'Maximale Anzahl Veranstaltungen' ),
		'Show place'                    => array( 'Ort anzeigen' ),
		'Show time'                     => array( 'Uhrzeit anzeigen' ),
		'Style'                         => array( 'Darstellung' ),
		'List'                          => array( 'Liste' ),
		'Minimal list'                  => array( 'Minimale Liste' ),
		'Calendar'                      => array( 'Kalender' ),
	);

	wp_add_inline_script(
		'sfc-upcoming-events-block',
		'wp.i18n.setLocaleData(' . wp_json_encode( $locale_data ) . ', "simple-foss-calendar");',
		'before'
	);
}

/**
 * Enqueues frontend assets.
 */
function sfc_enqueue_calendar_assets() {
	wp_enqueue_style( 'sfc-calendar' );
	wp_enqueue_script( 'sfc-calendar' );
}

/**
 * Renders interactive month calendar shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function sfc_calendar_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'topic'       => '',
			'height'      => 'auto',
			'show_legend' => 'true',
		),
		$atts,
		'simple_foss_calendar'
	);

	sfc_enqueue_calendar_assets();

	$calendar_id = wp_unique_id( 'sfc-calendar-' );
	$topic       = sanitize_title( $atts['topic'] );
	$height      = 'auto' === $atts['height'] ? 'auto' : max( 320, absint( $atts['height'] ) ) . 'px';
	$show_legend = filter_var( $atts['show_legend'], FILTER_VALIDATE_BOOLEAN );

	ob_start();
	?>
	<div
		id="<?php echo esc_attr( $calendar_id ); ?>"
		class="sfc-calendar"
		data-topic="<?php echo esc_attr( $topic ); ?>"
		data-show-legend="<?php echo esc_attr( $show_legend ? 'true' : 'false' ); ?>"
		style="--sfc-calendar-min-height: <?php echo esc_attr( $height ); ?>;"
	>
		<div class="sfc-calendar__toolbar">
			<button type="button" class="sfc-calendar__button" data-sfc-action="previous" aria-label="<?php esc_attr_e( 'Previous month', 'simple-foss-calendar' ); ?>">
				<span aria-hidden="true">&lsaquo;</span>
			</button>
			<h2 class="sfc-calendar__title" data-sfc-title></h2>
			<div class="sfc-calendar__actions">
				<button type="button" class="sfc-calendar__button sfc-calendar__button--text" data-sfc-action="today"><?php esc_html_e( 'Today', 'simple-foss-calendar' ); ?></button>
				<button type="button" class="sfc-calendar__button" data-sfc-action="next" aria-label="<?php esc_attr_e( 'Next month', 'simple-foss-calendar' ); ?>">
					<span aria-hidden="true">&rsaquo;</span>
				</button>
			</div>
		</div>
		<div class="sfc-calendar__status" data-sfc-status role="status"><?php esc_html_e( 'Loading events...', 'simple-foss-calendar' ); ?></div>
		<div class="sfc-calendar__weekdays" data-sfc-weekdays></div>
		<div class="sfc-calendar__grid" data-sfc-grid></div>
		<div class="sfc-calendar__legend" data-sfc-legend hidden></div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'simple_foss_calendar', 'sfc_calendar_shortcode' );

/**
 * Backward-friendly short alias.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function sfc_calendar_shortcode_alias( $atts ) {
	return sfc_calendar_shortcode( $atts );
}
add_shortcode( 'foss_calendar', 'sfc_calendar_shortcode_alias' );

/**
 * Renders upcoming events shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function sfc_upcoming_events_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'category'    => '',
			'limit'       => 6,
			'max-events'  => '',
			'max_events'  => '',
			'show-place'  => 'true',
			'show_place'  => '',
			'show-time'   => 'true',
			'show_time'   => '',
			'style'       => 'list',
			'topic'       => '',
		),
		$atts,
		'simple_foss_events'
	);

	return sfc_render_upcoming_events(
		array(
			'category'   => sfc_first_filled_value( array( $atts['category'], $atts['topic'] ) ),
			'max_events' => sfc_first_filled_value( array( $atts['max-events'], $atts['max_events'], $atts['limit'] ) ),
			'show_place' => sfc_first_filled_value( array( $atts['show-place'], $atts['show_place'] ), 'true' ),
			'show_time'  => sfc_first_filled_value( array( $atts['show-time'], $atts['show_time'] ), 'true' ),
			'style'      => $atts['style'],
		)
	);
}
add_shortcode( 'simple_foss_events', 'sfc_upcoming_events_shortcode' );

/**
 * Prepends event details to single event content.
 *
 * @param string $content Post content.
 * @return string
 */
function sfc_add_single_event_details_to_content( $content ) {
	static $rendering = false;

	if ( ! is_singular( 'sfc_event' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( $rendering ) {
		return $content;
	}

	$rendering = true;
	$details = sfc_render_single_event_details( get_the_ID() );
	$next    = sfc_render_single_next_events();
	$rendering = false;

	if ( empty( $details ) && empty( $next ) ) {
		return $content;
	}

	return $details . $content . $next;
}
add_filter( 'the_content', 'sfc_add_single_event_details_to_content', 8 );

/**
 * Renders a compact upcoming-events section below single event content.
 *
 * @return string
 */
function sfc_render_single_next_events() {
	$list = sfc_render_upcoming_events(
		array(
			'max_events' => 5,
			'show_place' => true,
			'show_time'  => true,
			'style'      => 'minimal-list',
		)
	);

	if ( empty( $list ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="sfc-single-next-events" aria-labelledby="sfc-single-next-events-title">
		<h2 id="sfc-single-next-events-title" class="sfc-single-next-events__title"><?php esc_html_e( 'Next Events', 'simple-foss-calendar' ); ?></h2>
		<?php echo $list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Renders the detail panel for a single event page.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function sfc_render_single_event_details( $post_id ) {
	$start_date = get_post_meta( $post_id, '_sfc_start_date', true );
	$start_time = get_post_meta( $post_id, '_sfc_start_time', true );
	$end_date   = get_post_meta( $post_id, '_sfc_end_date', true );
	$end_time   = get_post_meta( $post_id, '_sfc_end_time', true );
	$all_day    = '1' === get_post_meta( $post_id, '_sfc_all_day', true );
	$location   = get_post_meta( $post_id, '_sfc_location', true );
	$external   = get_post_meta( $post_id, '_sfc_external_url', true );

	if ( empty( $start_date ) && empty( $location ) && empty( $external ) ) {
		return '';
	}

	$details = array();

	if ( ! empty( $start_date ) ) {
		$details[] = array(
			'label' => __( 'Date', 'simple-foss-calendar' ),
			'value' => sfc_format_event_date_range_value( $start_date, $end_date ),
		);
	}

	if ( ! $all_day && ! empty( $start_time ) ) {
		$details[] = array(
			'label' => __( 'Time', 'simple-foss-calendar' ),
			'value' => sfc_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		);
	}

	if ( ! empty( $location ) ) {
		$details[] = array(
			'label' => __( 'Location', 'simple-foss-calendar' ),
			'value' => $location,
		);
	}

	ob_start();
	?>
	<div class="sfc-single-event">
		<dl class="sfc-single-event__details">
			<?php foreach ( $details as $detail ) : ?>
				<div class="sfc-single-event__row">
					<dt><?php echo esc_html( $detail['label'] ); ?></dt>
					<dd><?php echo esc_html( $detail['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
			<?php if ( ! empty( $external ) ) : ?>
				<div class="sfc-single-event__row">
					<dt><?php esc_html_e( 'External URL', 'simple-foss-calendar' ); ?></dt>
					<dd><a href="<?php echo esc_url( $external ); ?>"><?php echo esc_html( wp_parse_url( $external, PHP_URL_HOST ) ? wp_parse_url( $external, PHP_URL_HOST ) : $external ); ?></a></dd>
				</div>
			<?php endif; ?>
		</dl>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Returns a readable recurrence label.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function sfc_get_recurrence_label( $post_id ) {
	$recurrence = sfc_sanitize_recurrence( get_post_meta( $post_id, '_sfc_recurrence', true ) );
	$interval   = max( 1, absint( get_post_meta( $post_id, '_sfc_recurrence_interval', true ) ) );
	$until      = get_post_meta( $post_id, '_sfc_recurrence_until', true );
	$labels     = array(
		'daily'   => __( 'Daily', 'simple-foss-calendar' ),
		'weekly'  => __( 'Weekly', 'simple-foss-calendar' ),
		'monthly' => __( 'Monthly', 'simple-foss-calendar' ),
		'yearly'  => __( 'Yearly', 'simple-foss-calendar' ),
	);

	$label = isset( $labels[ $recurrence ] ) ? $labels[ $recurrence ] : __( 'Does not repeat', 'simple-foss-calendar' );

	if ( $interval > 1 ) {
		$label = sprintf(
			/* translators: 1: Recurrence frequency, 2: Interval number. */
			__( '%1$s, every %2$d intervals', 'simple-foss-calendar' ),
			$label,
			$interval
		);
	}

	if ( ! empty( $until ) ) {
		$label .= ' ' . sprintf(
			/* translators: %s: End date. */
			__( 'until %s', 'simple-foss-calendar' ),
			wp_date( get_option( 'date_format' ), strtotime( $until ) )
		);
	}

	return $label;
}

/**
 * Renders the dynamic Upcoming Events block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function sfc_render_upcoming_events_block( $attributes ) {
	return sfc_render_upcoming_events(
		array(
			'category'   => isset( $attributes['category'] ) ? $attributes['category'] : '',
			'max_events' => isset( $attributes['maxEvents'] ) ? $attributes['maxEvents'] : 6,
			'show_place' => isset( $attributes['showPlace'] ) ? $attributes['showPlace'] : true,
			'show_time'  => isset( $attributes['showTime'] ) ? $attributes['showTime'] : true,
			'style'      => isset( $attributes['displayStyle'] ) ? $attributes['displayStyle'] : 'list',
		)
	);
}

/**
 * Renders a styled upcoming-events list.
 *
 * @param array $args Display arguments.
 * @return string
 */
function sfc_render_upcoming_events( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'category'   => '',
			'max_events' => 6,
			'show_place' => true,
			'show_time'  => true,
			'style'      => 'list',
		)
	);

	wp_enqueue_style( 'sfc-calendar' );

	$category   = sanitize_title( $args['category'] );
	$max_events = max( 1, min( 50, absint( $args['max_events'] ) ) );
	$show_place = is_bool( $args['show_place'] ) ? $args['show_place'] : filter_var( $args['show_place'], FILTER_VALIDATE_BOOLEAN );
	$show_time  = is_bool( $args['show_time'] ) ? $args['show_time'] : filter_var( $args['show_time'], FILTER_VALIDATE_BOOLEAN );
	$style      = sfc_normalize_upcoming_style( $args['style'] );

	$events = sfc_get_events(
		array(
			'from'  => current_time( 'Y-m-d' ),
			'limit' => $max_events,
			'topic' => $category,
		)
	);

	$classes = array(
		'sfc-upcoming',
		'sfc-upcoming--' . $style,
	);

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<?php if ( empty( $events ) ) : ?>
			<p class="sfc-upcoming__empty"><?php esc_html_e( 'Currently there are no upcoming events.', 'simple-foss-calendar' ); ?></p>
		<?php else : ?>
			<ul class="sfc-upcoming__list">
				<?php foreach ( $events as $event ) : ?>
					<?php
					$compact_meta = array_filter(
						array(
							'date'     => $event['shortDateLabel'],
							'time'     => $show_time ? $event['compactTimeLabel'] : '',
							'location' => $show_place ? $event['location'] : '',
						)
					);
					?>
					<li class="sfc-upcoming__item" style="--sfc-event-color: <?php echo esc_attr( $event['color'] ); ?>;">
						<div class="sfc-upcoming__content">
							<span class="sfc-upcoming__date" aria-hidden="true">
								<span class="sfc-upcoming__day"><?php echo esc_html( $event['dayLabel'] ); ?></span>
								<span class="sfc-upcoming__month"><?php echo esc_html( $event['monthLabel'] ); ?></span>
								<span class="sfc-upcoming__short-date"><?php echo esc_html( $event['shortDateLabel'] ); ?></span>
							</span>
							<span class="sfc-upcoming__body">
								<span class="sfc-upcoming__headline">
									<strong class="sfc-upcoming__inline-date"><?php echo esc_html( $event['shortDateLabel'] ); ?></strong>
									<a class="sfc-upcoming__title" href="<?php echo esc_url( $event['url'] ); ?>"><?php echo esc_html( $event['title'] ); ?></a>
								</span>
								<span class="sfc-upcoming__compact-meta">
									<?php foreach ( $compact_meta as $meta_index => $meta_value ) : ?>
										<span class="sfc-upcoming__compact-meta-item sfc-upcoming__compact-meta-item--<?php echo esc_attr( $meta_index ); ?>"><?php echo esc_html( $meta_value ); ?></span>
									<?php endforeach; ?>
								</span>
								<span class="sfc-upcoming__meta">
									<?php if ( $show_time && ! empty( $event['timeLabel'] ) ) : ?>
										<span><?php echo esc_html( $event['timeLabel'] ); ?></span>
									<?php endif; ?>
									<?php if ( $show_place && ! empty( $event['location'] ) ) : ?>
										<span><?php echo esc_html( $event['location'] ); ?></span>
									<?php endif; ?>
								</span>
							</span>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Returns the first non-empty value from a list.
 *
 * @param array $values   Candidate values.
 * @param mixed $fallback Fallback value.
 * @return mixed
 */
function sfc_first_filled_value( $values, $fallback = '' ) {
	foreach ( $values as $value ) {
		if ( '' !== $value && null !== $value ) {
			return $value;
		}
	}

	return $fallback;
}

/**
 * Normalizes upcoming-events display styles.
 *
 * @param string $style Requested style.
 * @return string
 */
function sfc_normalize_upcoming_style( $style ) {
	$style = sanitize_key( $style );

	return in_array( $style, array( 'list', 'minimal-list', 'calendar' ), true ) ? $style : 'list';
}

/**
 * Registers REST API route.
 */
function sfc_register_rest_routes() {
	register_rest_route(
		'simple-foss-calendar/v1',
		'/events',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'sfc_rest_events',
			'permission_callback' => '__return_true',
			'args'                => array(
				'from'  => array(
					'sanitize_callback' => 'sfc_sanitize_date',
				),
				'to'    => array(
					'sanitize_callback' => 'sfc_sanitize_date',
				),
				'topic' => array(
					'sanitize_callback' => 'sanitize_title',
				),
			),
		)
	);

	register_rest_route(
		'simple-foss-calendar/v1',
		'/event-meta/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'sfc_rest_save_event_meta',
			'permission_callback' => 'sfc_rest_can_save_event_meta',
			'args'                => array(
				'id'   => array(
					'sanitize_callback' => 'absint',
				),
				'meta' => array(
					'type' => 'object',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'sfc_register_rest_routes' );

/**
 * Checks whether event metadata can be saved through REST.
 *
 * @param WP_REST_Request $request Request object.
 * @return bool
 */
function sfc_rest_can_save_event_meta( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'id' ) );
	$post    = get_post( $post_id );

	return $post && 'sfc_event' === $post->post_type && current_user_can( 'edit_post', $post_id );
}

/**
 * Saves event metadata through a dedicated REST endpoint.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function sfc_rest_save_event_meta( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'id' ) );
	$meta    = $request->get_param( 'meta' );

	if ( ! is_array( $meta ) ) {
		$meta = array();
	}

	$allowed = sfc_event_meta_keys();

	foreach ( $allowed as $meta_key ) {
		if ( ! array_key_exists( $meta_key, $meta ) ) {
			continue;
		}

		$value = sfc_sanitize_registered_event_meta( $meta[ $meta_key ], $meta_key );

		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'meta'    => sfc_get_event_meta_for_response( $post_id ),
		)
	);
}

/**
 * Returns event meta keys managed by this plugin.
 *
 * @return array
 */
function sfc_event_meta_keys() {
	return array(
		'_sfc_start_date',
		'_sfc_start_time',
		'_sfc_end_date',
		'_sfc_end_time',
		'_sfc_location',
		'_sfc_external_url',
		'_sfc_color',
		'_sfc_recurrence',
		'_sfc_recurrence_interval',
		'_sfc_recurrence_until',
		'_sfc_all_day',
	);
}

/**
 * Returns current event metadata for REST responses.
 *
 * @param int $post_id Event post ID.
 * @return array
 */
function sfc_get_event_meta_for_response( $post_id ) {
	$response = array();

	foreach ( sfc_event_meta_keys() as $meta_key ) {
		$response[ $meta_key ] = get_post_meta( $post_id, $meta_key, true );
	}

	return $response;
}

/**
 * Handles REST event requests.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function sfc_rest_events( WP_REST_Request $request ) {
	$events = sfc_get_events(
		array(
			'from'  => $request->get_param( 'from' ),
			'to'    => $request->get_param( 'to' ),
			'topic' => $request->get_param( 'topic' ),
			'limit' => 200,
		)
	);

	return rest_ensure_response( $events );
}

/**
 * Fetches normalized event data.
 *
 * @param array $args Query arguments.
 * @return array
 */
function sfc_get_events( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'from'  => '',
			'to'    => '',
			'topic' => '',
			'limit' => 100,
		)
	);

	$meta_query = array(
		array(
			'key'     => '_sfc_start_date',
			'compare' => 'EXISTS',
		),
	);

	if ( ! empty( $args['to'] ) ) {
		$meta_query[] = array(
			'key'     => '_sfc_start_date',
			'value'   => $args['to'],
			'compare' => '<=',
			'type'    => 'DATE',
		);
	}

	$query_args = array(
		'post_type'      => 'sfc_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_sfc_start_date',
		'orderby'        => array(
			'meta_value' => 'ASC',
			'date'       => 'ASC',
		),
		'meta_query'     => $meta_query,
	);

	if ( ! empty( $args['topic'] ) ) {
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'sfc_event_topic',
				'field'    => 'slug',
				'terms'    => $args['topic'],
			),
		);
	}

	$query  = new WP_Query( $query_args );
	$events = array();
	$limit  = max( 1, min( 200, absint( $args['limit'] ) ) );

	foreach ( $query->posts as $post ) {
		$events = array_merge( $events, sfc_expand_event_occurrences( $post, $args['from'], $args['to'] ) );
	}

	wp_reset_postdata();

	usort(
		$events,
		function ( $left, $right ) {
			return strcmp( $left['start'], $right['start'] );
		}
	);

	return array_slice( $events, 0, $limit );
}

/**
 * Expands one event post into displayable occurrences.
 *
 * @param WP_Post $post Event post.
 * @param string  $from Range start date.
 * @param string  $to   Range end date.
 * @return array
 */
function sfc_expand_event_occurrences( $post, $from = '', $to = '' ) {
	$start_date = get_post_meta( $post->ID, '_sfc_start_date', true );
	$end_date   = get_post_meta( $post->ID, '_sfc_end_date', true );

	if ( empty( $start_date ) ) {
		return array();
	}

	if ( empty( $end_date ) ) {
		$end_date = $start_date;
	}

	$recurrence = sfc_sanitize_recurrence( get_post_meta( $post->ID, '_sfc_recurrence', true ) );
	$interval   = max( 1, absint( get_post_meta( $post->ID, '_sfc_recurrence_interval', true ) ) );
	$until      = get_post_meta( $post->ID, '_sfc_recurrence_until', true );
	$range_from = ! empty( $from ) ? $from : current_time( 'Y-m-d' );
	$range_to   = ! empty( $to ) ? $to : wp_date( 'Y-m-d', strtotime( $range_from . ' +2 years' ) );
	$duration   = max( 0, sfc_days_between( $start_date, $end_date ) );

	if ( 'none' === $recurrence ) {
		if ( sfc_event_range_intersects( $start_date, $end_date, $range_from, $range_to ) ) {
			return array( sfc_normalize_event( $post, $start_date, $end_date ) );
		}

		return array();
	}

	if ( ! empty( $until ) && $until < $range_from ) {
		return array();
	}

	$occurrences = array();
	$cursor      = new DateTimeImmutable( $start_date );
	$max_date    = new DateTimeImmutable( ! empty( $until ) && $until < $range_to ? $until : $range_to );
	$guard       = 0;

	while ( $cursor <= $max_date && $guard < 1000 ) {
		$occurrence_start = $cursor->format( 'Y-m-d' );
		$occurrence_end   = $cursor->modify( '+' . $duration . ' days' )->format( 'Y-m-d' );

		if ( sfc_event_range_intersects( $occurrence_start, $occurrence_end, $range_from, $range_to ) ) {
			$occurrences[] = sfc_normalize_event( $post, $occurrence_start, $occurrence_end );
		}

		$cursor = sfc_next_recurrence_date( $cursor, $recurrence, $interval );
		$guard++;
	}

	return $occurrences;
}

/**
 * Returns the next recurrence date.
 *
 * @param DateTimeImmutable $date       Current date.
 * @param string            $recurrence Recurrence frequency.
 * @param int               $interval   Interval.
 * @return DateTimeImmutable
 */
function sfc_next_recurrence_date( DateTimeImmutable $date, $recurrence, $interval ) {
	$interval = max( 1, absint( $interval ) );

	if ( 'daily' === $recurrence ) {
		return $date->modify( '+' . $interval . ' days' );
	}

	if ( 'weekly' === $recurrence ) {
		return $date->modify( '+' . $interval . ' weeks' );
	}

	if ( 'monthly' === $recurrence ) {
		return $date->modify( '+' . $interval . ' months' );
	}

	if ( 'yearly' === $recurrence ) {
		return $date->modify( '+' . $interval . ' years' );
	}

	return $date->modify( '+1 day' );
}

/**
 * Checks whether an event range intersects a display range.
 *
 * @param string $event_start Event start date.
 * @param string $event_end   Event end date.
 * @param string $range_start Range start date.
 * @param string $range_end   Range end date.
 * @return bool
 */
function sfc_event_range_intersects( $event_start, $event_end, $range_start, $range_end ) {
	return $event_start <= $range_end && $event_end >= $range_start;
}

/**
 * Returns whole-day distance between two dates.
 *
 * @param string $start Start date.
 * @param string $end   End date.
 * @return int
 */
function sfc_days_between( $start, $end ) {
	$start_date = new DateTimeImmutable( $start );
	$end_date   = new DateTimeImmutable( $end );

	return (int) $start_date->diff( $end_date )->format( '%r%a' );
}

/**
 * Normalizes a post into event data for frontend use.
 *
 * @param WP_Post $post             Event post.
 * @param string  $occurrence_start Occurrence start date.
 * @param string  $occurrence_end   Occurrence end date.
 * @return array
 */
function sfc_normalize_event( $post, $occurrence_start = '', $occurrence_end = '' ) {
	$start_date = get_post_meta( $post->ID, '_sfc_start_date', true );
	$start_time = get_post_meta( $post->ID, '_sfc_start_time', true );
	$end_date   = get_post_meta( $post->ID, '_sfc_end_date', true );
	$end_time   = get_post_meta( $post->ID, '_sfc_end_time', true );
	$location   = get_post_meta( $post->ID, '_sfc_location', true );
	$external   = get_post_meta( $post->ID, '_sfc_external_url', true );
	$all_day    = '1' === get_post_meta( $post->ID, '_sfc_all_day', true );
	$color      = get_post_meta( $post->ID, '_sfc_color', true );
	$terms      = get_the_terms( $post, 'sfc_event_topic' );

	if ( empty( $end_date ) ) {
		$end_date = $start_date;
	}

	if ( ! empty( $occurrence_start ) ) {
		$start_date = $occurrence_start;
	}

	if ( ! empty( $occurrence_end ) ) {
		$end_date = $occurrence_end;
	}

	if ( empty( $color ) ) {
		$color = '#ffffff';
	}

	return array(
		'id'        => $post->ID . ':' . $start_date,
		'postId'    => $post->ID,
		'title'     => get_the_title( $post ),
		'start'     => sfc_combine_date_time( $start_date, $start_time, $all_day ),
		'end'       => sfc_combine_date_time( $end_date, $end_time, $all_day ),
		'allDay'    => $all_day,
		'url'       => ! empty( $external ) ? $external : get_permalink( $post ),
		'permalink' => get_permalink( $post ),
		'location'  => $location,
		'excerpt'   => sfc_get_plain_event_excerpt( $post ),
		'color'     => sanitize_hex_color( $color ) ? $color : '#ffffff',
		'topics'    => is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array(),
		'recurring' => 'none' !== sfc_sanitize_recurrence( get_post_meta( $post->ID, '_sfc_recurrence', true ) ),
		'dateLabel' => sfc_format_event_datetime_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		'shortDateLabel' => sfc_format_event_short_date_value( $start_date ),
		'timeLabel' => sfc_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		'compactTimeLabel' => sfc_format_event_compact_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		'dayLabel'  => sfc_format_event_day_value( $start_date ),
		'monthLabel' => sfc_format_event_month_value( $start_date ),
	);
}

/**
 * Returns a plain excerpt without invoking content/excerpt filters.
 *
 * @param WP_Post $post Event post.
 * @return string
 */
function sfc_get_plain_event_excerpt( $post ) {
	if ( ! empty( $post->post_excerpt ) ) {
		return wp_strip_all_tags( $post->post_excerpt );
	}

	if ( empty( $post->post_content ) ) {
		return '';
	}

	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 28 );
}

/**
 * Combines date and time metadata into an ISO-like local string.
 *
 * @param string $date    Date.
 * @param string $time    Time.
 * @param bool   $all_day Whether this is all day.
 * @return string
 */
function sfc_combine_date_time( $date, $time, $all_day ) {
	if ( empty( $date ) ) {
		return '';
	}

	if ( $all_day || empty( $time ) ) {
		return $date;
	}

	return $date . 'T' . $time . ':00';
}

/**
 * Formats event date and time for display.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function sfc_format_event_datetime( $post_id ) {
	$start_date = get_post_meta( $post_id, '_sfc_start_date', true );
	$start_time = get_post_meta( $post_id, '_sfc_start_time', true );
	$end_date   = get_post_meta( $post_id, '_sfc_end_date', true );
	$end_time   = get_post_meta( $post_id, '_sfc_end_time', true );
	$all_day    = '1' === get_post_meta( $post_id, '_sfc_all_day', true );

	return sfc_format_event_datetime_value( $start_date, $start_time, $end_date, $end_time, $all_day );
}

/**
 * Formats event date and time values for display.
 *
 * @param string $start_date Start date.
 * @param string $start_time Start time.
 * @param string $end_date   End date.
 * @param string $end_time   End time.
 * @param bool   $all_day    Whether this is all day.
 * @return string
 */
function sfc_format_event_datetime_value( $start_date, $start_time, $end_date, $end_time, $all_day ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	$date_format = get_option( 'date_format' );
	$start_label = wp_date( $date_format, strtotime( $start_date ) );

	if ( ! empty( $end_date ) && $end_date !== $start_date ) {
		$start_label .= ' - ' . wp_date( $date_format, strtotime( $end_date ) );
	}

	if ( ! $all_day && ! empty( $start_time ) ) {
		$start_label .= ', ' . sfc_format_local_time_value( $start_time );

		if ( ! empty( $end_time ) ) {
			$start_label .= ' - ' . sfc_format_local_time_value( $end_time );
		}
	}

	return $start_label;
}

/**
 * Formats an event date range.
 *
 * @param string $start_date Start date.
 * @param string $end_date   End date.
 * @return string
 */
function sfc_format_event_date_range_value( $start_date, $end_date = '' ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	$date_format = get_option( 'date_format' );
	$label       = wp_date( $date_format, strtotime( $start_date ) );

	if ( ! empty( $end_date ) && $end_date !== $start_date ) {
		$label .= ' - ' . wp_date( $date_format, strtotime( $end_date ) );
	}

	return $label;
}

/**
 * Formats event date as a compact numeric label.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function sfc_format_event_short_date( $post_id ) {
	$start_date = get_post_meta( $post_id, '_sfc_start_date', true );

	return sfc_format_event_short_date_value( $start_date );
}

/**
 * Formats event date value as a compact numeric label.
 *
 * @param string $start_date Start date.
 * @return string
 */
function sfc_format_event_short_date_value( $start_date ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	return wp_date( 'd.m.Y', strtotime( $start_date ) );
}

/**
 * Formats event time range.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function sfc_format_event_time( $post_id ) {
	$start_date = get_post_meta( $post_id, '_sfc_start_date', true );
	$start_time = get_post_meta( $post_id, '_sfc_start_time', true );
	$end_date   = get_post_meta( $post_id, '_sfc_end_date', true );
	$end_time   = get_post_meta( $post_id, '_sfc_end_time', true );
	$all_day    = '1' === get_post_meta( $post_id, '_sfc_all_day', true );

	return sfc_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day );
}

/**
 * Formats event time values.
 *
 * @param string $start_date Start date.
 * @param string $start_time Start time.
 * @param string $end_date   End date.
 * @param string $end_time   End time.
 * @param bool   $all_day    Whether this is all day.
 * @return string
 */
function sfc_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ) {
	if ( $all_day || empty( $start_date ) || empty( $start_time ) ) {
		return '';
	}

	$label = sfc_format_local_time_value( $start_time );

	if ( ! empty( $end_time ) ) {
		$label .= ' - ' . sfc_format_local_time_value( $end_time );
	}

	return $label;
}

/**
 * Formats a stored local HH:MM time without applying timezone conversion.
 *
 * @param string $time Stored time value.
 * @return string
 */
function sfc_format_local_time_value( $time ) {
	$time = sfc_sanitize_time( $time );

	if ( empty( $time ) ) {
		return '';
	}

	return substr( $time, 0, 5 );
}

/**
 * Formats event time values for compact upcoming-event meta rows.
 *
 * @param string $start_date Start date.
 * @param string $start_time Start time.
 * @param string $end_date   End date.
 * @param string $end_time   End time.
 * @param bool   $all_day    Whether this is all day.
 * @return string
 */
function sfc_format_event_compact_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ) {
	if ( $all_day || empty( $start_date ) || empty( $start_time ) ) {
		return '';
	}

	$start_label = sfc_format_local_time_value( $start_time );

	if ( ! empty( $end_time ) ) {
		$end_label = sfc_format_local_time_value( $end_time );

		return sprintf(
			/* translators: 1: event start time, 2: event end time. */
			__( '%1$s - %2$s o\'clock', 'simple-foss-calendar' ),
			$start_label,
			$end_label
		);
	}

	return sprintf(
		/* translators: %s: event start time. */
		__( '%s o\'clock', 'simple-foss-calendar' ),
		$start_label
	);
}

/**
 * Formats event day for the visual date badge.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function sfc_format_event_day( $post_id ) {
	$start_date = get_post_meta( $post_id, '_sfc_start_date', true );

	return sfc_format_event_day_value( $start_date );
}

/**
 * Formats event day value for the visual date badge.
 *
 * @param string $start_date Start date.
 * @return string
 */
function sfc_format_event_day_value( $start_date ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	return wp_date( 'd', strtotime( $start_date ) );
}

/**
 * Formats event month for the visual date badge.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function sfc_format_event_month( $post_id ) {
	$start_date = get_post_meta( $post_id, '_sfc_start_date', true );

	return sfc_format_event_month_value( $start_date );
}

/**
 * Formats event month value for the visual date badge.
 *
 * @param string $start_date Start date.
 * @return string
 */
function sfc_format_event_month_value( $start_date ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	return wp_date( 'M', strtotime( $start_date ) );
}
