<?php
/**
 * Plugin Name: OpenAgenda Events Calendar
 * Description: Adds an accessible events calendar and upcoming-events list to any WordPress site.
 * Version: 0.1.31
 * Author: dersim
 * License: GPL-2.0-or-later
 * Text Domain: openagenda-events-calendar
 * Domain Path: /languages
 *
 * @package OpenAgendaEventsCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OPENAGENDA_VERSION', '0.1.31' );
define( 'OPENAGENDA_PLUGIN_FILE', __FILE__ );
define( 'OPENAGENDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPENAGENDA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Registers the Event post type and event topic taxonomy.
 */
function openagenda_register_content_types() {
	$event_labels = array(
		'name'                  => _x( 'Events', 'post type general name', 'openagenda-events-calendar' ),
		'singular_name'         => _x( 'Event', 'post type singular name', 'openagenda-events-calendar' ),
		'menu_name'             => _x( 'Events', 'admin menu', 'openagenda-events-calendar' ),
		'name_admin_bar'        => _x( 'Event', 'add new on admin bar', 'openagenda-events-calendar' ),
		'add_new'               => _x( 'Add New', 'event', 'openagenda-events-calendar' ),
		'add_new_item'          => __( 'Add New Event', 'openagenda-events-calendar' ),
		'new_item'              => __( 'New Event', 'openagenda-events-calendar' ),
		'edit_item'             => __( 'Edit Event', 'openagenda-events-calendar' ),
		'view_item'             => __( 'View Event', 'openagenda-events-calendar' ),
		'all_items'             => __( 'All Events', 'openagenda-events-calendar' ),
		'search_items'          => __( 'Search Events', 'openagenda-events-calendar' ),
		'not_found'             => __( 'No events found.', 'openagenda-events-calendar' ),
		'not_found_in_trash'    => __( 'No events found in Trash.', 'openagenda-events-calendar' ),
		'featured_image'        => __( 'Event image', 'openagenda-events-calendar' ),
		'set_featured_image'    => __( 'Set event image', 'openagenda-events-calendar' ),
		'remove_featured_image' => __( 'Remove event image', 'openagenda-events-calendar' ),
	);

	register_post_type(
		'openagenda_event',
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
		'openagenda_event_topic',
		'openagenda_event',
		array(
			'labels'            => array(
				'name'          => _x( 'Event Topics', 'taxonomy general name', 'openagenda-events-calendar' ),
				'singular_name' => _x( 'Event Topic', 'taxonomy singular name', 'openagenda-events-calendar' ),
				'search_items'  => __( 'Search Event Topics', 'openagenda-events-calendar' ),
				'all_items'     => __( 'All Event Topics', 'openagenda-events-calendar' ),
				'edit_item'     => __( 'Edit Event Topic', 'openagenda-events-calendar' ),
				'update_item'   => __( 'Update Event Topic', 'openagenda-events-calendar' ),
				'add_new_item'  => __( 'Add New Event Topic', 'openagenda-events-calendar' ),
				'new_item_name' => __( 'New Event Topic Name', 'openagenda-events-calendar' ),
				'menu_name'     => __( 'Event Topics', 'openagenda-events-calendar' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'event-topic' ),
		)
	);
}
add_action( 'init', 'openagenda_register_content_types' );

/**
 * Registers event metadata for the REST API and block editor.
 */
function openagenda_register_event_meta() {
	$meta_fields = array(
		'_openagenda_start_date'          => array( 'type' => 'string', 'default' => '' ),
		'_openagenda_start_time'          => array( 'type' => 'string', 'default' => '' ),
		'_openagenda_end_date'            => array( 'type' => 'string', 'default' => '' ),
		'_openagenda_end_time'            => array( 'type' => 'string', 'default' => '' ),
		'_openagenda_location'            => array( 'type' => 'string', 'default' => '' ),
		'_openagenda_external_url'        => array( 'type' => 'string', 'default' => '' ),
		'_openagenda_color'               => array( 'type' => 'string', 'default' => '#ffffff' ),
		'_openagenda_recurrence'          => array( 'type' => 'string', 'default' => 'none' ),
		'_openagenda_recurrence_interval' => array( 'type' => 'integer', 'default' => 1 ),
		'_openagenda_recurrence_until'    => array( 'type' => 'string', 'default' => '' ),
		'_openagenda_all_day'             => array( 'type' => 'string', 'default' => '0' ),
	);

	foreach ( $meta_fields as $meta_key => $schema ) {
		register_post_meta(
			'openagenda_event',
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
				'sanitize_callback' => 'openagenda_sanitize_registered_event_meta',
			)
		);
	}
}
add_action( 'init', 'openagenda_register_event_meta' );

/**
 * Sanitizes registered event metadata.
 *
 * @param mixed  $value    Submitted value.
 * @param string $meta_key Meta key.
 * @return mixed
 */
function openagenda_sanitize_registered_event_meta( $value, $meta_key ) {
	if ( '_openagenda_start_date' === $meta_key || '_openagenda_end_date' === $meta_key || '_openagenda_recurrence_until' === $meta_key ) {
		return openagenda_sanitize_date( $value );
	}

	if ( '_openagenda_start_time' === $meta_key || '_openagenda_end_time' === $meta_key ) {
		return openagenda_sanitize_time( $value );
	}

	if ( '_openagenda_external_url' === $meta_key ) {
		return esc_url_raw( $value );
	}

	if ( '_openagenda_color' === $meta_key ) {
		return sanitize_hex_color( $value ) ? sanitize_hex_color( $value ) : '#ffffff';
	}

	if ( '_openagenda_recurrence' === $meta_key ) {
		return openagenda_sanitize_recurrence( $value );
	}

	if ( '_openagenda_recurrence_interval' === $meta_key ) {
		return max( 1, min( 99, absint( $value ) ) );
	}

	if ( '_openagenda_all_day' === $meta_key ) {
		return ! empty( $value ) && '0' !== (string) $value ? '1' : '0';
	}

	return sanitize_text_field( $value );
}

/**
 * Flush rewrites after activation so event URLs work immediately.
 */
function openagenda_activate() {
	openagenda_register_content_types();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'openagenda_activate' );

/**
 * Flush rewrites after deactivation.
 */
function openagenda_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'openagenda_deactivate' );

/**
 * Adds event details meta box.
 */
function openagenda_add_event_meta_box() {
	add_meta_box(
		'openagenda_event_details',
		__( 'Event Details', 'openagenda-events-calendar' ),
		'openagenda_render_event_meta_box',
		'openagenda_event',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'openagenda_add_event_meta_box' );

/**
 * Renders event metadata fields in the editor.
 *
 * @param WP_Post $post Current event post.
 */
function openagenda_render_event_meta_box( $post ) {
	wp_nonce_field( 'openagenda_save_event_meta', 'openagenda_event_meta_nonce' );

	$start_date = get_post_meta( $post->ID, '_openagenda_start_date', true );
	$start_time = get_post_meta( $post->ID, '_openagenda_start_time', true );
	$end_date   = get_post_meta( $post->ID, '_openagenda_end_date', true );
	$end_time   = get_post_meta( $post->ID, '_openagenda_end_time', true );
	$location   = get_post_meta( $post->ID, '_openagenda_location', true );
	$url        = get_post_meta( $post->ID, '_openagenda_external_url', true );
	$all_day    = get_post_meta( $post->ID, '_openagenda_all_day', true );
	$color      = get_post_meta( $post->ID, '_openagenda_color', true );
	$recurrence = get_post_meta( $post->ID, '_openagenda_recurrence', true );
	$interval   = get_post_meta( $post->ID, '_openagenda_recurrence_interval', true );
	$until      = get_post_meta( $post->ID, '_openagenda_recurrence_until', true );

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
	<div class="openagenda-admin-grid">
		<p>
			<label for="openagenda_start_date"><strong><?php esc_html_e( 'Start date', 'openagenda-events-calendar' ); ?></strong></label>
			<input required type="date" id="openagenda_start_date" name="openagenda_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
		</p>
		<p>
			<label for="openagenda_start_time"><strong><?php esc_html_e( 'Start time', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="time" id="openagenda_start_time" name="openagenda_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
		</p>
		<p>
			<label for="openagenda_end_date"><strong><?php esc_html_e( 'End date', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="date" id="openagenda_end_date" name="openagenda_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
		</p>
		<p>
			<label for="openagenda_end_time"><strong><?php esc_html_e( 'End time', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="time" id="openagenda_end_time" name="openagenda_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
		</p>
		<p>
			<label for="openagenda_location"><strong><?php esc_html_e( 'Location', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="text" id="openagenda_location" name="openagenda_location" value="<?php echo esc_attr( $location ); ?>" class="widefat" />
		</p>
		<p>
			<label for="openagenda_external_url"><strong><?php esc_html_e( 'External URL', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="url" id="openagenda_external_url" name="openagenda_external_url" value="<?php echo esc_url( $url ); ?>" class="widefat" />
		</p>
		<p>
			<label for="openagenda_color"><strong><?php esc_html_e( 'Calendar color', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="color" id="openagenda_color" name="openagenda_color" value="<?php echo esc_attr( $color ); ?>" />
		</p>
		<p>
			<label for="openagenda_recurrence"><strong><?php esc_html_e( 'Repeat', 'openagenda-events-calendar' ); ?></strong></label>
			<select id="openagenda_recurrence" name="openagenda_recurrence" class="widefat">
				<option value="none" <?php selected( $recurrence, 'none' ); ?>><?php esc_html_e( 'Does not repeat', 'openagenda-events-calendar' ); ?></option>
				<option value="daily" <?php selected( $recurrence, 'daily' ); ?>><?php esc_html_e( 'Daily', 'openagenda-events-calendar' ); ?></option>
				<option value="weekly" <?php selected( $recurrence, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'openagenda-events-calendar' ); ?></option>
				<option value="monthly" <?php selected( $recurrence, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'openagenda-events-calendar' ); ?></option>
				<option value="yearly" <?php selected( $recurrence, 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'openagenda-events-calendar' ); ?></option>
			</select>
		</p>
		<p>
			<label for="openagenda_recurrence_interval"><strong><?php esc_html_e( 'Repeat every', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="number" id="openagenda_recurrence_interval" name="openagenda_recurrence_interval" value="<?php echo esc_attr( $interval ); ?>" min="1" max="99" />
		</p>
		<p>
			<label for="openagenda_recurrence_until"><strong><?php esc_html_e( 'Repeat until', 'openagenda-events-calendar' ); ?></strong></label>
			<input type="date" id="openagenda_recurrence_until" name="openagenda_recurrence_until" value="<?php echo esc_attr( $until ); ?>" />
		</p>
		<p class="openagenda-admin-checkbox">
			<label>
				<input type="checkbox" name="openagenda_all_day" value="1" <?php checked( $all_day, '1' ); ?> />
				<?php esc_html_e( 'All-day event', 'openagenda-events-calendar' ); ?>
			</label>
		</p>
	</div>
	<?php
}

/**
 * Saves event metadata.
 *
 * @param int $post_id Current post ID.
 */
function openagenda_save_event_meta( $post_id ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	if ( ! isset( $_POST['openagenda_event_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openagenda_event_meta_nonce'] ) ), 'openagenda_save_event_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['openagenda_start_date'] ) && ! isset( $_POST['openagenda_location'] ) ) {
		return;
	}

	$fields = array(
		'_openagenda_start_date'       => isset( $_POST['openagenda_start_date'] ) ? openagenda_sanitize_date( sanitize_text_field( wp_unslash( $_POST['openagenda_start_date'] ) ) ) : '',
		'_openagenda_start_time'       => isset( $_POST['openagenda_start_time'] ) ? openagenda_sanitize_time( sanitize_text_field( wp_unslash( $_POST['openagenda_start_time'] ) ) ) : '',
		'_openagenda_end_date'         => isset( $_POST['openagenda_end_date'] ) ? openagenda_sanitize_date( sanitize_text_field( wp_unslash( $_POST['openagenda_end_date'] ) ) ) : '',
		'_openagenda_end_time'         => isset( $_POST['openagenda_end_time'] ) ? openagenda_sanitize_time( sanitize_text_field( wp_unslash( $_POST['openagenda_end_time'] ) ) ) : '',
		'_openagenda_location'         => isset( $_POST['openagenda_location'] ) ? sanitize_text_field( wp_unslash( $_POST['openagenda_location'] ) ) : '',
		'_openagenda_external_url'     => isset( $_POST['openagenda_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['openagenda_external_url'] ) ) : '',
		'_openagenda_color'            => isset( $_POST['openagenda_color'] ) ? sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['openagenda_color'] ) ) ) : '',
		'_openagenda_recurrence'       => isset( $_POST['openagenda_recurrence'] ) ? openagenda_sanitize_recurrence( sanitize_key( wp_unslash( $_POST['openagenda_recurrence'] ) ) ) : '',
		'_openagenda_recurrence_until' => isset( $_POST['openagenda_recurrence_until'] ) ? openagenda_sanitize_date( sanitize_text_field( wp_unslash( $_POST['openagenda_recurrence_until'] ) ) ) : '',
	);

	foreach ( $fields as $meta_key => $value ) {
		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	$all_day = isset( $_POST['openagenda_all_day'] ) ? '1' : '0';
	update_post_meta( $post_id, '_openagenda_all_day', $all_day );

	$interval = isset( $_POST['openagenda_recurrence_interval'] ) ? absint( wp_unslash( $_POST['openagenda_recurrence_interval'] ) ) : 1;
	update_post_meta( $post_id, '_openagenda_recurrence_interval', max( 1, min( 99, $interval ) ) );
}
add_action( 'save_post_openagenda_event', 'openagenda_save_event_meta' );

/**
 * Sanitizes a date field.
 *
 * @param string $date Submitted date.
 * @return string
 */
function openagenda_sanitize_date( $date ) {
	$date = sanitize_text_field( $date );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
}

/**
 * Sanitizes a time field.
 *
 * @param string $time Submitted time.
 * @return string
 */
function openagenda_sanitize_time( $time ) {
	$time = sanitize_text_field( $time );
	return preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time : '';
}

/**
 * Sanitizes recurrence frequency.
 *
 * @param string $recurrence Submitted recurrence.
 * @return string
 */
function openagenda_sanitize_recurrence( $recurrence ) {
	$recurrence = sanitize_key( $recurrence );
	return in_array( $recurrence, array( 'none', 'daily', 'weekly', 'monthly', 'yearly' ), true ) ? $recurrence : 'none';
}

/**
 * Adds event date columns to the event list table.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function openagenda_event_columns( $columns ) {
	$insert = array(
		'openagenda_start'    => __( 'Starts', 'openagenda-events-calendar' ),
		'openagenda_location' => __( 'Location', 'openagenda-events-calendar' ),
	);

	return array_slice( $columns, 0, 2, true ) + $insert + array_slice( $columns, 2, null, true );
}
add_filter( 'manage_openagenda_event_posts_columns', 'openagenda_event_columns' );

/**
 * Prints event list table column values.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function openagenda_event_column_content( $column, $post_id ) {
	if ( 'openagenda_start' === $column ) {
		echo esc_html( openagenda_format_event_datetime( $post_id ) );
		openagenda_print_quick_edit_event_data( $post_id );
	}

	if ( 'openagenda_location' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_openagenda_location', true ) );
	}
}
add_action( 'manage_openagenda_event_posts_custom_column', 'openagenda_event_column_content', 10, 2 );

/**
 * Prints hidden event metadata used to populate Quick Edit.
 *
 * @param int $post_id Event post ID.
 */
function openagenda_print_quick_edit_event_data( $post_id ) {
	$data = array(
		'startDate' => get_post_meta( $post_id, '_openagenda_start_date', true ),
		'startTime' => get_post_meta( $post_id, '_openagenda_start_time', true ),
		'endDate'   => get_post_meta( $post_id, '_openagenda_end_date', true ),
		'endTime'   => get_post_meta( $post_id, '_openagenda_end_time', true ),
		'location'  => get_post_meta( $post_id, '_openagenda_location', true ),
		'allDay'    => get_post_meta( $post_id, '_openagenda_all_day', true ),
	);

	printf(
		'<span class="openagenda-quick-edit-data" hidden data-start-date="%1$s" data-start-time="%2$s" data-end-date="%3$s" data-end-time="%4$s" data-location="%5$s" data-all-day="%6$s"></span>',
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
function openagenda_quick_edit_event_fields( $column_name, $post_type ) {
	if ( 'openagenda_event' !== $post_type || 'openagenda_start' !== $column_name ) {
		return;
	}

	wp_nonce_field( 'openagenda_save_event_meta', 'openagenda_event_meta_nonce' );
	?>
	<fieldset class="inline-edit-col-left openagenda-quick-edit-fields">
		<div class="inline-edit-col">
			<span class="title"><?php esc_html_e( 'Event date, time and place', 'openagenda-events-calendar' ); ?></span>
			<label>
				<span class="title"><?php esc_html_e( 'Start date', 'openagenda-events-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="date" name="openagenda_start_date" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'Start time', 'openagenda-events-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="time" name="openagenda_start_time" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'End date', 'openagenda-events-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="date" name="openagenda_end_date" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'End time', 'openagenda-events-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="time" name="openagenda_end_time" value="" />
				</span>
			</label>
			<label>
				<span class="title"><?php esc_html_e( 'Location', 'openagenda-events-calendar' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="openagenda_location" value="" />
				</span>
			</label>
			<label class="inline-edit-group">
				<span class="title"><?php esc_html_e( 'All-day event', 'openagenda-events-calendar' ); ?></span>
				<input type="checkbox" name="openagenda_all_day" value="1" />
			</label>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'openagenda_quick_edit_event_fields', 10, 2 );

/**
 * Makes start column sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function openagenda_sortable_event_columns( $columns ) {
	$columns['openagenda_start'] = 'openagenda_start';
	return $columns;
}
add_filter( 'manage_edit-openagenda_event_sortable_columns', 'openagenda_sortable_event_columns' );

/**
 * Applies active/archive filtering and event start date sorting in admin.
 *
 * @param WP_Query $query Query instance.
 */
function openagenda_admin_event_ordering( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'openagenda_event' !== $query->get( 'post_type' ) ) {
		return;
	}

	$post_status = $query->get( 'post_status' );

	if ( in_array( $post_status, array( 'trash', 'auto-draft' ), true ) ) {
		return;
	}

	$time_filter = openagenda_get_admin_event_time_filter();

	if ( 'archive' === $time_filter ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Event date filtering relies on registered post meta.
		$query->set( 'meta_query', openagenda_get_admin_event_archive_meta_query() );
	} else {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Event date filtering relies on registered post meta.
		$query->set( 'meta_query', openagenda_get_admin_event_active_meta_query() );
	}

	if ( 'openagenda_start' === $query->get( 'orderby' ) || ! $query->get( 'orderby' ) ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin event sorting uses the registered start-date meta field.
		$query->set( 'meta_key', '_openagenda_start_date' );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin event sorting uses the registered start-date meta field.
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'archive' === $time_filter ? 'DESC' : 'ASC' );
	}
}
add_action( 'pre_get_posts', 'openagenda_admin_event_ordering' );

/**
 * Returns the selected admin event time filter.
 *
 * @return string
 */
function openagenda_get_admin_event_time_filter() {
	if ( ! isset( $_GET['openagenda_event_time_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['openagenda_event_time_nonce'] ) ), 'openagenda_filter_events' ) ) {
		return 'active';
	}

	$filter = isset( $_GET['openagenda_event_time'] ) ? sanitize_key( wp_unslash( $_GET['openagenda_event_time'] ) ) : '';

	return 'archive' === $filter ? 'archive' : 'active';
}

/**
 * Adds active/archive views to the admin event table.
 *
 * @param array $views Existing view links.
 * @return array
 */
function openagenda_admin_event_views( $views ) {
	$current = openagenda_get_admin_event_time_filter();

	$active_url  = remove_query_arg( 'openagenda_event_time', admin_url( 'edit.php?post_type=openagenda_event' ) );
	$archive_url = wp_nonce_url( add_query_arg( 'openagenda_event_time', 'archive', admin_url( 'edit.php?post_type=openagenda_event' ) ), 'openagenda_filter_events', 'openagenda_event_time_nonce' );

	$views['all'] = sprintf(
		'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
		esc_url( $active_url ),
		'active' === $current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Active', 'openagenda-events-calendar' ),
		absint( openagenda_count_admin_events_by_time_filter( 'active' ) )
	);

	$views['openagenda_event_archive'] = sprintf(
		'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
		esc_url( $archive_url ),
		'archive' === $current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Archive', 'openagenda-events-calendar' ),
		absint( openagenda_count_admin_events_by_time_filter( 'archive' ) )
	);

	return $views;
}
add_filter( 'views_edit-openagenda_event', 'openagenda_admin_event_views' );

/**
 * Counts events for an admin time filter.
 *
 * @param string $time_filter Time filter.
 * @return int
 */
function openagenda_count_admin_events_by_time_filter( $time_filter ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'openagenda_event',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin event counts use the same date filters as the event list.
			'meta_query'     => 'archive' === $time_filter ? openagenda_get_admin_event_archive_meta_query() : openagenda_get_admin_event_active_meta_query(),
		)
	);

	return (int) $query->found_posts;
}

/**
 * Returns meta query for active events in the admin list.
 *
 * @return array
 */
function openagenda_get_admin_event_active_meta_query() {
	$today = current_time( 'Y-m-d' );

	return array(
		'relation' => 'AND',
		array(
			'key'     => '_openagenda_start_date',
			'compare' => 'EXISTS',
		),
		array(
			'relation' => 'OR',
			array(
				'relation' => 'AND',
				openagenda_get_admin_event_non_recurring_meta_query(),
				openagenda_get_admin_event_active_date_meta_query( $today ),
			),
			array(
				'relation' => 'AND',
				openagenda_get_admin_event_recurring_meta_query(),
				openagenda_get_admin_event_active_recurrence_meta_query( $today ),
			),
		),
	);
}

/**
 * Returns meta query for archived events in the admin list.
 *
 * @return array
 */
function openagenda_get_admin_event_archive_meta_query() {
	$today = current_time( 'Y-m-d' );

	return array(
		'relation' => 'AND',
		array(
			'key'     => '_openagenda_start_date',
			'compare' => 'EXISTS',
		),
		array(
			'relation' => 'OR',
			array(
				'relation' => 'AND',
				openagenda_get_admin_event_non_recurring_meta_query(),
				openagenda_get_admin_event_past_date_meta_query( $today ),
			),
			array(
				'relation' => 'AND',
				openagenda_get_admin_event_recurring_meta_query(),
				openagenda_get_admin_event_past_recurrence_meta_query( $today ),
			),
		),
	);
}

/**
 * Returns meta query for non-recurring events.
 *
 * @return array
 */
function openagenda_get_admin_event_non_recurring_meta_query() {
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_openagenda_recurrence',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_openagenda_recurrence',
			'value'   => '',
			'compare' => '=',
		),
		array(
			'key'     => '_openagenda_recurrence',
			'value'   => 'none',
			'compare' => '=',
		),
	);
}

/**
 * Returns meta query for recurring events.
 *
 * @return array
 */
function openagenda_get_admin_event_recurring_meta_query() {
	return array(
		'key'     => '_openagenda_recurrence',
		'value'   => array( 'daily', 'weekly', 'monthly', 'yearly' ),
		'compare' => 'IN',
	);
}

/**
 * Returns meta query for active non-recurring event dates.
 *
 * @param string $today Current WordPress date.
 * @return array
 */
function openagenda_get_admin_event_active_date_meta_query( $today ) {
	return array(
		'relation' => 'OR',
		array(
			'relation' => 'AND',
			array(
				'key'     => '_openagenda_end_date',
				'value'   => '',
				'compare' => '!=',
			),
			array(
				'key'     => '_openagenda_end_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
		array(
			'relation' => 'AND',
			array(
				'key'     => '_openagenda_end_date',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_openagenda_start_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
		array(
			'relation' => 'AND',
			array(
				'key'     => '_openagenda_end_date',
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => '_openagenda_start_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
	);
}

/**
 * Returns meta query for archived non-recurring event dates.
 *
 * @param string $today Current WordPress date.
 * @return array
 */
function openagenda_get_admin_event_past_date_meta_query( $today ) {
	return array(
		'relation' => 'OR',
		array(
			'relation' => 'AND',
			array(
				'key'     => '_openagenda_end_date',
				'value'   => '',
				'compare' => '!=',
			),
			array(
				'key'     => '_openagenda_end_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			),
		),
		array(
			'relation' => 'AND',
			array(
				'key'     => '_openagenda_end_date',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_openagenda_start_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			),
		),
		array(
			'relation' => 'AND',
			array(
				'key'     => '_openagenda_end_date',
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => '_openagenda_start_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			),
		),
	);
}

/**
 * Returns meta query for active recurring event dates.
 *
 * @param string $today Current WordPress date.
 * @return array
 */
function openagenda_get_admin_event_active_recurrence_meta_query( $today ) {
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_openagenda_recurrence_until',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_openagenda_recurrence_until',
			'value'   => '',
			'compare' => '=',
		),
		array(
			'key'     => '_openagenda_recurrence_until',
			'value'   => $today,
			'compare' => '>=',
			'type'    => 'DATE',
		),
	);
}

/**
 * Returns meta query for archived recurring event dates.
 *
 * @param string $today Current WordPress date.
 * @return array
 */
function openagenda_get_admin_event_past_recurrence_meta_query( $today ) {
	return array(
		'key'     => '_openagenda_recurrence_until',
		'value'   => $today,
		'compare' => '<',
		'type'    => 'DATE',
	);
}

/**
 * Adds a duplicate action to event row actions.
 *
 * @param array   $actions Existing row actions.
 * @param WP_Post $post    Current post.
 * @return array
 */
function openagenda_add_duplicate_event_action( $actions, $post ) {
	if ( 'openagenda_event' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action'  => 'openagenda_duplicate_event',
				'post_id' => $post->ID,
			),
			admin_url( 'admin.php' )
		),
		'openagenda_duplicate_event_' . $post->ID
	);

	$actions['openagenda_duplicate_event'] = sprintf(
		'<a href="%1$s" aria-label="%2$s">%3$s</a>',
		esc_url( $url ),
		esc_attr__( 'Duplicate this event', 'openagenda-events-calendar' ),
		esc_html__( 'Duplicate', 'openagenda-events-calendar' )
	);

	return $actions;
}
add_filter( 'post_row_actions', 'openagenda_add_duplicate_event_action', 10, 2 );

/**
 * Handles event duplication.
 */
function openagenda_duplicate_event() {
	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to duplicate this event.', 'openagenda-events-calendar' ) );
	}

	check_admin_referer( 'openagenda_duplicate_event_' . $post_id );

	$source = get_post( $post_id );
	if ( ! $source || 'openagenda_event' !== $source->post_type ) {
		wp_die( esc_html__( 'Event could not be found.', 'openagenda-events-calendar' ) );
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
				__( '%s copy', 'openagenda-events-calendar' ),
				$source->post_title
			),
			'post_type'             => 'openagenda_event',
			'post_content_filtered' => $source->post_content_filtered,
		),
		true
	);

	if ( is_wp_error( $new_post_id ) ) {
		wp_die( esc_html( $new_post_id->get_error_message() ) );
	}

	openagenda_copy_event_metadata( $post_id, $new_post_id );
	openagenda_copy_event_terms( $post_id, $new_post_id );
	openagenda_copy_event_thumbnail( $post_id, $new_post_id );

	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
	exit;
}
add_action( 'admin_action_openagenda_duplicate_event', 'openagenda_duplicate_event' );

/**
 * Copies event metadata to a duplicated event.
 *
 * @param int $source_id Source event ID.
 * @param int $target_id Target event ID.
 */
function openagenda_copy_event_metadata( $source_id, $target_id ) {
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
function openagenda_copy_event_terms( $source_id, $target_id ) {
	$taxonomies = get_object_taxonomies( 'openagenda_event' );

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
function openagenda_copy_event_thumbnail( $source_id, $target_id ) {
	$thumbnail_id = get_post_thumbnail_id( $source_id );

	if ( $thumbnail_id ) {
		set_post_thumbnail( $target_id, $thumbnail_id );
	}
}

/**
 * Registers scripts, styles, shortcodes, and REST route.
 */
function openagenda_register_frontend_assets() {
	wp_register_style(
		'openagenda-calendar',
		OPENAGENDA_PLUGIN_URL . 'assets/calendar.css',
		array(),
		OPENAGENDA_VERSION
	);

	wp_register_script(
		'openagenda-calendar',
		OPENAGENDA_PLUGIN_URL . 'assets/calendar.js',
		array(),
		OPENAGENDA_VERSION,
		true
	);

	wp_localize_script(
		'openagenda-calendar',
		'openagendaCalendarSettings',
		array(
			'restUrl'      => esc_url_raw( rest_url( 'openagenda-events-calendar/v1/events' ) ),
			'locale'       => str_replace( '_', '-', get_locale() ),
			'firstWeekday' => absint( get_option( 'start_of_week', 1 ) ),
			'labels'       => array(
				'next'      => __( 'Next month', 'openagenda-events-calendar' ),
				'previous'  => __( 'Previous month', 'openagenda-events-calendar' ),
				'today'     => __( 'Today', 'openagenda-events-calendar' ),
				'loading'   => __( 'Loading events...', 'openagenda-events-calendar' ),
				'noEvents'  => __( 'No events this month.', 'openagenda-events-calendar' ),
				'viewEvent' => __( 'View event', 'openagenda-events-calendar' ),
			),
		)
	);
}
add_action( 'init', 'openagenda_register_frontend_assets' );

/**
 * Registers editor assets and dynamic blocks.
 */
function openagenda_register_blocks() {
	wp_register_script(
		'openagenda-upcoming-events-block',
		OPENAGENDA_PLUGIN_URL . 'assets/upcoming-events-block.js',
		array( 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-server-side-render' ),
		OPENAGENDA_VERSION,
		true
	);

	wp_set_script_translations( 'openagenda-upcoming-events-block', 'openagenda-events-calendar', OPENAGENDA_PLUGIN_DIR . 'languages' );
	openagenda_add_block_editor_locale_data();

	register_block_type(
		'openagenda-events-calendar/upcoming-events',
		array(
			'api_version'     => 2,
			'title'           => __( 'Upcoming Events', 'openagenda-events-calendar' ),
			'description'     => __( 'Shows a styled list of upcoming events.', 'openagenda-events-calendar' ),
			'category'        => 'widgets',
			'icon'            => 'calendar-alt',
			'editor_script'   => 'openagenda-upcoming-events-block',
			'render_callback' => 'openagenda_render_upcoming_events_block',
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
add_action( 'init', 'openagenda_register_blocks' );

/**
 * Registers admin editor assets.
 */
function openagenda_register_admin_assets() {
	wp_register_style(
		'openagenda-admin',
		OPENAGENDA_PLUGIN_URL . 'assets/admin.css',
		array(),
		OPENAGENDA_VERSION
	);

	wp_register_script(
		'openagenda-event-editor',
		OPENAGENDA_PLUGIN_URL . 'assets/event-editor.js',
		array( 'wp-api-fetch', 'wp-components', 'wp-compose', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins' ),
		OPENAGENDA_VERSION,
		true
	);

	wp_set_script_translations( 'openagenda-event-editor', 'openagenda-events-calendar', OPENAGENDA_PLUGIN_DIR . 'languages' );

	wp_register_script(
		'openagenda-shortcode-generator',
		OPENAGENDA_PLUGIN_URL . 'assets/shortcode-generator.js',
		array(),
		OPENAGENDA_VERSION,
		true
	);

	wp_register_script(
		'openagenda-quick-edit',
		OPENAGENDA_PLUGIN_URL . 'assets/quick-edit.js',
		array( 'inline-edit-post' ),
		OPENAGENDA_VERSION,
		true
	);
}
add_action( 'admin_init', 'openagenda_register_admin_assets' );

/**
 * Enqueues shortcode generator assets on the event list screen.
 *
 * @param string $hook Current admin hook.
 */
function openagenda_enqueue_shortcode_generator_assets( $hook ) {
	if ( 'edit.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'openagenda_event' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'openagenda-admin' );
	wp_enqueue_script( 'openagenda-shortcode-generator' );
	wp_enqueue_script( 'openagenda-quick-edit' );
}
add_action( 'admin_enqueue_scripts', 'openagenda_enqueue_shortcode_generator_assets' );

/**
 * Enqueues the event editor sidebar for event posts.
 *
 * @param string $hook Current admin hook.
 */
function openagenda_enqueue_event_editor_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'openagenda_event' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'openagenda-admin' );
	wp_enqueue_script( 'openagenda-event-editor' );
	openagenda_add_event_editor_locale_data();
}
add_action( 'admin_enqueue_scripts', 'openagenda_enqueue_event_editor_assets' );

/**
 * Renders a shortcode generator below the event list table.
 */
function openagenda_render_shortcode_generator( $which ) {
	if ( 'bottom' !== $which ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'edit-openagenda_event' !== $screen->id ) {
		return;
	}

	$topics = get_terms(
		array(
			'taxonomy'   => 'openagenda_event_topic',
			'hide_empty' => false,
		)
	);
	?>
	<div class="openagenda-shortcode-generator postbox" style="margin-top: 20px; max-width: 960px;">
		<div class="postbox-header">
			<h2><?php esc_html_e( 'Shortcode Generator', 'openagenda-events-calendar' ); ?></h2>
		</div>
		<div class="inside">
			<p><?php esc_html_e( 'Use these shortcodes in pages, posts, widgets, or template areas to show your events.', 'openagenda-events-calendar' ); ?></p>

			<div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
				<div>
					<h3><?php esc_html_e( 'Upcoming events list', 'openagenda-events-calendar' ); ?></h3>
					<p>
						<label for="openagenda_shortcode_category"><strong><?php esc_html_e( 'Event category slug', 'openagenda-events-calendar' ); ?></strong></label>
						<select id="openagenda_shortcode_category" class="widefat" data-openagenda-shortcode-field="category">
							<option value=""><?php esc_html_e( 'All event topics', 'openagenda-events-calendar' ); ?></option>
							<?php if ( ! is_wp_error( $topics ) ) : ?>
								<?php foreach ( $topics as $topic ) : ?>
									<option value="<?php echo esc_attr( $topic->slug ); ?>"><?php echo esc_html( $topic->name . ' (' . $topic->slug . ')' ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</p>
					<p>
						<label for="openagenda_shortcode_max_events"><strong><?php esc_html_e( 'Maximum events', 'openagenda-events-calendar' ); ?></strong></label>
						<input id="openagenda_shortcode_max_events" type="number" min="1" max="50" value="6" class="small-text" data-openagenda-shortcode-field="max-events" />
					</p>
					<p>
						<label for="openagenda_shortcode_style"><strong><?php esc_html_e( 'Style', 'openagenda-events-calendar' ); ?></strong></label>
						<select id="openagenda_shortcode_style" class="widefat" data-openagenda-shortcode-field="style">
							<option value="list"><?php esc_html_e( 'List', 'openagenda-events-calendar' ); ?></option>
							<option value="minimal-list"><?php esc_html_e( 'Minimal list', 'openagenda-events-calendar' ); ?></option>
							<option value="calendar"><?php esc_html_e( 'Calendar', 'openagenda-events-calendar' ); ?></option>
						</select>
					</p>
					<p>
						<label><input type="checkbox" checked data-openagenda-shortcode-field="show-place" /> <?php esc_html_e( 'Show place', 'openagenda-events-calendar' ); ?></label><br />
						<label><input type="checkbox" checked data-openagenda-shortcode-field="show-time" /> <?php esc_html_e( 'Show time', 'openagenda-events-calendar' ); ?></label>
					</p>
					<p>
						<label for="openagenda_shortcode_events"><strong><?php esc_html_e( 'Generated shortcode', 'openagenda-events-calendar' ); ?></strong></label>
						<input id="openagenda_shortcode_events" class="widefat code" type="text" readonly data-openagenda-shortcode-output="events" value='[openagenda_events max-events="6" show-place="true" show-time="true" style="list"]' />
					</p>
					<p><button type="button" class="button" data-openagenda-copy-shortcode="openagenda_shortcode_events"><?php esc_html_e( 'Copy shortcode', 'openagenda-events-calendar' ); ?></button></p>
				</div>

				<div>
					<h3><?php esc_html_e( 'Full month calendar', 'openagenda-events-calendar' ); ?></h3>
					<p>
						<label for="openagenda_shortcode_calendar_topic"><strong><?php esc_html_e( 'Event category slug', 'openagenda-events-calendar' ); ?></strong></label>
						<select id="openagenda_shortcode_calendar_topic" class="widefat" data-openagenda-calendar-field="topic">
							<option value=""><?php esc_html_e( 'All event topics', 'openagenda-events-calendar' ); ?></option>
							<?php if ( ! is_wp_error( $topics ) ) : ?>
								<?php foreach ( $topics as $topic ) : ?>
									<option value="<?php echo esc_attr( $topic->slug ); ?>"><?php echo esc_html( $topic->name . ' (' . $topic->slug . ')' ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</p>
					<p>
						<label><input type="checkbox" checked data-openagenda-calendar-field="show_legend" /> <?php esc_html_e( 'Show legend', 'openagenda-events-calendar' ); ?></label>
					</p>
					<p>
						<label for="openagenda_shortcode_calendar"><strong><?php esc_html_e( 'Generated shortcode', 'openagenda-events-calendar' ); ?></strong></label>
						<input id="openagenda_shortcode_calendar" class="widefat code" type="text" readonly data-openagenda-shortcode-output="calendar" value='[openagenda_events_calendar show_legend="true"]' />
					</p>
					<p><button type="button" class="button" data-openagenda-copy-shortcode="openagenda_shortcode_calendar"><?php esc_html_e( 'Copy shortcode', 'openagenda-events-calendar' ); ?></button></p>
				</div>
			</div>

			<p style="margin-top: 16px;">
				<strong><?php esc_html_e( 'Quick examples', 'openagenda-events-calendar' ); ?></strong><br />
				<code>[openagenda_events max-events="5" style="minimal-list"]</code><br />
				<code>[openagenda_events_calendar]</code>
			</p>
		</div>
	</div>
	<?php
}
add_action( 'manage_posts_extra_tablenav', 'openagenda_render_shortcode_generator' );

/**
 * Enqueues event styles on single event pages.
 */
function openagenda_enqueue_single_event_assets() {
	if ( is_singular( 'openagenda_event' ) ) {
		wp_enqueue_style( 'openagenda-calendar' );
	}
}
add_action( 'wp_enqueue_scripts', 'openagenda_enqueue_single_event_assets' );

/**
 * Adds lightweight JavaScript translations for the event editor sidebar.
 */
function openagenda_add_event_editor_locale_data() {
	$locale = determine_locale();

	if ( 0 !== strpos( $locale, 'de_' ) && 'de' !== $locale ) {
		return;
	}

	$locale_data = array(
		''                    => array(
			'domain' => 'openagenda-events-calendar',
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
		'openagenda-event-editor',
		'wp.i18n.setLocaleData(' . wp_json_encode( $locale_data ) . ', "openagenda-events-calendar");',
		'before'
	);
}

/**
 * Adds lightweight JavaScript translations for the editor block labels.
 */
function openagenda_add_block_editor_locale_data() {
	$locale = determine_locale();

	if ( 0 !== strpos( $locale, 'de_' ) && 'de' !== $locale ) {
		return;
	}

	$locale_data = array(
		''                              => array(
			'domain' => 'openagenda-events-calendar',
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
		'openagenda-upcoming-events-block',
		'wp.i18n.setLocaleData(' . wp_json_encode( $locale_data ) . ', "openagenda-events-calendar");',
		'before'
	);
}

/**
 * Enqueues frontend assets.
 */
function openagenda_enqueue_calendar_assets() {
	wp_enqueue_style( 'openagenda-calendar' );
	wp_enqueue_script( 'openagenda-calendar' );
}

/**
 * Renders interactive month calendar shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function openagenda_calendar_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'topic'       => '',
			'height'      => 'auto',
			'show_legend' => 'true',
		),
		$atts,
		'openagenda_events_calendar'
	);

	openagenda_enqueue_calendar_assets();

	$calendar_id = wp_unique_id( 'openagenda-calendar-' );
	$topic       = sanitize_title( $atts['topic'] );
	$height      = 'auto' === $atts['height'] ? 'auto' : max( 320, absint( $atts['height'] ) ) . 'px';
	$show_legend = filter_var( $atts['show_legend'], FILTER_VALIDATE_BOOLEAN );

	ob_start();
	?>
	<div
		id="<?php echo esc_attr( $calendar_id ); ?>"
		class="openagenda-calendar"
		data-topic="<?php echo esc_attr( $topic ); ?>"
		data-show-legend="<?php echo esc_attr( $show_legend ? 'true' : 'false' ); ?>"
		style="--openagenda-calendar-min-height: <?php echo esc_attr( $height ); ?>;"
	>
		<div class="openagenda-calendar__toolbar">
			<button type="button" class="openagenda-calendar__button" data-openagenda-action="previous" aria-label="<?php esc_attr_e( 'Previous month', 'openagenda-events-calendar' ); ?>">
				<span aria-hidden="true">&lsaquo;</span>
			</button>
			<h2 class="openagenda-calendar__title" data-openagenda-title></h2>
			<div class="openagenda-calendar__actions">
				<button type="button" class="openagenda-calendar__button openagenda-calendar__button--text" data-openagenda-action="today"><?php esc_html_e( 'Today', 'openagenda-events-calendar' ); ?></button>
				<button type="button" class="openagenda-calendar__button" data-openagenda-action="next" aria-label="<?php esc_attr_e( 'Next month', 'openagenda-events-calendar' ); ?>">
					<span aria-hidden="true">&rsaquo;</span>
				</button>
			</div>
		</div>
		<div class="openagenda-calendar__status" data-openagenda-status role="status"><?php esc_html_e( 'Loading events...', 'openagenda-events-calendar' ); ?></div>
		<div class="openagenda-calendar__weekdays" data-openagenda-weekdays></div>
		<div class="openagenda-calendar__grid" data-openagenda-grid></div>
		<div class="openagenda-calendar__legend" data-openagenda-legend hidden></div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'openagenda_events_calendar', 'openagenda_calendar_shortcode' );

/**
 * Renders upcoming events shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function openagenda_upcoming_events_shortcode( $atts ) {
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
		'openagenda_events'
	);

	return openagenda_render_upcoming_events(
		array(
			'category'   => openagenda_first_filled_value( array( $atts['category'], $atts['topic'] ) ),
			'max_events' => openagenda_first_filled_value( array( $atts['max-events'], $atts['max_events'], $atts['limit'] ) ),
			'show_place' => openagenda_first_filled_value( array( $atts['show-place'], $atts['show_place'] ), 'true' ),
			'show_time'  => openagenda_first_filled_value( array( $atts['show-time'], $atts['show_time'] ), 'true' ),
			'style'      => $atts['style'],
		)
	);
}
add_shortcode( 'openagenda_events', 'openagenda_upcoming_events_shortcode' );

/**
 * Prepends event details to single event content.
 *
 * @param string $content Post content.
 * @return string
 */
function openagenda_add_single_event_details_to_content( $content ) {
	static $rendering = false;

	if ( ! is_singular( 'openagenda_event' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( $rendering ) {
		return $content;
	}

	$rendering = true;
	$details = openagenda_render_single_event_details( get_the_ID() );
	$next    = openagenda_render_single_next_events();
	$rendering = false;

	if ( empty( $details ) && empty( $next ) ) {
		return $content;
	}

	return $details . $content . $next;
}
add_filter( 'the_content', 'openagenda_add_single_event_details_to_content', 8 );

/**
 * Renders a compact upcoming-events section below single event content.
 *
 * @return string
 */
function openagenda_render_single_next_events() {
	$list = openagenda_render_upcoming_events(
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
	<section class="openagenda-single-next-events" aria-labelledby="openagenda-single-next-events-title">
		<h2 id="openagenda-single-next-events-title" class="openagenda-single-next-events__title"><?php esc_html_e( 'Next Events', 'openagenda-events-calendar' ); ?></h2>
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
function openagenda_render_single_event_details( $post_id ) {
	$start_date = get_post_meta( $post_id, '_openagenda_start_date', true );
	$start_time = get_post_meta( $post_id, '_openagenda_start_time', true );
	$end_date   = get_post_meta( $post_id, '_openagenda_end_date', true );
	$end_time   = get_post_meta( $post_id, '_openagenda_end_time', true );
	$all_day    = '1' === get_post_meta( $post_id, '_openagenda_all_day', true );
	$location   = get_post_meta( $post_id, '_openagenda_location', true );
	$external   = get_post_meta( $post_id, '_openagenda_external_url', true );

	if ( empty( $start_date ) && empty( $location ) && empty( $external ) ) {
		return '';
	}

	$details = array();

	if ( ! empty( $start_date ) ) {
		$details[] = array(
			'label' => __( 'Date', 'openagenda-events-calendar' ),
			'value' => openagenda_format_event_date_range_value( $start_date, $end_date ),
		);
	}

	if ( ! $all_day && ! empty( $start_time ) ) {
		$details[] = array(
			'label' => __( 'Time', 'openagenda-events-calendar' ),
			'value' => openagenda_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		);
	}

	if ( ! empty( $location ) ) {
		$details[] = array(
			'label' => __( 'Location', 'openagenda-events-calendar' ),
			'value' => $location,
		);
	}

	ob_start();
	?>
	<div class="openagenda-single-event">
		<dl class="openagenda-single-event__details">
			<?php foreach ( $details as $detail ) : ?>
				<div class="openagenda-single-event__row">
					<dt><?php echo esc_html( $detail['label'] ); ?></dt>
					<dd><?php echo esc_html( $detail['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
			<?php if ( ! empty( $external ) ) : ?>
				<div class="openagenda-single-event__row">
					<dt><?php esc_html_e( 'External URL', 'openagenda-events-calendar' ); ?></dt>
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
function openagenda_get_recurrence_label( $post_id ) {
	$recurrence = openagenda_sanitize_recurrence( get_post_meta( $post_id, '_openagenda_recurrence', true ) );
	$interval   = max( 1, absint( get_post_meta( $post_id, '_openagenda_recurrence_interval', true ) ) );
	$until      = get_post_meta( $post_id, '_openagenda_recurrence_until', true );
	$labels     = array(
		'daily'   => __( 'Daily', 'openagenda-events-calendar' ),
		'weekly'  => __( 'Weekly', 'openagenda-events-calendar' ),
		'monthly' => __( 'Monthly', 'openagenda-events-calendar' ),
		'yearly'  => __( 'Yearly', 'openagenda-events-calendar' ),
	);

	$label = isset( $labels[ $recurrence ] ) ? $labels[ $recurrence ] : __( 'Does not repeat', 'openagenda-events-calendar' );

	if ( $interval > 1 ) {
		$label = sprintf(
			/* translators: 1: Recurrence frequency, 2: Interval number. */
			__( '%1$s, every %2$d intervals', 'openagenda-events-calendar' ),
			$label,
			$interval
		);
	}

	if ( ! empty( $until ) ) {
		$label .= ' ' . sprintf(
			/* translators: %s: End date. */
			__( 'until %s', 'openagenda-events-calendar' ),
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
function openagenda_render_upcoming_events_block( $attributes ) {
	return openagenda_render_upcoming_events(
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
function openagenda_render_upcoming_events( $args = array() ) {
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

	wp_enqueue_style( 'openagenda-calendar' );

	$category   = sanitize_title( $args['category'] );
	$max_events = max( 1, min( 50, absint( $args['max_events'] ) ) );
	$show_place = is_bool( $args['show_place'] ) ? $args['show_place'] : filter_var( $args['show_place'], FILTER_VALIDATE_BOOLEAN );
	$show_time  = is_bool( $args['show_time'] ) ? $args['show_time'] : filter_var( $args['show_time'], FILTER_VALIDATE_BOOLEAN );
	$style      = openagenda_normalize_upcoming_style( $args['style'] );

	$events = openagenda_get_events(
		array(
			'from'  => current_time( 'Y-m-d' ),
			'limit' => $max_events,
			'topic' => $category,
		)
	);

	$classes = array(
		'openagenda-upcoming',
		'openagenda-upcoming--' . $style,
	);

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<?php if ( empty( $events ) ) : ?>
			<p class="openagenda-upcoming__empty"><?php esc_html_e( 'Currently there are no upcoming events.', 'openagenda-events-calendar' ); ?></p>
		<?php else : ?>
			<ul class="openagenda-upcoming__list">
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
					<li class="openagenda-upcoming__item<?php echo ! empty( $event['multiDay'] ) ? ' openagenda-upcoming__item--multi-day' : ''; ?>" style="--openagenda-event-color: <?php echo esc_attr( $event['color'] ); ?>;">
						<div class="openagenda-upcoming__content">
							<span class="openagenda-upcoming__date" aria-hidden="true">
								<span class="openagenda-upcoming__day"><?php echo esc_html( $event['dayLabel'] ); ?></span>
								<span class="openagenda-upcoming__month"><?php echo esc_html( $event['monthLabel'] ); ?></span>
								<span class="openagenda-upcoming__short-date">
									<span class="openagenda-upcoming__short-date-start"><?php echo esc_html( $event['startShortDateLabel'] ); ?></span>
									<?php if ( ! empty( $event['multiDay'] ) ) : ?>
										<span class="openagenda-upcoming__short-date-end"><?php echo esc_html( $event['endShortDateLabel'] ); ?></span>
									<?php endif; ?>
								</span>
							</span>
							<span class="openagenda-upcoming__body">
								<span class="openagenda-upcoming__headline">
									<strong class="openagenda-upcoming__inline-date"><?php echo esc_html( $event['shortDateLabel'] ); ?></strong>
									<a class="openagenda-upcoming__title" href="<?php echo esc_url( $event['url'] ); ?>"><?php echo esc_html( $event['title'] ); ?></a>
								</span>
								<span class="openagenda-upcoming__compact-meta">
									<?php foreach ( $compact_meta as $meta_index => $meta_value ) : ?>
										<span class="openagenda-upcoming__compact-meta-item openagenda-upcoming__compact-meta-item--<?php echo esc_attr( $meta_index ); ?>"><?php echo esc_html( $meta_value ); ?></span>
									<?php endforeach; ?>
								</span>
								<span class="openagenda-upcoming__meta">
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
function openagenda_first_filled_value( $values, $fallback = '' ) {
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
function openagenda_normalize_upcoming_style( $style ) {
	$style = sanitize_key( $style );

	return in_array( $style, array( 'list', 'minimal-list', 'calendar' ), true ) ? $style : 'list';
}

/**
 * Registers REST API route.
 */
function openagenda_register_rest_routes() {
	register_rest_route(
		'openagenda-events-calendar/v1',
		'/events',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'openagenda_rest_events',
			'permission_callback' => '__return_true',
			'args'                => array(
				'from'  => array(
					'sanitize_callback' => 'openagenda_sanitize_date',
				),
				'to'    => array(
					'sanitize_callback' => 'openagenda_sanitize_date',
				),
				'topic' => array(
					'sanitize_callback' => 'sanitize_title',
				),
			),
		)
	);

	register_rest_route(
		'openagenda-events-calendar/v1',
		'/event-meta/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'openagenda_rest_save_event_meta',
			'permission_callback' => 'openagenda_rest_can_save_event_meta',
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
add_action( 'rest_api_init', 'openagenda_register_rest_routes' );

/**
 * Checks whether event metadata can be saved through REST.
 *
 * @param WP_REST_Request $request Request object.
 * @return bool
 */
function openagenda_rest_can_save_event_meta( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'id' ) );
	$post    = get_post( $post_id );

	return $post && 'openagenda_event' === $post->post_type && current_user_can( 'edit_post', $post_id );
}

/**
 * Saves event metadata through a dedicated REST endpoint.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function openagenda_rest_save_event_meta( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'id' ) );
	$meta    = $request->get_param( 'meta' );

	if ( ! is_array( $meta ) ) {
		$meta = array();
	}

	$allowed = openagenda_event_meta_keys();

	foreach ( $allowed as $meta_key ) {
		if ( ! array_key_exists( $meta_key, $meta ) ) {
			continue;
		}

		$value = openagenda_sanitize_registered_event_meta( $meta[ $meta_key ], $meta_key );

		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'meta'    => openagenda_get_event_meta_for_response( $post_id ),
		)
	);
}

/**
 * Returns event meta keys managed by this plugin.
 *
 * @return array
 */
function openagenda_event_meta_keys() {
	return array(
		'_openagenda_start_date',
		'_openagenda_start_time',
		'_openagenda_end_date',
		'_openagenda_end_time',
		'_openagenda_location',
		'_openagenda_external_url',
		'_openagenda_color',
		'_openagenda_recurrence',
		'_openagenda_recurrence_interval',
		'_openagenda_recurrence_until',
		'_openagenda_all_day',
	);
}

/**
 * Returns current event metadata for REST responses.
 *
 * @param int $post_id Event post ID.
 * @return array
 */
function openagenda_get_event_meta_for_response( $post_id ) {
	$response = array();

	foreach ( openagenda_event_meta_keys() as $meta_key ) {
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
function openagenda_rest_events( WP_REST_Request $request ) {
	$events = openagenda_get_events(
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
function openagenda_get_events( $args = array() ) {
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
			'key'     => '_openagenda_start_date',
			'compare' => 'EXISTS',
		),
	);

	if ( ! empty( $args['to'] ) ) {
		$meta_query[] = array(
			'key'     => '_openagenda_start_date',
			'value'   => $args['to'],
			'compare' => '<=',
			'type'    => 'DATE',
		);
	}

	$query_args = array(
		'post_type'      => 'openagenda_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Public event lists are ordered by the registered start-date meta field.
		'meta_key'       => '_openagenda_start_date',
		'orderby'        => array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Public event lists are ordered by the registered start-date meta field.
			'meta_value' => 'ASC',
			'date'       => 'ASC',
		),
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Public event lists filter by the registered start-date meta field.
		'meta_query'     => $meta_query,
	);

	if ( ! empty( $args['topic'] ) ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Topic filtering is an intentional public event-list feature.
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'openagenda_event_topic',
				'field'    => 'slug',
				'terms'    => $args['topic'],
			),
		);
	}

	$query  = new WP_Query( $query_args );
	$events = array();
	$limit  = max( 1, min( 200, absint( $args['limit'] ) ) );

	foreach ( $query->posts as $post ) {
		$events = array_merge( $events, openagenda_expand_event_occurrences( $post, $args['from'], $args['to'] ) );
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
function openagenda_expand_event_occurrences( $post, $from = '', $to = '' ) {
	$start_date = get_post_meta( $post->ID, '_openagenda_start_date', true );
	$end_date   = get_post_meta( $post->ID, '_openagenda_end_date', true );

	if ( empty( $start_date ) ) {
		return array();
	}

	if ( empty( $end_date ) ) {
		$end_date = $start_date;
	}

	$recurrence = openagenda_sanitize_recurrence( get_post_meta( $post->ID, '_openagenda_recurrence', true ) );
	$interval   = max( 1, absint( get_post_meta( $post->ID, '_openagenda_recurrence_interval', true ) ) );
	$until      = get_post_meta( $post->ID, '_openagenda_recurrence_until', true );
	$range_from = ! empty( $from ) ? $from : current_time( 'Y-m-d' );
	$range_to   = ! empty( $to ) ? $to : wp_date( 'Y-m-d', strtotime( $range_from . ' +2 years' ) );
	$duration   = max( 0, openagenda_days_between( $start_date, $end_date ) );

	if ( 'none' === $recurrence ) {
		if ( openagenda_event_range_intersects( $start_date, $end_date, $range_from, $range_to ) ) {
			return array( openagenda_normalize_event( $post, $start_date, $end_date ) );
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

		if ( openagenda_event_range_intersects( $occurrence_start, $occurrence_end, $range_from, $range_to ) ) {
			$occurrences[] = openagenda_normalize_event( $post, $occurrence_start, $occurrence_end );
		}

		$cursor = openagenda_next_recurrence_date( $cursor, $recurrence, $interval );
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
function openagenda_next_recurrence_date( DateTimeImmutable $date, $recurrence, $interval ) {
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
function openagenda_event_range_intersects( $event_start, $event_end, $range_start, $range_end ) {
	return $event_start <= $range_end && $event_end >= $range_start;
}

/**
 * Returns whole-day distance between two dates.
 *
 * @param string $start Start date.
 * @param string $end   End date.
 * @return int
 */
function openagenda_days_between( $start, $end ) {
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
function openagenda_normalize_event( $post, $occurrence_start = '', $occurrence_end = '' ) {
	$start_date = get_post_meta( $post->ID, '_openagenda_start_date', true );
	$start_time = get_post_meta( $post->ID, '_openagenda_start_time', true );
	$end_date   = get_post_meta( $post->ID, '_openagenda_end_date', true );
	$end_time   = get_post_meta( $post->ID, '_openagenda_end_time', true );
	$location   = get_post_meta( $post->ID, '_openagenda_location', true );
	$external   = get_post_meta( $post->ID, '_openagenda_external_url', true );
	$all_day    = '1' === get_post_meta( $post->ID, '_openagenda_all_day', true );
	$color      = get_post_meta( $post->ID, '_openagenda_color', true );
	$terms      = get_the_terms( $post, 'openagenda_event_topic' );

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
		'start'     => openagenda_combine_date_time( $start_date, $start_time, $all_day ),
		'end'       => openagenda_combine_date_time( $end_date, $end_time, $all_day ),
		'allDay'    => $all_day,
		'url'       => ! empty( $external ) ? $external : get_permalink( $post ),
		'permalink' => get_permalink( $post ),
		'location'  => $location,
		'excerpt'   => openagenda_get_plain_event_excerpt( $post ),
		'color'     => sanitize_hex_color( $color ) ? $color : '#ffffff',
		'topics'    => is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array(),
		'recurring' => 'none' !== openagenda_sanitize_recurrence( get_post_meta( $post->ID, '_openagenda_recurrence', true ) ),
		'multiDay'  => ! empty( $end_date ) && $end_date !== $start_date,
		'dateLabel' => openagenda_format_event_datetime_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		'startShortDateLabel' => openagenda_format_event_short_single_date_value( $start_date ),
		'endShortDateLabel' => openagenda_format_event_short_single_date_value( $end_date ),
		'shortDateLabel' => openagenda_format_event_short_date_value( $start_date, $end_date ),
		'timeLabel' => openagenda_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		'compactTimeLabel' => openagenda_format_event_compact_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ),
		'dayLabel'  => openagenda_format_event_day_value( $start_date ),
		'monthLabel' => openagenda_format_event_month_value( $start_date ),
	);
}

/**
 * Returns a plain excerpt without invoking content/excerpt filters.
 *
 * @param WP_Post $post Event post.
 * @return string
 */
function openagenda_get_plain_event_excerpt( $post ) {
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
function openagenda_combine_date_time( $date, $time, $all_day ) {
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
function openagenda_format_event_datetime( $post_id ) {
	$start_date = get_post_meta( $post_id, '_openagenda_start_date', true );
	$start_time = get_post_meta( $post_id, '_openagenda_start_time', true );
	$end_date   = get_post_meta( $post_id, '_openagenda_end_date', true );
	$end_time   = get_post_meta( $post_id, '_openagenda_end_time', true );
	$all_day    = '1' === get_post_meta( $post_id, '_openagenda_all_day', true );

	return openagenda_format_event_datetime_value( $start_date, $start_time, $end_date, $end_time, $all_day );
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
function openagenda_format_event_datetime_value( $start_date, $start_time, $end_date, $end_time, $all_day ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	$date_format = get_option( 'date_format' );
	$start_label = wp_date( $date_format, strtotime( $start_date ) );

	if ( ! empty( $end_date ) && $end_date !== $start_date ) {
		$start_label .= ' - ' . wp_date( $date_format, strtotime( $end_date ) );
	}

	if ( ! $all_day && ! empty( $start_time ) ) {
		$start_label .= ', ' . openagenda_format_local_time_value( $start_time );

		if ( ! empty( $end_time ) ) {
			$start_label .= ' - ' . openagenda_format_local_time_value( $end_time );
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
function openagenda_format_event_date_range_value( $start_date, $end_date = '' ) {
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
function openagenda_format_event_short_date( $post_id ) {
	$start_date = get_post_meta( $post_id, '_openagenda_start_date', true );
	$end_date   = get_post_meta( $post_id, '_openagenda_end_date', true );

	return openagenda_format_event_short_date_value( $start_date, $end_date );
}

/**
 * Formats event date value as a compact numeric label or range.
 *
 * @param string $start_date Start date.
 * @param string $end_date   End date.
 * @return string
 */
function openagenda_format_event_short_date_value( $start_date, $end_date = '' ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	$label = openagenda_format_event_short_single_date_value( $start_date );

	if ( ! empty( $end_date ) && $end_date !== $start_date ) {
		$label .= ' - ' . openagenda_format_event_short_single_date_value( $end_date );
	}

	return $label;
}

/**
 * Formats a single date as a compact numeric label.
 *
 * @param string $date Date value.
 * @return string
 */
function openagenda_format_event_short_single_date_value( $date ) {
	if ( empty( $date ) ) {
		return '';
	}

	return wp_date( 'd.m.Y', strtotime( $date ) );
}

/**
 * Formats event time range.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function openagenda_format_event_time( $post_id ) {
	$start_date = get_post_meta( $post_id, '_openagenda_start_date', true );
	$start_time = get_post_meta( $post_id, '_openagenda_start_time', true );
	$end_date   = get_post_meta( $post_id, '_openagenda_end_date', true );
	$end_time   = get_post_meta( $post_id, '_openagenda_end_time', true );
	$all_day    = '1' === get_post_meta( $post_id, '_openagenda_all_day', true );

	return openagenda_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day );
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
function openagenda_format_event_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ) {
	if ( $all_day || empty( $start_date ) || empty( $start_time ) ) {
		return '';
	}

	$label = openagenda_format_local_time_value( $start_time );

	if ( ! empty( $end_time ) ) {
		$label .= ' - ' . openagenda_format_local_time_value( $end_time );
	}

	return $label;
}

/**
 * Formats a stored local HH:MM time without applying timezone conversion.
 *
 * @param string $time Stored time value.
 * @return string
 */
function openagenda_format_local_time_value( $time ) {
	$time = openagenda_sanitize_time( $time );

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
function openagenda_format_event_compact_time_value( $start_date, $start_time, $end_date, $end_time, $all_day ) {
	if ( $all_day || empty( $start_date ) || empty( $start_time ) ) {
		return '';
	}

	$start_label = openagenda_format_local_time_value( $start_time );

	if ( ! empty( $end_time ) ) {
		$end_label = openagenda_format_local_time_value( $end_time );

		return sprintf(
			/* translators: 1: event start time, 2: event end time. */
			__( '%1$s - %2$s o\'clock', 'openagenda-events-calendar' ),
			$start_label,
			$end_label
		);
	}

	return sprintf(
		/* translators: %s: event start time. */
		__( '%s o\'clock', 'openagenda-events-calendar' ),
		$start_label
	);
}

/**
 * Formats event day for the visual date badge.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function openagenda_format_event_day( $post_id ) {
	$start_date = get_post_meta( $post_id, '_openagenda_start_date', true );

	return openagenda_format_event_day_value( $start_date );
}

/**
 * Formats event day value for the visual date badge.
 *
 * @param string $start_date Start date.
 * @return string
 */
function openagenda_format_event_day_value( $start_date ) {
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
function openagenda_format_event_month( $post_id ) {
	$start_date = get_post_meta( $post_id, '_openagenda_start_date', true );

	return openagenda_format_event_month_value( $start_date );
}

/**
 * Formats event month value for the visual date badge.
 *
 * @param string $start_date Start date.
 * @return string
 */
function openagenda_format_event_month_value( $start_date ) {
	if ( empty( $start_date ) ) {
		return '';
	}

	return wp_date( 'M', strtotime( $start_date ) );
}
