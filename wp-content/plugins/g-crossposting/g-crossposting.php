<?php
/*
	Plugin Name: Google+ Crossposting
	Plugin URI: http://wordpress.org/extend/plugins/g-crossposting/
	Description: Imports your public Google+ activities in your Wordpress blog.
	Version: 1.6.1
	Author: Sebastian Stein
	Author URI: http://sebstein.hpfsc.de/
	Text Domain: g-crossposting
	Domain Path: /lang
	License: GPL2
*/


/*
	Copyright 2011  Sebastian Stein  (email : seb.kde@hpfsc.de)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// don't allow to be called directly
if (!function_exists('add_action')) {
	echo "Don't call this file directly!";
	exit;
}

// load text domain for translations
load_plugin_textdomain('g-crossposting', false, basename(dirname(__FILE__)).'/lang');

// load admin UI only if current user is an admin
if (is_admin()) {
	require_once dirname(__FILE__).'/admin.php';
}

// add our own 5 minute scheduler
add_filter('cron_schedules', 'g_crossposting_cron_definer');

// register activation hook
register_activation_hook(__FILE__, 'g_crossposting_activation');

// register deactivation hook
register_deactivation_hook(__FILE__, 'g_crossposting_deactivation');

// get our options
$options = g_crossposting_get_settings();

// register an action for wp_head() hook with high priority so that we can still
// remove the rel_default action of this hook
if ($options['addcanonical'] == 'true') {
	add_action('wp_head', 'g_crossposting_wp_head', 9);
}

// load CSS
// add_action('wp_enqueue_scripts', 'g_crossposting_load_css');

// bind _update function to update action triggered as a scheduled event
add_action('g_crossposting_update_action', 'g_crossposting_update');

/**
 * load CSS file for this plugin
 */
// function g_crossposting_load_css() {
//	wp_register_style('g_crossposting', plugins_url('g-crossposting/style.css'));
//	wp_enqueue_style('g_crossposting');
//}

/**
 * gets setup options from DB and adds some default values in case a setting is
 * missing
 */
function g_crossposting_get_settings() {
	$options = get_option('g_crossposting_options');

	if (! isset($options['titlelen']) || empty($options['titlelen'])) {
		$options['titlelen'] = 0;
		update_option('g_crossposting_options', $options);
	}

	return $options;
}

/**
 *  create a 5 minute schedule
 */
function g_crossposting_cron_definer($schedules) {
	$schedules['5minutes'] = array(
			'interval' => 300,
			'display' => __('Once every 5 minutes', 'g-crossposting'),
		);

	return $schedules;
}

/**
 *  triggered on plugin activation
 */
function g_crossposting_activation() {
	// register our action hook for checking for new G+ posts every 5 minutes
	wp_schedule_event(time(), '5minutes', 'g_crossposting_update_action');
}

/**
 *  triggered on plugin deactivation
 */
function g_crossposting_deactivation() {
	// remove our action hook for checking for new G+ posts every 5 minutes
	wp_clear_scheduled_hook('g_crossposting_update_action');
}

/**
 *  action bound to wp_head hook adding canonical tag
 */
function g_crossposting_wp_head() {
	// first check if all settings are in place
	if (! g_crossposting_is_enabled()) {
		return;
	}

	// only act on single posts
	if (!is_single() || is_feed()) {
		return;
	}

	$post_meta = get_post_custom();
	if ($post_meta && ! array_key_exists('g_crossposting_posturl', $post_meta)) {
		return;
	}

	// print our canonical tag
	remove_action('wp_head', 'rel_canonical');
	echo "<link rel=\"canonical\" href=\"{$post_meta['g_crossposting_posturl'][0]}\" />";
}

/**
 *  checks Google+ for new activities and post them
 */
function g_crossposting_update() {
	// first check if all settings are in place
	if (! g_crossposting_is_enabled()) {
		return;
	}

	// get new activities if any
	$new_activities = array();
	$new_activities = g_crossposting_get_new_activities();
	if ($new_activities == null) {
		return;
	}

	// post new activities on blog
	g_crossposting_post_new($new_activities);
}

/**
 *  checks if we got correct settings like API key and Google+ ID
 *  it doesn't check if querying the API works
 *
 *  @return true if settings are correct, otherwise false
 */
function g_crossposting_is_enabled() {
	$options = g_crossposting_get_settings();

	// basic validation showed that we might have valid connection settings
	// now do an actual connect to see if we are able to connect
	if (g_crossposting_api_activities_list($options['gplusid'], $options['apikey'], 1, "") == null) {
		// either settings are wrong or gplus is offline

		return FALSE;
	}

	// great, we are able to connect to the API
	//
	// go on validating remaining settings
	if (! g_crossposting_check_maxactivities($options['maxactivities'])) {
		return FALSE;
	}

	if (! g_crossposting_check_titlelen($options['titlelen'])) {
		return FALSE;
	}

	if (! g_crossposting_check_addcanonical($options['addcanonical'])) {
		return FALSE;
	}

	if (! g_crossposting_check_category($options['category'])) {
		return FALSE;
	}

	if (! g_crossposting_check_comment($options['comment'])) {
		return FALSE;
	}

	if (! g_crossposting_check_author($options['author'])) {
		return FALSE;
	}

	return TRUE;
}

/**
 *  query Google+ API activities.list function
 *
 *  @return array with activities or null
 */
function g_crossposting_api_activities_list($p_gplusid, $p_apikey, $p_maxactivities, $p_pagetoken) {
	// get list of latest p_maxactivities activities
	$query = "https://www.googleapis.com/plus/v1/people/{$p_gplusid}/activities/public?alt=json&key={$p_apikey}";

	if (isset($p_maxactivities)) {
		$query = $query."&maxResults={$p_maxactivities}";
	}

	if (isset($p_pagetoken)) {
		$query = $query."&pageToken={$p_pagetoken}";
	}

	$ch = curl_init($query);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$activities = curl_exec($ch);
	curl_close($ch);

	$activities = json_decode($activities);

	// check if we got errors
	if (isset($activities, $activities->error)) {
		return null;
	}

	// looks fine, return activities
	return $activities;
}

/**
 *  returns new activities not yet posted on blog
 *
 *  @return array of new activities or null if no new activities were
 *          found on Google+
 */
function g_crossposting_get_new_activities() {
	// load options
	$options = g_crossposting_get_settings();

	// get activities from Google+
	$activities = g_crossposting_api_activities_list($options['gplusid'],
							$options['apikey'], $options['maxactivities'], "");
	if ($activities == null) {
		return null;
	}

	if (isset($activities->items) && count($activities->items) > 0) {
		return $activities->items;
	} else {
		return null;
	}
}

/**
 *  creates posts for every given activity without checking if this activity was
 *  already posted before!
 */
function g_crossposting_post_new($activities) {
	// go through all given activities and post them
	$latest_activity_id = null;
	$posts_updated = 0;
	foreach ($activities as $activity) {
		// no activity ID or one of being null must be wrong
		if (! isset($activity->id)) {
			continue;
		}

		// always keep track of all old activities to prevent reposting
		if (get_option('g_crossposting_'.$activity->id) != false) {
			continue;
		}
		update_option('g_crossposting_'.$activity->id, $activity->id);

		// currently, we only post posts and not shares :-)
		if ($activity->verb != 'post') {
			continue;
		}

		// we found a new post, so let's increment our counter
		$posts_updated++;

		// get options to configure way how activities get imported
		$options = g_crossposting_get_settings();

		// prepare data for new post
		$post_time = strtotime($activity->published);
		$post_time_gm = gmdate('Y-m-d H:i:s', $post_time);

		// create content
		$post_content = $activity->object->content;
		$att_photo = null;
		$att_title = null;
		$att_article = null;
		$att_url = null;
		if (isset($activity->object->attachments)) {
			foreach ($activity->object->attachments as $attachment) {
				switch ($attachment->objectType) {
					case 'article':
						$att_title = $attachment->displayName;
						$att_url = $attachment->url;

						$att_article = "";
						if (isset($attachment->content)) {
							$att_article = $attachment->content;
						}

						if (isset($attachment->image, $attachment->image->url)) {
							$att_photo = $attachment->image->url;
						}
						break;
					case 'photo':
						$att_photo = $attachment->image->url;
						break;
					case 'video':
						$att_title = $attachment->displayName;
						$att_article = substr($attachment->content, 0, 100);
						$att_photo = $attachment->image->url;
						$att_url = $activity->url;
						break;
				}
			}
			$post_content .= '<div class="g-crossposting-att">';

			if (isset($att_url) && isset($att_title)) {
				$post_content .= "<div class=\"g-crossposting-att-title\"><p><a href=\"{$att_url}\" target=\"_blank\">{$att_title}</a></p></div>";
			}

			if ($att_photo) {
				$post_content .= '<div class="g-crossposting-att-img">';
				$post_content .= "<p><a href=\"{$att_url}\" target=\"_blank\">";
				$post_content .= "<img src=\"{$att_photo}\" />";
				$post_content .= '</a></p></div>';
			}

			if ($att_article) {
				$post_content .= '<div class="g-crossposting-att-txt"><p>'.$att_article.'</p></div>';
				$post_content .= '</div>';
			}
		}
		$post_content .= '<div class="g-crossposting-backlink"><p><a href="'.$activity->url.'" target="_blank">'.__('This was posted on Google+', 'g-crossposting').'</a></p></div>';


		// if content starts with bold text, use it as title
		$post_title = '';
		if (isset($activity->object->content)) {
			$trimmed_content = trim($activity->object->content);
			$regex = '/^<b>(.*)<\/b>/U';
			$matches = array();
			if (preg_match($regex, $trimmed_content, $matches) && count($matches) == 2) {
				$post_title = $matches[1];
			}
		}

		// fallback on G+ title generation logic
		if (empty($post_title)) {
			if ($activity->title) {
				$post_title = $activity->title;
			} else if ($att_title) {
				$post_title = $att_title;
			} else {
				$post_title = __('Google+ post: No title available&hellip;', 'g-crossposting');
			}
		}

		// shorten post title if required
		if ($options['titlelen'] > 0 && strlen($post_title) > $options['titlelen']) {
			$post_title = substr($post_title, 0, $options['titlelen']).'&hellip;';
		}

		// set comment status for post
		$post_comment = 'closed';
		if ($options['comment'] == true) {
			$post_comment = 'open';
		}

		// create post array
		$new_post = array(
			'post_title' => $post_title,
			'post_content' => $post_content,
			'post_status' => 'publish',
			'post_author' => $options['author'],
			'post_date_gmt' => $post_time_gm,
			'post_date' => $post_time_gm,
			'post_type' => 'post',
			'post_category' => array($options['category']),
			'ping_status' => 'closed',
			'comment_status' => $post_comment,
		);

		// create post and add some meta information we might need later
		$post_id = wp_insert_post($new_post);
		if ($post_id) {
			add_post_meta($post_id, 'g_crossposting_posturl', $activity->url, TRUE);
			add_post_meta($post_id, 'g_crossposting_postid', $activity->id, TRUE);
		}
	}
	return $posts_updated;
}


/**
 *  the maximum number of activities to import must be 1..100
 *
 *  @return true if this is set to a number from 1 to 100
 */
function g_crossposting_check_maxactivities($given_maxactivities) {
	// must be number from 1 to 100
	if (!is_numeric($given_maxactivities) || $given_maxactivities < 1 || $given_maxactivities > 100) {
		return FALSE;
	}

	return TRUE;
}

/**
 *  maximum length of post title to import
 *
 *  @return true if this is set to a number from 0 to 100
 */
function g_crossposting_check_titlelen($given_titlelen) {
	// must be number from 0 to 100
	if (!is_numeric($given_titlelen) || $given_titlelen < 0 || $given_titlelen > 100) {
		return FALSE;
	}

	return TRUE;
}

/**
 *  simple binary option whether canonical tag should be added
 *
 *  @return true if this is set to a correct value
 */
function g_crossposting_check_addcanonical($given_addcanonical) {
	// must be either '' or true
	if (! (empty($given_addcanonical) || $given_addcanonical == 'true')) {
		return FALSE;
	}

	return TRUE;
}

/**
 *  check category setting the posts should be assigned to
 *
 *  @return true if this is set to a correct value
 */
function g_crossposting_check_category($given_category) {
	$cat_obj = get_category($given_category);
	if (isset($cat_obj) && isset($cat_obj->cat_ID) && $given_category == $cat_obj->cat_ID) {
		return TRUE;
	}

	return FALSE;
}

/**
 *  simple binary option whether comments should be turned on
 *
 *  @return true if this is set to a correct value
 */
function g_crossposting_check_comment($given_comment) {
	// must be either '' or true
	if (! (empty($given_comment) || $given_comment == 'true')) {
		return FALSE;
	}

	return TRUE;
}

/**
 *  check author setting the posts should be assigned to
 *
 *  @return true if this is set to a correct value
 */
function g_crossposting_check_author($given_author) {
	// TODO dude, that's strange, sometimes get_userdata is not available
	if (! function_exists('get_userdata')) {
		require_once(ABSPATH . 'wp-includes/pluggable.php');
	}

	$user_obj = get_userdata($given_author);
	if ($user_obj && $given_author == $user_obj->ID) {
		return TRUE;
	}

	return FALSE;
}

/**
*  checks Google+ for all activities and posts them
*/
function g_crossposting_update_all() {
	// first check if all settings are in place
	if (! g_crossposting_is_enabled()) {
		return;
	}

	// get all activities
	$all_activities = array();
	$all_activities = g_crossposting_get_all_activities();
	if ($all_activities == null) {
		return;
	}

	// post new activities on blog
	$new_activities_count = g_crossposting_post_new($all_activities);
	return $new_activities_count.' out of '.count($all_activities);
}

/**
*  returns all activities
*
*  @return array of all activities found on Google+
*/
function g_crossposting_get_all_activities() {
	// load options
	$options = g_crossposting_get_settings();

	// go through all activities
	$all_activities = array();
	$nextPageToken = null;
	do
	{
		$activities = g_crossposting_api_activities_list($options['gplusid'],
															$options['apikey'], 100, $nextPageToken);
		if ($activities == null) {
			return null;
		}

		if (isset($activities->nextPageToken)) {
			$nextPageToken = $activities->nextPageToken;
		} else {
			$nextPageToken = null;
		}

		$all_activities = array_merge($all_activities, $activities->items);
	}
	while($nextPageToken != null);

	if (count($all_activities) > 0) {
		return $all_activities;
	} else {
		return null;
	}
}
