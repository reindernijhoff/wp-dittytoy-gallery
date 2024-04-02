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
	$timeout += intval(rand(0, $timeout)); // prevent that all cached items get invalid at the same time

	$data = '';

	$dbkey = 'dittytoy_' . $query;

	$cached = get_transient($dbkey);
	if ($cached) {
		$data = $cached;
	} else {
		$url = 'https://dittytoy.net/api/v1/' . $query;
		$data = dittytoy_curl_get_contents($url);
		$json = json_decode($data);

		if (json_last_error() == JSON_ERROR_NONE) {
			// add license to each object
			foreach ($json->objects as $value) {
				// fetch json from https://dittytoy.net/api/v1/turtle/ id /license
				$license = json_decode(dittytoy_curl_get_contents('https://dittytoy.net/api/v1/ditty/' . $value->object_id . '/license'));
				$value->license = $license->url;
			}
			$data = json_encode($json);

			set_transient($dbkey, $data, $timeout);
		}
	}

	return json_decode($data, TRUE);
}

function dittytoy_list($atts) {
	$a = shortcode_atts( array(
		'username' => false,
		'query' => '',
		'columns' => 3,
		'limit' => 0,
		'hideusername' => 0
	), $atts );

	$username = $a['username'];
	$limit = $a['limit'];

	$list = dittytoy_do_query($a['query']);
	$results = $list["objects"];

	$html = '<ul class="wp-block-gallery columns-' . $a['columns'] . ' is-cropped">';

	$start = microtime(true);

    $count = 0;
	$ldJSON = array();
	foreach ($results as $key => $turtle) {
		$info = $turtle;

		$html .= dittytoy_layout_ditty($info, $a['hideusername']);
		$ldJSON[] = dittytoy_ld_json($info);

		if (microtime(true) - $start > 15) {
			break;
		}

		$count ++;
		if ($limit > 0 && $count >= $limit) {
		    break;
		}
	}

	$html .= '</ul>';

	$html .= '<script type="application/ld+json">' . json_encode($ldJSON) . '</script>';

    return $html;
}

function dittytoy_ld_json($info) {
	return array("@context"           => "https://schema.org",
	             "@type"              => "ImageObject",
	             "name"               => $info['title'],
	             "caption"            => $info['title'],
	             "creator"            => array("@type"      => "Person",
	                                           "name"       => $info['user_id'],
	                                           "identifier" => $info['user_id'],
	                                           "url"        => "https://dittytoy.net/user/" . $info['user_id']),
	             "description"        => $info['description'],
	             "image"              => "https://dittytoy.net/thumbnail/" . $info['object_id'] . ".jpg",
	             "thumbnail"          => "https://dittytoy.net/thumbnail/" . $info['object_id'] . ".jpg",
	             "contentUrl"         => "https://dittytoy.net/thumbnail/" . $info['object_id'] . ".jpg",
	             "sameAs"             => "https://dittytoy.net/turtle/" . $info['object_id'],
	             "url"                => "https://dittytoy.net/turtle/" . $info['object_id'],
	             "dateCreated"        => $info['date_published'],
	             "identifier"         => $info['object_id'],
	             "material"           => "Dittytoy Ditty",
	             "genre"              => "Audio Synthesis",
	             "commentCount"       => $info['comments'],
	             "copyrightHolder"    => array("@type"      => "Person",
	                                           "name"       => $info['user_id'],
	                                           "identifier" => $info['user_id'],
	                                           "url"        => "https://dittytoy.net/user/" . $info['user_id']),
	             "copyrightYear"      => date('Y'),
	             "copyrightNotice"    => "© " . date('Y') . " " . $info['user_id'] . " - dittytoy",
	             "creditText"         => "© " . date('Y') . " " . $info['user_id'] . " - dittytoy",
	             "acquireLicensePage" => "https://dittytoy.net/terms",
	             "license"            => $info['license']);
}

function dittytoy_layout_ditty($info, $hideusername) {
	$html = '<li class="blocks-gallery-item" style="object-fit:cover; aspect-ratio: 16/9;"><figure>';
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
