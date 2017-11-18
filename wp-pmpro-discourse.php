<?php
/*
Plugin Name: WordPress PMPro Discourse
Plugin URI: https://github.com/joebuhlig/wp-pmpro-discourse
Version: 0.1
Author: Joe Buhlig
Author URI: https://joebuhlig.com
GitHub Plugin URI: https://github.com/joebuhlig/wp-pmpro-discourse
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-pmpro-discourse
*/
if (!function_exists('write_log')) {
    function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }
}


add_action( 'pmpro_after_change_membership_level', 'discourse_groups_sync', 10, 4 );
add_action( 'updated_user_meta', 'discourse_groups_sync_setup', 10, 4 );

function discourse_groups_sync_setup( $meta_id, $user_id, $meta_key, $meta_value ) {
  global $wpdb;
  $sql = "SELECT membership_id, status FROM $wpdb->pmpro_memberships_users WHERE user_id=$user_id";
  $results = $wpdb->get_results($sql);
  foreach( $results as $result ) {
    if ( $result->status == "active") {
      discourse_groups_sync( $result->membership_id, $user_id, false) ;
    }
    else {
      discourse_groups_sync( $result->membership_id, $user_id, $result->membership_id ); 
    }
  }
}

function discourse_groups_sync( $level_id, $user_id, $cancel_level ) {
  $discourse_options = get_option( 'discourse_connect' );
  $discourse_url = $discourse_options['url'];;
  $api_key = $discourse_options['api-key'];
  $api_username = 'system';
  $userdata = get_userdata( $user_id );
  $username = $userdata->user_email;

  if ( $cancel_level ) {
    $level_id = $cancel_level;
    $type = "DELETE";
  }
  else {
    $type = "PUT";
  }

  global $wpdb;
  $sql = "SELECT * FROM $wpdb->pmpro_membership_levels";
  $pmpro_levels = $wpdb->get_results($sql);

  $group_name = false;
  foreach ( $pmpro_levels as $level ) {
    if ( $level->id == $level_id ) {
      $group_name = $level->name;
    }
  }

  if ( $group_name ) {
    $url = sprintf(
      '%s/groups.json?api_key=%s&api_username=%s',
      $discourse_url,
      $api_key,
      $api_username
    );
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    $body = curl_exec( $ch );
    curl_close( $ch );
    $json = json_decode( $body, true );

    foreach ( $json['groups'] as $group_key => $group_value ) {
      if ( strtolower($group_value["name"]) == strtolower($group_name) ) {
        if ( $type == "PUT" ) {
          $url = sprintf(
            '%s/groups/%s/members.json?api_key=%s&api_username=%s&user_emails=%s',
            $discourse_url,
            $group_value["id"],
            $api_key,
            $api_username,
            $username
          );
        }
        elseif ( $type == "DELETE") {
          $url = sprintf(
            '%s/groups/%s/members.json?api_key=%s&api_username=%s&user_email=%s',
            $discourse_url,
            $group_value["id"],
            $api_key,
            $api_username,
            $username
          );
        }

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $type );
        $body = curl_exec( $ch );
        curl_close( $ch );
        $json = json_decode( $body, true );
      }
    }
  }
}
?>