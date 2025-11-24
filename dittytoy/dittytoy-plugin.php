<?php

/*
Plugin Name: Dittytoy Gallery
Plugin URI: https://github.com/reindernijhoff/wp-dittytoy-gallery
Description: A WordPress plugin to display Dittytoy galleries.
Version: 1.0.0
Author: Reinder Nijhoff
Author URI: https://reindernijhoff.net/
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function dittytoy_install()
{
}

function dittytoy_fetch($url)
{
    $response = wp_remote_get($url);
    $body = wp_remote_retrieve_body($response);

    return $body;
}

function dittytoy_do_query($query, $timeout = 60 * 60)
{
    $data = '';

    $dbkey = 'dittytoy_' . $query;

    $cached = get_transient($dbkey);
    if ($cached) {
        $data = $cached;
    } else {
        $url = 'https://dittytoy.net/api/v1/' . $query;
        $data = dittytoy_fetch($url);
        $json = json_decode($data);

        if (json_last_error() == JSON_ERROR_NONE) {
            $data = json_encode($json);

            set_transient($dbkey, $data, $timeout + wp_rand(0, $timeout));
        }
    }

    return json_decode($data, TRUE);
}

function dittytoy_list($atts)
{
    $a = shortcode_atts(array('username' => FALSE,
        'query' => '',
        'columns' => 2,
        'limit' => 0,
        'hideusername' => 0), $atts);

    $username = $a['username'];
    $limit = $a['limit'];

    $list = dittytoy_do_query($a['query']);
    $results = $list["objects"];

    $html = '<ul class="wp-block-gallery columns-' . esc_attr($a['columns']) . ' is-cropped">';

    $start = microtime(TRUE);

    $count = 0;
    $ldJSON = array();
    foreach ($results as $key => $turtle) {
        $info = $turtle;

        $html .= dittytoy_layout_ditty($info, $a['hideusername']);
        $ldJSON[] = dittytoy_ld_json($info);
        if (microtime(TRUE) - $start > 15) {
            break;
        }

        $count++;
        if ($limit > 0 && $count >= $limit) {
            break;
        }
    }


    $html .= '</ul>';

    $html .= '<script type="application/ld+json">' . wp_json_encode($ldJSON) . '</script>';

    return $html;
}

function dittytoy_ld_json($info)
{
    return array("@context" => "https://schema.org",
        "@type" => "ImageObject",
        "name" => $info['title'],
        "caption" => $info['title'],
        "creator" => array("@type" => "Person",
            "name" => $info['user_id'],
            "identifier" => $info['user_id'],
            "url" => "https://dittytoy.net/user/" . $info['user_id']),
        "description" => $info['description'],
        "image" => "https://dittytoy.net/thumbnail/" . $info['object_id'] . ".jpg",
        "thumbnail" => "https://dittytoy.net/thumbnail/" . $info['object_id'] . ".jpg",
        "contentUrl" => "https://dittytoy.net/thumbnail/" . $info['object_id'] . ".jpg",
        "sameAs" => "https://dittytoy.net/ditty/" . $info['object_id'],
        "url" => "https://dittytoy.net/ditty/" . $info['object_id'],
        "dateCreated" => $info['date_published'],
        "identifier" => $info['object_id'],
        "material" => "Dittytoy Ditty",
        "genre" => "Audio Synthesis",
        "commentCount" => $info['comments'],
        "copyrightHolder" => array("@type" => "Person",
            "name" => $info['user_id'],
            "identifier" => $info['user_id'],
            "url" => "https://dittytoy.net/user/" . $info['user_id']),
        "copyrightYear" => gmdate('Y'),
        "copyrightNotice" => "© " . gmdate('Y') . " " . $info['user_id'] . " - Dittytoy",
        "creditText" => "© " . gmdate('Y') . " " . $info['user_id'] . " - Dittytoy",
        "acquireLicensePage" => "https://dittytoy.net/terms",
        "license" => $info['license']);
}

// phpcs:disable
//
// We directly link images from the dittytoy.net domain, as users can update the preview images without notice.
function dittytoy_layout_ditty($info, $hideusername)
{
    $html = '<li class="blocks-gallery-item"><figure>';
    $html .= '<a href="' . esc_url($info['url']) . '" title="' . esc_attr($info['title'] . ' by ' . $info['user_id']) . '">';
    $html .= '<picture>';
    $html .= '<source type="image/webp" srcset="' . esc_url($info['webp']) . '" />';
    $html .= '<img src="' . esc_url($info['img']) . '" alt="' . esc_attr(str_replace("\n", '&#10;', $info['description'])) . '" width="512" height="512" />';
    $html .= '</picture>';
    $html .= '<figcaption>' . esc_html($info['title']) . (!$hideusername ? '<br/>by ' . esc_html($info['user_id']) : '') . '</figcaption>';
    $html .= '</a>';
    $html .= '</figure></li>';

    return $html;
}

// phpcs:enable

register_activation_hook(__FILE__, 'dittytoy_install');
add_shortcode('dittytoy-list', 'dittytoy_list');
