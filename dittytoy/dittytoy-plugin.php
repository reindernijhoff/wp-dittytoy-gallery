<?php

/*
Plugin Name: Dittytoy Gallery
Plugin URI: https://github.com/reindernijhoff/wp-dittytoy-gallery
Description: Creates and update a gallery with Dittytoy ditties based on a query.
Version: 0.1
Author: Reinder Nijhoff
Author URI: https://reindernijhoff.net/
*/

$dittytoy_db_version = '1.0';

function dittytoy_install() {
	global $wpdb;
	global $dittytoy_db_version;

	$table_name = $wpdb->prefix . 'dittytoy';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id varchar(255) NOT NULL,
  		expires datetime NOT NULL,
  		data mediumtext NOT NULL,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'dittytoy_db_version', $dittytoy_db_version );
}

function dittytoy_curl_get_contents($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	$data = curl_exec($ch);
	curl_close($ch);

	return $data;
}

function dittytoy_do_query($query, $timeout = 60*60) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'dittytoy';

	$timeout += intval(rand(0, $timeout)); // prevent that all cached items get invalid at the same time

	$json = '';

	$dbkey = $query;

	$cached = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %s AND expires > NOW()", $dbkey) );
	if ($cached) {
		$json = $cached->data;
	} else {
		$url = 'https://dittytoy.net/api/v1/' . $query;
		$json = dittytoy_curl_get_contents($url);

		json_decode($json);
		if (json_last_error() != JSON_ERROR_NONE) {
			$wpdb->query( $wpdb->prepare( "REPLACE INTO $table_name( id, data, expires ) VALUES ( %s, %s, NOW() + INTERVAL %d SECOND )", $dbkey, $json, $timeout ) );
		}
	}

	$obj = json_decode($json, true);
	return $obj;
}

function dittytoy_list($atts) {
	$a = shortcode_atts( array(
		'username' => false,
		'query' => '',
		'columns' => 2,
		'hideusername' => 0
	), $atts );

	$username = $a['username'];

	$list = dittytoy_do_query($a['query']);
	$results = $list["objects"];

	$html = '<ul class="wp-block-gallery columns-' . $a['columns'] . ' is-cropped">';

	$start = microtime(true);

	foreach ($results as $key => $turtle) {
		$info = $turtle;

		$html .= dittytoy_layout_ditty($info, $a['hideusername']);

		if (microtime(true) - $start > 15) {
			break;
		}
	}


	$html .= '</ul>';	 
    return $html;
}

function dittytoy_layout_ditty($info, $hideusername) {
	$html = '<li class="blocks-gallery-item"><figure>';
	$html .= '<a href="' . $info['url'] . '" title="' . htmlentities($info['title'] . ' by ' . $info['user_id']) .'">';
	$html .= '<picture>';
	$html .= '<source type="image/webp" srcset="' . $info['webp'] . '" />';
	$html .= '<img src="' . $info['img'] . '" alt="' . str_replace("\n", '&#10;', htmlentities($info['description'])) . '" width="512" height="512" />';
	$html .= '</picture>';
	$html .= '<figcaption>' . $info['title'] . (!$hideusername?'<br/>by ' . $info['user_id']:'') . '</figcaption>';
	$html .= '</a>';
	$html .= '</figure></li>';

	return $html;
}

register_activation_hook( __FILE__, 'dittytoy_install' );
add_shortcode('dittytoy-list', 'dittytoy_list');
