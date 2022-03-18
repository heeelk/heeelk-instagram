<?php
/*
 * Plugin Name: heeelk Instagram
 * Description: API Instagram
 * Version: 1.0
 * Author: Dmitriy Savchenko
 * Author URI: https://cheitgroup.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined( 'ABSPATH' )) {
	exit;
}

class HeeelkInstagram {

    public $page_slug;
    public $option_group;

    function __construct()
    {
        $this->page_slug = 'heeelk_instagram';
        $this->option_group = 'heeelk_instagram_settings';

        add_action('admin_menu', array($this, 'add'), 25);
        add_action('admin_init', array($this, 'settings'));
        add_action('admin_notices', array($this, 'notice'));
    }

    function add() {
        add_menu_page('Instagram', 'Instagram', 'manage_options', $this->page_slug, array($this, 'display'), 'dashicons-camera-alt');
    }

    function display() {
        echo '<div class="wrap">
                <h1>' . get_admin_page_title() . '</h1>
                <form method="post" action="options.php">';

                settings_fields($this->option_group);
                do_settings_sections($this->page_slug);
                submit_button();

        echo '</form></div>';
    }

    function settings() {
        register_setting($this->option_group, $this->page_slug . '_app_id');
        register_setting($this->option_group, $this->page_slug . '_access_token');
        register_setting($this->option_group, $this->page_slug . '_access_token_date');
        register_setting($this->option_group, $this->page_slug . '_limit');

        add_settings_section('settings_section_id', '', '', $this->page_slug);

        add_settings_field(
            $this->page_slug . '_app_id',
            'App ID',
            array($this, 'field'),
            $this->page_slug,
            'settings_section_id',
            array(
                'label_for' => $this->page_slug . '_app_id',
                'name'      => $this->page_slug . '_app_id'
            )
        );

        add_settings_field(
            $this->page_slug . '_access_token',
            'Access Token',
            array($this, 'field'),
            $this->page_slug,
            'settings_section_id',
            array(
                'label_for' => $this->page_slug . '_access_token',
                'name'      => $this->page_slug . '_access_token'
            )
        );

        add_settings_field(
            $this->page_slug . '_access_token_date',
            'Access Token expires in',
            array($this, 'date'),
            $this->page_slug,
            'settings_section_id',
            array(
                'label_for' => $this->page_slug . '_access_token_date',
                'name'      => $this->page_slug . '_access_token_date'
            )
        );

        add_settings_field(
            $this->page_slug . '_limit',
            'Access Token expires in',
            array($this, 'limit'),
            $this->page_slug,
            'settings_section_id',
            array(
                'label_for' => $this->page_slug . '_limit',
                'name'      => $this->page_slug . '_limit'
            )
        );
    }

    function field($args) {
        $value = get_option($args['name']);
        printf(
            '<input type="text" id="%s" name="%s" value="%s" style="min-width:298px;">',
            esc_attr($args['name']),
            esc_attr($args['name']),
            sanitize_text_field($value)
        );
    }

    function date($args) {
        $value = get_option($args['name']);
        printf(
            '<input type="text" id="%s" name="%s" value="%s" style="min-width:298px;" readonly>',
            esc_attr($args['name']),
            esc_attr($args['name']),
            sanitize_text_field($value) ?: date('d.m.Y', (time() + 5180481))
        );
    }

    function limit($args) {
        $value = get_option($args['name']);
        printf(
            '<input type="number" id="%s" name="%s" value="%s" style="min-width:298px;">',
            esc_attr($args['name']),
            esc_attr($args['name']),
            absint($value) ?: 8
        );
    }

    function notice() {
		if (
			isset( $_GET[ 'page' ] )
			&& $this->page_slug == $_GET[ 'page' ]
			&& isset( $_GET[ 'settings-updated' ] )
			&& true == $_GET[ 'settings-updated' ]
		) {
			echo '<div class="notice notice-success is-dismissible"><p>The fields are saved!</p></div>';
		}
	}

    function get_list($after = false) {
        $accessToken = get_option($this->page_slug . '_access_token');
        $tokenDate = get_option($this->page_slug . '_access_token_date');
        $limit = get_option($this->page_slug . '_limit', 8);

        $tokenTimestamp = strtotime($tokenDate);
        $curTimestamp = time();
        $dayDiff = ($curTimestamp - $tokenTimestamp) / 86400;

        if (empty($accessToken)) return;
        
        // If the token is more than 50 days old, then update it
        if ($dayDiff > 50) {
            $url = 'https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=' . $accessToken;
            $instagramCnct = curl_init();
            curl_setopt($instagramCnct, CURLOPT_URL, $url);
            curl_setopt($instagramCnct, CURLOPT_RETURNTRANSFER, 1);
            $response = json_decode(curl_exec($instagramCnct));
            curl_close($instagramCnct);

            if ($response) {
                $accessToken = $response->access_token;
                $tokenDate = date('d.m.Y', (time() + $response->expires_in));
                update_option($this->page_slug . '_access_token', $accessToken);
                update_option($this->page_slug . '_access_token_date', $tokenDate);
            }
        }

        // Getting the feed
        $url = 'https://graph.instagram.com/me/media?fields=id,media_type,media_url,caption,timestamp,thumbnail_url,permalink,children{fields=id,media_url,thumbnail_url,permalink}&limit=' . $limit . '&access_token=' . $accessToken;
        if ($after) {
            $url .= '&after=' . $after;
        }
        $instagramCnct = curl_init();
        curl_setopt($instagramCnct, CURLOPT_URL, $url);
        curl_setopt($instagramCnct, CURLOPT_RETURNTRANSFER, 1);
        $media = json_decode(curl_exec($instagramCnct));
        curl_close($instagramCnct);

        // $instaFeed = array();
        // foreach ($media->data as $mediaObj) {
        //     if (!empty($mediaObj->children->data)) {
        //         foreach ($mediaObj->children->data as $children) {
        //             $instaFeed[$children->id]['img'] = $children->thumbnail_url ?: $children->media_url;
        //             $instaFeed[$children->id]['link'] = $children->permalink;
        //         }
        //     } else {
        //         $instaFeed[$mediaObj->id]['img'] = $mediaObj->thumbnail_url ?: $mediaObj->media_url;
        //         $instaFeed[$mediaObj->id]['link'] = $mediaObj->permalink;
        //     }
        // }

        return $media;
    }

}

new HeeelkInstagram();
