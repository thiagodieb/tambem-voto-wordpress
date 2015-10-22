<?php

// don't allow to be called directly
if (!function_exists('add_action')) {
	echo "Don't call this file directly!";
	exit;
}

// only load this file for admins
if (! is_admin()) {
	exit;
}

// register our admin actions
add_action('admin_init', 'g_crossposting_register_options');
add_action('admin_menu', 'g_crossposting_create_conf_menu');

// remove any old messages from cookie
$g_crossposting_msg = null;

// provide admins a hook to trigger manual import of ALL activities
if (array_key_exists('g_crossposting_manual_import_all', $_GET)) {
	$total_activities = g_crossposting_update_all();
	g_crossposting_add_error('g_crossposting_err_import_all', __('Manual import of all '.$total_activities.' Google+ activities done.', 'g-crossposting'));
}

// provide admins a hook to trigger manual import of activities
if (array_key_exists('g_crossposting_manual_import', $_GET)) {
	g_crossposting_update();
	g_crossposting_add_error('g_crossposting_err_import', __('Manual import of Google+ activities done.', 'g-crossposting'));
}

function g_crossposting_add_error($err_code, $err_msg) {
	global $g_crossposting_msg;
	if (is_wp_error($g_crossposting_msg)) {
		$g_crossposting_msg->add($err_code, $err_msg);
	} else {
		$g_crossposting_msg = new WP_Error($err_code, $err_msg);
	}
}

/**
 *  register config page
 */
function g_crossposting_create_conf_menu() {
	if (function_exists('add_submenu_page')) {
		// add menu entry for config page
		add_submenu_page('options-general.php', __('Google+ Crossposting Configuration', 'g-crossposting'), __('Google+ Crossposting', 'g-crossposting'), 'manage_options', 'g-crossposting-key-config', 'g_crossposting_conf');
	}
}

/**
 *  register config options using Settings API
 */
function g_crossposting_register_options() {
	// define our option variable
	register_setting('g_crossposting_options', 'g_crossposting_options', 'g_crossposting_options_validate');

	// add a section around our options
	add_settings_section('g_crossposting_section_main', __('Authentication Settings', 'g-crossposting'), 'g_crossposting_section_main_text', 'g_crossposting');
	add_settings_section('g_crossposting_section_options', __('Setup Options', 'g-crossposting'), 'g_crossposting_section_options_text', 'g_crossposting');

	// add field for each option
	add_settings_field('g_crossposting_gplusid', __('Your Google+ ID', 'g-crossposting'), 'g_crossposting_gplusid_field', 'g_crossposting', 'g_crossposting_section_main');
	add_settings_field('g_crossposting_apikey', __('Your Google API key', 'g-crossposting'), 'g_crossposting_apikey_field', 'g_crossposting', 'g_crossposting_section_main');
	add_settings_field('g_crossposting_user', __('Wordpress user to set as author of imported Google+ activities', 'g-crossposting'), 'g_crossposting_author_field', 'g_crossposting', 'g_crossposting_section_options');
	add_settings_field('g_crossposting_category', __('Category to use for imported Google+ activities', 'g-crossposting'), 'g_crossposting_category_field', 'g_crossposting', 'g_crossposting_section_options');
	add_settings_field('g_crossposting_comment', __('Enable comments on imported Google+ activities', 'g-crossposting'), 'g_crossposting_comment_field', 'g_crossposting', 'g_crossposting_section_options');
	add_settings_field('g_crossposting_maxactivities', __('Maximum number of new activities to import', 'g-crossposting'), 'g_crossposting_maxactivities_field', 'g_crossposting', 'g_crossposting_section_options');
	add_settings_field('g_crossposting_titlelen', __('Maximum length of imported title (set to 0 for full length)', 'g-crossposting'), 'g_crossposting_titlelen_field', 'g_crossposting', 'g_crossposting_section_options');
	add_settings_field('g_crossposting_addcanonical', __('Add canonical meta tag pointing to Google+', 'g-crossposting'), 'g_crossposting_addcanonical_field', 'g_crossposting', 'g_crossposting_section_options');
}

/**
 *  output the input field for the Google+ ID
 */
function g_crossposting_gplusid_field() {
	$options = g_crossposting_get_settings();
	echo "<input id='g_crossposting_gplusid' name='g_crossposting_options[gplusid]' size='59' maxlength='59' type='text' value='{$options['gplusid']}' />";
}

/**
 *  output the input field for the Google API key
 */
function g_crossposting_apikey_field() {
	$options = g_crossposting_get_settings();
	echo "<input id='g_crossposting_apikey' name='g_crossposting_options[apikey]' size='59' maxlength='59' type='text' value='{$options['apikey']}' />";
}

/**
 *  output the input field for the maximum number of new activities to import
 */
function g_crossposting_maxactivities_field() {
	$options = g_crossposting_get_settings();
	echo "<input id='g_crossposting_maxactivities' name='g_crossposting_options[maxactivities]' size='59' maxlength='3' type='text' value='{$options['maxactivities']}' />";
}

/**
 *  output the input field for the maximum length of post title
 */
function g_crossposting_titlelen_field() {
	$options = g_crossposting_get_settings();
	echo "<input id='g_crossposting_titlelen' name='g_crossposting_options[titlelen]' size='59' maxlength='3' type='text' value='{$options['titlelen']}' />";
}

/**
 *  output the checkbox to enable output of canonical tag on Google+ posts
 */
function g_crossposting_addcanonical_field() {
	$options = g_crossposting_get_settings();
	echo "<input id='g_crossposting_addcanonical' name='g_crossposting_options[addcanonical]' type='checkbox' value='true'";
	if ($options['addcanonical'] == 'true') {
		echo " checked";
	}
	echo " />";
}

/**
 *  output selection box with available categories
 */
function g_crossposting_category_field() {
	$options = g_crossposting_get_settings();
	$selected = $options['category'];
	$option_str = 'hide_empty&name=g_crossposting_options[category]&id=g_crossposting_category';
	if (is_numeric($selected)) {
		$option_str .= "&selected={$selected}";
	}
	wp_dropdown_categories($option_str);
}

/**
 *  output the checkbox to enable comments on posts created
 */
function g_crossposting_comment_field() {
	$options = g_crossposting_get_settings();
	echo "<input id='g_crossposting_comment' name='g_crossposting_options[comment]' type='checkbox' value='true'";
	if ($options['comment'] == 'true') {
		echo " checked";
	}
	echo " />";
}

/**
 *  output selection box with available authors
 */
function g_crossposting_author_field() {
	$options = g_crossposting_get_settings();
	$selected = $options['author'];
	$option_str = 'name=g_crossposting_options[author]&id=g_crossposting_author&who=author&who=authors';
	if (is_numeric($selected)) {
		$option_str .= "&selected={$selected}";
	}
	wp_dropdown_users($option_str);
}

/**
 *  description text for main settings section
 */
function g_crossposting_section_main_text() {
	echo '<p>';
	_e('The following settings specify your Google+ ID and your Google API key. Without those settings, the plugin won\'t work.', 'g-crossposting');
	echo '</p>';
}

/**
 *  description text for options settings section
 */
function g_crossposting_section_options_text() {
	echo '<p>';
	_e('The following settings let you configure the behaviour of this plugin.', 'g-crossposting');
	echo '</p>';
}

/**
 *  validation handler for our options array
 */
function g_crossposting_options_validate($input) {
	// trim Google+ ID
	$new_input['gplusid'] = trim($input['gplusid']);

	// trim Google API key
	$new_input['apikey'] = trim($input['apikey']);

	// validate number of maximum activities to import
	$new_input['maxactivities'] = trim($input['maxactivities']);
	if (! g_crossposting_check_maxactivities($new_input['maxactivities'])) {
		$new_input['maxactivities'] = 1;
		g_crossposting_add_error('g_crossposting_err_maxactivities', __('The maximum number of activities to import must be a number between 1 and 100.', 'g-crossposting'));
	}

	// validate maximum length of title field
	$new_input['titlelen'] = trim($input['titlelen']);
	if (! g_crossposting_check_titlelen($new_input['titlelen'])) {
		$new_input['titlelen'] = 0;
		g_crossposting_add_error('g_crossposting_err_titlelen', __('The maximum length of post title should be a number between 0 and 100.', 'g-crossposting'));
	}

	// validate addcanonical setting
	if (isset($input['addcanonical'])) {
		$new_input['addcanonical'] = trim($input['addcanonical']);
		if (! g_crossposting_check_addcanonical($new_input['addcanonical'])) {
			$new_input['addcanonical'] = '';
		}
	} else {
		$new_input['addcanonical'] = '';
	}

	// validate selected category
	$new_input['category'] = trim($input['category']);
	if (! g_crossposting_check_category($new_input['category'])) {
		$new_input['category'] = false;
	}

	// validate comment setting
	$new_input['comment'] = trim($input['comment']);
	if (! g_crossposting_check_comment($new_input['comment'])) {
		$new_input['comment'] = '';
	}

	// validate selected author
	$new_input['author'] = trim($input['author']);
	if (! g_crossposting_check_author($new_input['author'])) {
		$new_input['author'] = '';
	}

	return $new_input;
}

/**
 *  setting page, main purpose is get Google+ ID and Google API key
 */
function g_crossposting_conf() {
	// TODO make that work for all error messages
	global $g_crossposting_msg;
	if (is_wp_error($g_crossposting_msg)) {
		$err_messages = $g_crossposting_msg->get_error_messages();
		foreach ($err_messages as $err_message) {
			echo '<div id="message" class="updated fade"><p>'.$err_message.'</p></div>';
		}
	}
?>
<div class="wrap">
<h2><?php _e('Google+ Crossposting Configuration', 'g-crossposting'); ?></h2>
<form action="options.php" method="post">
<?php
	settings_fields('g_crossposting_options');
	do_settings_sections('g_crossposting');
?>
<p class="submit">
	<input type="submit" class='button-primary' name="Submit" value="<?php _e('Save Changes', 'g-crossposting') ?> &raquo;" />
</p>
</form>
<?php
	// add link to start manual mass import
	echo '<div id="g-crossposting-manual-import"><strong>';
	_e('Manual mass import:', 'g-crossposting');
	echo '</strong> <a href="?page=g-crossposting-key-config&amp;g_crossposting_manual_import=true">';
	_e('manually check for new activities and import them', 'g-crossposting');
	echo '</a></div>';

	echo '<div id="g-crossposting-manual-import-all"><strong>';
	_e('Manual mass import ALL:', 'g-crossposting');
	echo '</strong> <a href="?page=g-crossposting-key-config&amp;g_crossposting_manual_import_all=true">';
	_e('manually check for ALL activities and import them', 'g-crossposting');
	echo '</a></div>';
	echo '</div>';
}
