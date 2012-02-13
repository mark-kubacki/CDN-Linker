<?php
/*
Plugin Name: CDN Linker
Plugin URI: https://github.com/wmark/CDN-Linker
Description: Replaces the blog URL by another for all files under <code>wp-content</code> and <code>wp-includes</code>. That way static content can be handled by a CDN by origin pull - the origin being your blog address - or loaded from an other site.
Version: 1.4.0
Author: W-Mark Kubacki
Author URI: http://mark.ossdl.de/
License: RPL for non-commercial
*/

if ( @include_once('cdn-linker-base.php') ) {
	add_action('template_redirect', 'do_ossdl_off_ob_start');
}

/********** WordPress Administrative ********/

function ossdl_off_activate() {
	add_option('ossdl_off_cdn_url', get_option('siteurl'));
	add_option('ossdl_off_include_dirs', 'wp-content,wp-includes');
	add_option('ossdl_off_exclude', '.php');
	add_option('ossdl_off_rootrelative', '');
	add_option('ossdl_off_www_is_optional', '');
}
register_activation_hook( __FILE__, 'ossdl_off_activate');

function ossdl_off_deactivate() {
	delete_option('ossdl_off_cdn_url');
	delete_option('ossdl_off_include_dirs');
	delete_option('ossdl_off_exclude');
	delete_option('ossdl_off_rootrelative');
	delete_option('ossdl_off_www_is_optional');
}
// register_deactivation_hook( __FILE__, 'ossdl_off_deactivate');
// Deactivated because: If the user activated this plugin again his previous settings would have been deleted by function.

/********** WordPress Interface ********/
add_action('admin_menu', 'ossdl_off_menu');

function ossdl_off_menu() {
	add_options_page('CDN Linker', 'CDN Linker', 8, __FILE__, 'ossdl_off_options');
}

function ossdl_off_options() {
	if ( isset($_POST['action']) && ( $_POST['action'] == 'update_ossdl_off' )){
		update_option('ossdl_off_cdn_url', $_POST['ossdl_off_cdn_url']);
		update_option('ossdl_off_include_dirs', $_POST['ossdl_off_include_dirs'] == '' ? 'wp-content,wp-includes' : $_POST['ossdl_off_include_dirs']);
		if(strstr(get_option('ossdl_off_exclude'), '.php')) {
			update_option('ossdl_off_exclude', $_POST['ossdl_off_exclude']);
		} else {
			// this forces '.php' being part of the list
			$excludes = array_map('trim', explode(',', $_POST['ossdl_off_exclude']));
			$excludes[] = '.php';
			update_option('ossdl_off_exclude', implode(',', $excludes));
		}
		update_option('ossdl_off_rootrelative', !!$_POST['ossdl_off_rootrelative']);
		update_option('ossdl_off_www_is_optional', !!$_POST['ossdl_off_www_is_optional']);
	}
	$example_cdn_uri = str_replace('http://', 'http://cdn.', str_replace('www.', '', get_option('siteurl')));

	?><div class="wrap">
		<h2>CDN Linker</h2>
		<p>Many Wordpress plugins misbehave when linking to their JS or CSS files, and yet there is no filter to let your old posts point to a statics' site or CDN for images.
		Therefore this plugin replaces at any links into <code>wp-content</code> and <code>wp-includes</code> directories (except for PHP files) the <code>blog_url</code> by the URL you provide below.
		That way you can either copy all the static content to a dedicated host or mirror the files at a CDN by <a href="http://knowledgelayer.softlayer.com/questions/365/How+does+Origin+Pull+work%3F" target="_blank">origin pull</a>.</p>
		<p><strong style="color: red">WARNING:</strong> Test some static urls e.g., <code><?php echo(get_option('ossdl_off_cdn_url') == get_option('siteurl') ? $example_cdn_uri : get_option('ossdl_off_cdn_url')); ?>/wp-includes/js/prototype.js</code> to ensure your CDN service is fully working before saving changes.</p>
		<p><form method="post" action="">
		<table class="form-table"><tbod>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_cdn_url">CDN URL</label></th>
				<td>
					<input type="text" name="ossdl_off_cdn_url" value="<?php echo(get_option('ossdl_off_cdn_url')); ?>" size="64" class="regular-text code" />
					<span class="description">The new URL to be used in place of <?php echo(get_option('siteurl')); ?> for rewriting. No trailing <code>/</code> please. E.g. <code><?php echo($example_cdn_uri); ?></code>.
					&mdash;
					You can use <code>%4%</code> (number between 1 and 9, surrounded by percent signs) to enable up to that many hostname variations.
					1 or less doesn't make sense and more than 4 is beyond optimum. If you are going to use 3 or more, then make sure they have different IPs or
					<a href="http://statichtml.com/2010/use-unique-ips-for-sharded-asset-hosts.html">some routers will block requests</a> to them.
					</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_rootrelative">rewrite root-relative refs</label></th>
				<td>
					<input type="checkbox" name="ossdl_off_rootrelative" <?php echo(!!get_option('ossdl_off_rootrelative') ? 'checked="1" ' : '') ?>value="true" class="regular-text code" />
					<span class="description">Check this if you want to have links like <code><em>/</em>wp-content/xyz.png</code> rewritten - i.e. without your blog's domain as prefix.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_www_is_optional">subdomain 'www' is optional</label></th>
				<td>
					<input type="checkbox" name="ossdl_off_www_is_optional" <?php echo(!!get_option('ossdl_off_www_is_optional') ? 'checked="1" ' : '') ?>value="true" class="regular-text code" />
					<span class="description">Check this if your blog can be accessed without a 'www' in front of its domain name. If unchecked, links with missing 'www' won't be modified.
					Safe to say 'yes' here.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_include_dirs">include dirs</label></th>
				<td>
					<input type="text" name="ossdl_off_include_dirs" value="<?php echo(get_option('ossdl_off_include_dirs')); ?>" size="64" class="regular-text code" />
					<span class="description">Directories to include in static file matching. Use a comma as the delimiter. Default is <code>wp-content, wp-includes</code>, which will be enforced if this field is left empty.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_exclude">exclude if substring</label></th>
				<td>
					<input type="text" name="ossdl_off_exclude" value="<?php echo(get_option('ossdl_off_exclude')); ?>" size="64" class="regular-text code" />
					<span class="description">Excludes something from being rewritten if one of the above strings is found in the match. Use a comma as the delimiter. E.g. <code>.php, .flv, .do</code>, always include <code>.php</code> (default and enforced if missing).</span>
				</td>
			</tr>
		</tbody></table>
		<input type="hidden" name="action" value="update_ossdl_off" />
		<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
		</form></p>
	</div><?php
}
