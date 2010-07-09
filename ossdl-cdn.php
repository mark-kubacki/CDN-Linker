<?php
/*
Plugin Name: OSSDL CDN off-linker
Plugin URI: http://mark.ossdl.de/2009/08/rewriting-urls-for-wordpress-and-cdn/
Description: Replaces the blog URL by another for all files under <code>wp-content</code> and <code>wp-includes</code>. That way static content can be handled by a CDN by origin pull - the origin being your blog address - or loaded from an other site.
Version: 1.1.0
Author: W-Mark Kubacki
Author URI: http://mark.ossdl.de/
*/

add_option('ossdl_off_cdn_url', get_option('siteurl'));
$ossdl_off_blog_url = get_option('siteurl');
$ossdl_off_cdn_url = trim(get_option('ossdl_off_cdn_url'));
add_option('ossdl_off_include_dirs');
$ossdl_off_include_dirs = trim(get_option('ossdl_off_include_dirs'));
addoption('ossdl_off_exclude', '.php');
$ossdl_off_exclude = trim(get_option('ossdl_off_exclude'));

$arr_of_excludes = array_map('trim', explode(',', $ossdl_off_exclude));

/**
 * Determines whether to exclude a match.
 *
 * @param String $match URI to examine
 * @param Array $excludes array of "badwords"
 * @return Boolean true if to exclude given match from rewriting
 */
function ossdl_off_exclude_match($match, $excludes) {
	foreach ($excludes as $badword) {
		if (stristr($match, $badword) != false) {
			return true;
		}
	}
	return false;
}

/**
 * Rewriter of URLs, used as replace-callback.
 *
 * Called by #ossdl_off_filter.
 */
function ossdl_off_rewriter($match) {
	global $ossdl_off_blog_url, $ossdl_off_cdn_url, $arr_of_excludes;
	if (ossdl_off_exclude_match($match[0], $arr_of_excludes)) {
		return $match[0];
	} else {
		return str_replace($ossdl_off_blog_url, $ossdl_off_cdn_url, $match[0]);
	}
}

/**
 * Creates a regexp compatible pattern from the directories to be included in matching.
 *
 * @return String with the pattern with {@literal |} as prefix, or empty
 */
function ossdl_off_additional_directories() {
	global $ossdl_off_include_dirs;
	$input = explode(',', $ossdl_off_include_dirs);
	if (count($input) < 1) {
		return '';
	} else {
		return '|'.implode('|', array_map('quotemeta', array_map('trim', $input)));
	}
}

/**
 * Output filter which runs the actual plugin logic.
 */
function ossdl_off_filter($content) {
	global $ossdl_off_blog_url, $ossdl_off_cdn_url;
	if ($ossdl_off_blog_url == $ossdl_off_cdn_url) { // no rewrite needed
		return $content;
	} else {
		$dirs = ossdl_off_additional_directories();
		$regex = '#(?<=[(\"\'])'.quotemeta($ossdl_off_blog_url).'(?:(/(?:wp\-content|wp\-includes'.$dirs.')[^\"\')]+)|(/[^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
		return preg_replace_callback($regex, 'ossdl_off_rewriter', $content);
	}
}

/**
 * Registers ossdl_off_filter as output buffer, if needed.
 */
function do_ossdl_off_ob_start() {
	global $ossdl_off_blog_url, $ossdl_off_cdn_url;
	if ($ossdl_off_blog_url != $ossdl_off_cdn_url) {
		ob_start('ossdl_off_filter');
	}
}

add_action('template_redirect', 'do_ossdl_off_ob_start');

/********** WordPress Administrative ********/
add_action('admin_menu', 'ossdl_off_menu');

function ossdl_off_menu() {
	add_options_page('OSSDL CDN off-linker', 'OSSDL CDN off-linker', 8, __FILE__, 'ossdl_off_options');
}

function ossdl_off_options() {
	if ( isset($_POST['action']) && ( $_POST['action'] == 'update_ossdl_off' )){
		update_option('ossdl_off_cdn_url', $_POST['ossdl_off_cdn_url']);
		update_option('ossdl_off_include_dirs', $_POST['ossdl_off_include_dirs']);
		update_option('ossdl_off_exclude', $_POST['ossdl_off_exclude']);
	}

	?><div class="wrap">
		<h2>OSSDL CDN off-linker</h2>
		<p>Many Wordpress plugins misbehave when linking to their JS or CSS files, and yet there is no filter to let your old posts point to a statics' site or CDN for images.
		Therefore this plugin replaces at any links into <code>wp-content</code> and <code>wp-includes</code> directories (except for PHP files) the <code>blog_url</code> by the URL you provide below.
		That way you can either copy all the static content to a dedicated host or mirror the files at a CDN by <a href="http://knowledgelayer.softlayer.com/questions/365/How+does+Origin+Pull+work%3F" target="_blank">origin pull</a>.</p>
		<p><strong style="color: red">WARNING:</strong> Test some static urls e.g., <code>http://static.mydomain.com/wp-includes/js/prototype.js</code> to ensure your CDN service is fully working before saving changes.</p>
		<p><form method="post" action="">
		<table class="form-table"><tbod>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_cdn_url">off-site URL</label></th>
				<td>
					<input type="text" name="ossdl_off_cdn_url" value="<?php echo get_option('ossdl_off_cdn_url'); ?>" size="64" class="regular-text code" />
					<span class="description">The new URL to be used in place of <?php echo get_option('siteurl'); ?> for rewriting. No trailing <code>/</code> please. E.g. <code><?php echo str_replace('http://', 'http://cdn.', str_replace('www.', '', get_option('siteurl'))); ?></code>.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_include_dirs">include dirs</label></th>
				<td>
					<input type="text" name="ossdl_off_include_dirs" value="<?php echo get_option('ossdl_off_include_dirs'); ?>" size="64" class="regular-text code" />
					<span class="setting-description">Directories to include in static file matching. Use a comma as the delimiter. E.g. <code>ads, audio</code> or leave blank to disable (default).</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_exclude">exclude if substring</label></th>
				<td>
					<input type="text" name="ossdl_off_exclude" value="<?php echo get_option('ossdl_off_exclude'); ?>" size="64" class="regular-text code" />
					<span class="setting-description">Excludes something from being rewritten if one of the above strings is found in the match. Use a comma as the delimiter. E.g. <code>.php, .flv, .do</code>, always include <code>.php</code> (default).</span>
				</td>
			</tr>
		</tbody></table>
		<input type="hidden" name="action" value="update_ossdl_off" />
		<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
		</form></p>
	</div><?php
}
