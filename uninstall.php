<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

// Deactivated because: If the user activated this plugin again his previous settings would have been deleted by this function.
/*
delete_option('ossdl_off_cdn_url');
delete_option('ossdl_off_include_dirs');
delete_option('ossdl_off_exclude');
delete_option('ossdl_off_rootrelative');
delete_option('ossdl_off_www_is_optional');
delete_option('ossdl_off_disable_cdnuris_if_https');
*/