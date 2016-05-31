<?php
/**
 * @package s2Member Framework
 * @version 1.6
 */
/*
Plugin Name: s2Member Trial period adder
Description: A simple add-on to s2Member Framework for adding capability to add trial period for new registration
Author: Alex
Version: 0.1
*/

/**
 * Get real default role back in action
 * by switching it back to the value form 'options' table
 */
if ( ! function_exists( 's2member_trial_set_default_role' ) ) {
	function s2member_trial_set_default_role( $default_role = 's2member_level1' ) {
		global $wpdb;
		$sql_var = $wpdb->get_var( "SELECT `option_value` FROM $wpdb->options WHERE `option_name` = 'default_role'" );
		if ( ! empty( $sql_var ) ) {
			$default_role = $sql_var;
		}
		$default_role = 's2member_level1';
		return $default_role;
	}
}

/**
 * Add menu item with settings page
 */
if ( ! function_exists( 's2member_trial_add_admin_options' ) ) {
	function s2member_trial_add_admin_options( $vars ) {
		$menu = $vars['menu'];
		add_submenu_page( $menu, 'Trial period settings', 'Trial settings', 'manage_options', 's2member-trial.php', 's2member_trial_settings_page');
	}
}


/* Register settings function */
if ( ! function_exists( 's2member_trial_settings' ) ) {
	function s2member_trial_settings() {

		if ( ! function_exists( 'get_plugin_data' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$s2member_trial_plugin_info = get_plugin_data( __FILE__ );

		$s2member_trial_option_defaults	=	array(
			'plugin_option_version' 		=> $s2member_trial_plugin_info["Version"],
			'trial_period' 					=> 30,
			'role_when_trial_over'			=> 'subscriber',
		);

		/* Install the option defaults */
		if ( ! get_option( 's2member_trial_options' ) )
			add_option( 's2member_trial_options', $s2member_trial_option_defaults );

		/* Get options from the database */
		$s2member_trial_options = get_option( 's2member_trial_options' );

		/* Array merge incase this version has added new options */
		if ( ! isset( $s2member_trial_options['plugin_option_version'] ) || $s2member_trial_options['plugin_option_version'] != $s2member_trial_plugin_info["Version"] ) {
			$s2member_trial_options = array_merge( $s2member_trial_option_defaults, $s2member_trial_options );
			$s2member_trial_options['plugin_option_version'] = $s2member_trial_plugin_info["Version"];
			update_option( 's2member_trial_options', $s2member_trial_options );
		}
		return $s2member_trial_options;
	}
}

/**
 * Settings page
 */
if ( ! function_exists( 's2member_trial_settings_page' ) ) {
	function s2member_trial_settings_page() {
		global $wpdb;
		$s2member_trial_options = s2member_trial_settings();
		$user_roles = get_editable_roles();
		$error = $message = '';

		/* process info */
		if ( isset( $_REQUEST['s2member_trial_form_submit'] ) && check_admin_referer( plugin_basename( __FILE__ ), 's2member_trial_nonce_name' ) ) {
			$s2member_trial_options["trial_period"]	= intval( $_REQUEST['s2member_trial_trial_period'] );
			if ( $s2member_trial_options["trial_period"] < 1 ) {
				$s2member_trial_options["trial_period"] = 1;
			}

			/* role when trial period is over */
			if ( isset( $_REQUEST['s2member_trial_role_when_trial_over'] ) && array_key_exists( $_REQUEST['s2member_trial_role_when_trial_over'], $user_roles ) ) {
				$s2member_trial_options["role_when_trial_over"] = $_REQUEST['s2member_trial_role_when_trial_over'];
			}
			update_option( 's2member_trial_options', $s2member_trial_options );
			$message = 'Settings are saved.';
		} elseif ( isset( $_REQUEST['s2member_trial_update_users'] ) && check_admin_referer( plugin_basename( __FILE__ ), 's2member_trial_nonce_name' ) ) {
			$message = 'Users downgraded. Total number of users: ' . s2member_trial_check_for_expired_trials() . '.';
		} ?>


		<div class="wrap">
			<h2>Trial Period Settings</h2>
			<div id="settings_message" class="updated fade" <?php if ( empty( $message )  || "" != $error ) echo 'style="display:none"'; ?>><p><strong><?php echo $message; ?></strong></p></div>
			<div class="error" <?php if ( "" == $error ) echo 'style="display:none"'; ?>><p><strong><?php echo $error; ?></strong></p></div>

			<form id="s2member_trial_settings_form" method="post" action="admin.php?page=s2member-trial.php">
				<table class="form-table">
					<tr valign="top" class="gllr_width_labels">
						<th scope="row">Days for trial period</th>
						<td>
							<input type="number" name="s2member_trial_trial_period" value="<?php echo $s2member_trial_options["trial_period"]; ?>" />
						</td>
					</tr>
					<tr valign="top" class="gllr_width_labels">
						<th scope="row">Role to downgrade after trial is over</th>
						<td>
							<select name="s2member_trial_role_when_trial_over"><?php wp_dropdown_roles( $s2member_trial_options["role_when_trial_over"] ) ?></select>
						</td>
					</tr>
				</table>

				<input type="hidden" name="s2member_trial_form_submit" value="submit" />
				<p class="submit">
					<input type="submit" class="button-primary" value="Save Changes" />
				</p>
				<?php wp_nonce_field( plugin_basename( __FILE__ ), 's2member_trial_nonce_name' ); ?>
			</form>
		</div>

		<?php $expired_trial_users_count = count( s2member_trial_get_expired_trials( $s2member_trial_options ) );
		echo '<b>Total number of expired trial users: ' . $expired_trial_users_count . '</b>.';
		if ( $expired_trial_users_count > 0 ) { ?>

			<div class="wrap">
				<form method="post" action="admin.php?page=s2member-trial.php">
					<input type="hidden" name="s2member_trial_update_users" value="submit" />
					<p class="submit">
						<input type="submit" class="button-primary" value="Downgrade trial users to default role" />
					</p>
					<?php wp_nonce_field( plugin_basename( __FILE__ ), 's2member_trial_nonce_name' ); ?>
				</form>
			</div>

		<?php }
	}
}

/**
 * Add 'Settings' link on plugin page
 */
if ( ! function_exists( 's2member_trial_plugin_action_links' ) ) {
	function s2member_trial_plugin_action_links( $links, $file ) {
		if ( ! is_network_admin() ) {
			/* Static so we don't call plugin_basename on every plugin row. */
			static $this_plugin;
			if ( ! $this_plugin )
				$this_plugin = plugin_basename( __FILE__ );

			if ( $file == $this_plugin ) {
				$settings_link = '<a href="admin.php?page=s2member-trial.php">Settings</a>';
				array_unshift( $links, $settings_link );
			}
		}
		return $links;
	}
}

/**
 * Add cron schedule on activation
 */
if ( ! function_exists( 's2member_trial_plugin_activation' ) ) {
	function s2member_trial_plugin_activation(  ) {
		wp_schedule_event( time() + 60 * 60, 'daily', 's2member_trial_expired_trials' );
	}
}

/**
 * Add cron schedule on deactivation
 */
if ( ! function_exists( 's2member_trial_plugin_deactivation' ) ) {
	function s2member_trial_plugin_deactivation(  ) {
		wp_clear_scheduled_hook( 's2member_trial_expired_trials' );

	}
}

/**
 * Get list of expired trial users
 */
if ( ! function_exists( 's2member_trial_get_expired_trials' ) ) {
	function s2member_trial_get_expired_trials( $s2member_trial_options ) {
		$args = array(
			'role'         => 's2member_level1',
			'date_query'   => array(
				array(
					'before' => $s2member_trial_options['trial_period'] . ' days ago', 
					'inclusive' => false, 
				),
			),        
			'orderby'      => 'registered',
			'order'        => 'ASC',
		 );
		/* get users with expired trial period */
		return get_users( $args );
	}
}

/**
 * Check for out-of-time users with free access and set them to role without access (e.g. 'subscriber')
 */
if ( ! function_exists( 's2member_trial_check_for_expired_trials' ) ) {
	function s2member_trial_check_for_expired_trials() {
		/* get plugin options */
		$s2member_trial_options = s2member_trial_settings();
		/* get users with expired trial period */
		$expired_trial_users = s2member_trial_get_expired_trials( $s2member_trial_options );
		if ( ! empty( $expired_trial_users ) ) {
			foreach ( $expired_trial_users as $user ) {
				$user->set_role( $s2member_trial_options['role_when_trial_over'] );
			}
		}
		return count( $expired_trial_users );
	}
}

/** 
 * Add scripts
 */
if ( ! function_exists ( 's2member_trial_wp_head' ) ) {
	function s2member_trial_wp_head() {
		wp_enqueue_script( 's2member_trial_general_js', plugins_url( 'general.js', __FILE__ ), array( 'jquery' ) ); 	
	}
}

/* hooks */
register_activation_hook( __FILE__ , 's2member_trial_plugin_activation' );
register_deactivation_hook( __FILE__ , 's2member_trial_plugin_deactivation' );

add_filter( 'plugin_action_links', 's2member_trial_plugin_action_links', 10, 2 );
add_action( 'ws_plugin__s2member_after_add_admin_options', 's2member_trial_add_admin_options' );
add_filter( 'ws_plugin__s2member_force_default_role', 's2member_trial_set_default_role' );

/* Add scripts */
add_action( 'wp_enqueue_scripts', 's2member_trial_wp_head' );

/* cron function */
add_action( 's2member_trial_expired_trials', 's2member_trial_check_for_expired_trials' );