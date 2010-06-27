<?php
/*
Plugin Name: OSSDL CDN off-linker
Plugin URI: http://mark.ossdl.de/2009/08/rewriting-urls-for-wordpress-and-cdn/
Description: Replaces the blog URL by another for all files under <code>wp-content</code> and <code>wp-includes</code>. That way static content can be handled by a CDN by origin pull - the origin being your blog address - or loaded from an other site.
Version: 1.0.2
Author: W-Mark Kubacki
Author URI: http://mark.ossdl.de/
*/

add_option('ossdl_off_cdn_url', get_option('siteurl'));
$ossdl_off_blog_url = get_option('siteurl');
$ossdl_off_cdn_url = trim(get_option('ossdl_off_cdn_url'));

/**
 * Rewriter of URLs, used as replace-callback.
 */
function ossdl_off_rewriter($match) {
	global $ossdl_off_cdn_url;
	$pos = strrpos($match[0], ".php");

	if ($pos === false) {	// not linking to PHP, we can rewrite the URL
		return $ossdl_off_cdn_url.$match[1];
	} else {		// ... else, if it is a PHP, do...
		return $match[0];// nothing
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
		$regex = '#(?<=[(\"\'])'.quotemeta($ossdl_off_blog_url).'(?:(/(?:wp\-content|wp\-includes)[^\"\')]+)|(/[^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
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
}

?>
<div class="wrap">
<h2>OSSDL CDN off-linker</h2>
<p>Many Wordpress plugins misbehave when linking to their JS or CSS files, and yet there is no filter to let your old posts point to a statics' site or CDN for images.
Therefore this plugin replaces at any links into <code>wp-content</code> and <code>wp-includes</code> directories (except for PHP files) the <code>blog_url</code> by the URL you provide below.
That way you can either copy all the static content to a dedicated host or mirror the files at a CDN by <a href="http://knowledgelayer.softlayer.com/questions/365/How+does+Origin+Pull+work%3F" target="_blank">origin pull</a>.</p>
<p><strong style="color: red">WARNING:</strong> Test some static urls e.g., http://static.mydomain.com/wp-includes/js/prototype.js<br/>
to ensure your CDN service is fully working before saving changes.</p>
<p><form method="post" action="">
<table class="form-table">
<tr valign="top">
<th scope="row"><label for="ossdl_off_cdn_url">off-site URL</label></th>
<td><input type="text" name="ossdl_off_cdn_url" value="<?php echo get_option('ossdl_off_cdn_url'); ?>" size="64" /></td>
<td><span class="setting-description">The new URL to be used in place of <?php echo get_option('siteurl'); ?> for rewriting.</span></td>
</tr>
</table>
<input type="hidden" name="action" value="update_ossdl_off" />
<p class="submit">
<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</form></p>
</div>
<?php
}
