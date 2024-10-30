<?php
/*
Plugin Name: Link File Info
Plugin URI: mailto:tim@wirejunkie.com?subject=Link%20File%20Info%20WordPress%20Plugin
Description: Display file type and size after links to local media files in posts.  Requires PHP 5.  Only applies for full posts, not comments or post excerpts .  Parses every post through DOMDocument on view, so unlikely to be wildly efficient.  Link parsing might be a bit rough - needs more extensive testing.  Invokes '/usr/bin/file' to figure out file types, which gives very pretty and accurate output, but again probably isn't very efficient.
Version: 0.5
Author: Tim Serong
Author URI: http://www.wirejunkie.com/
*/

/*
	This library is free software; you can redistribute it and/or
	modify it under the terms of the GNU Lesser General Public
	License as published by the Free Software Foundation; either
	version 2.1 of the License, or (at your option) any later version.

	This library is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	Lesser General Public License for more details.
*/

/*
 * @todo	see about making the 'file' output a bit prettier,
 * 			maybe strip everything after the first comma.  consider,
 * 			for example, this file info:
 * 
 * 				JPEG image data, JFIF standard 1.01
 * 				Microsoft ASF
 * 				TrueType font data
 * 				PDF document, version 1.4
 * 
 * 			who really cares about the crap after the comma?
 * 
 * @todo	option for file types to not show size & type for?
 * 
 * @todo	option for wrapper around text, eg: to specify <div> or
 * 			span to thus control display of file info.  maybe can
 * 			be done with document fragments and sprintf eg: allow
 * 			specification as '<span>%s</span>'.
 * 
 * @todo	even more flexible, allow specifier as ' (%t, %s) ' for
 * 			type, size (but problematic if one is missing - how to
 * 			know to remove the comma?)
 * 
 * @todo	figure out how to handle plugin upgrades - are they
 * 			automatically re-activated?  (need to cope with adding
 * 			new configuration options)
 * 
 */
if ( !class_exists( 'LinkFileTypesPlugin' ) ) {

	class LinkFileTypesPlugin
	{
		private $host;
		private $base_path;
		private $base_url;
		private $this_url;

		function LinkFileTypesPlugin() {
			register_activation_hook( __FILE__, array( &$this, 'activation_hook' ) );
			add_action( 'admin_init', array( &$this, 'admin_init') );
			add_filter( 'the_content', array( &$this, 'the_content' ) );

			// Get hostname of current site for use in URL parsing
			$site_url = parse_url( get_option( 'siteurl' ) );
			$this->host = $site_url['host'];

			// Get base URL & path for media files, and URL of
			// current page.
			$this->figure_out_base_path_and_url_for_media_files();
			$this->figure_out_this_url();
		}

		// Most of this is a copy of the first part of wp_upload_dir()
		// from wp-includes/functions.php.  Doing this should allow us
		// to reliably map URLs to file paths, even in the face of
		// things like mod_rewrite, with the restriction that it will
		// only work for local files uploaded via WordPress media
		// upload.  It will not work for links/files outside the
		// WordPress upload directory (but that's probably OK, because
		// that's exactly the set of files this plugin was intended
		// to deal with anyway).
		function figure_out_base_path_and_url_for_media_files() {
			$siteurl = get_option( 'siteurl' );
			$upload_path = get_option( 'upload_path' );
			$upload_path = trim($upload_path);
			if ( empty($upload_path) )
				$dir = WP_CONTENT_DIR . '/uploads';
			else
				$dir = $upload_path;

			// $dir is absolute, $path is (maybe) relative to ABSPATH
			$dir = path_join( ABSPATH, $dir );

			if ( !$url = get_option( 'upload_url_path' ) ) {
				if ( empty($upload_path) or ( $upload_path == $dir ) )
					$url = WP_CONTENT_URL . '/uploads';
				else
					$url = trailingslashit( $siteurl ) . $upload_path;
			}

			if ( defined('UPLOADS') ) {
				$dir = ABSPATH . UPLOADS;
				$url = trailingslashit( $siteurl ) . UPLOADS;
			}

			$this->base_path = $dir;
			$this->base_url = $url;
		}

		function figure_out_this_url() {
			$schema = ( isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
			$q = strpos( $_SERVER['REQUEST_URI'], '?' );
			$uri = ( $q === false ? $_SERVER['REQUEST_URI'] : substr( $_SERVER['REQUEST_URI'], 0, $q ) );
			$this->this_url = $schema . $_SERVER['HTTP_HOST'] . $uri;
		}

		// Cribbed from http://us.php.net/manual/en/function.filesize.php,
		// comment from "nak5ive at DONT-SPAM-ME dot gmail dot com" dated
		// 11-Jun-2009 05:59
		function format_bytes ( $bytes, $precision = 1 ) {
			$units = array( 'Bytes', 'KB', 'MB', 'GB', 'TB' );

			$bytes = max( $bytes, 0 );
			$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow = min( $pow, count( $units ) - 1 );

			$bytes /= pow( 1024, $pow);

			return round( $bytes, $precision) . ' ' . $units[$pow];
		}

		function activation_hook() {
			if ( version_compare( PHP_VERSION, '5.0.0', '<' ) || !class_exists('domDocument') ) {
				// Have to force deactivate, because the plugin has
				// already been set active at this point.
				deactivate_plugins(__FILE__);
				trigger_error('This plugin requires PHP 5 or newer with the DOM extension (http://www.php.net/manual/en/book.dom.php)', E_USER_ERROR );
			}

			add_option( 'lfi_path_to_file_cmd', '/usr/bin/file' );
		}

		function admin_init() {
			// Add this plugin's settings to the media page
			add_settings_section( 'link-file-info', 'Link File Info Settings', array( &$this, 'settings_section' ), 'media' );
			add_settings_field( 'lfi_path_to_file_cmd', 'Path to \'file\' command', array( &$this, 'settings_field_path_to_file_cmd' ), 'media', 'link-file-info' );			
			register_setting( 'media', 'lfi_path_to_file_cmd' );
		}

		function settings_section() {
?>
<p>These settings control how file metadata is inserted into posts by the Link File Info plugin.</p>
<?php
		}

		function settings_field_path_to_file_cmd() {
?>
<input id="lfi_path_to_file_cmd" name="lfi_path_to_file_cmd" type="text" value="<?php echo form_option( 'lfi_path_to_file_cmd' ); ?>" /> (executed to determine link file type - leave blank to only show file size)
<?php
		}

		function the_content( $content ) {
			$file_cmd = get_option( 'lfi_path_to_file_cmd' );
			if ( !empty($file_cmd) && !is_executable( $file_cmd ) ) {
				error_log( "link-file-info plugin not processing post ('$file_cmd' is not executable)" );
				$file_cmd = '';	// Don't try to execute it later
			}

			$dom = new domDocument;
			$dom->loadHTML( $content );
			$doc  = $dom->documentElement;
			$links = $dom->getElementsByTagName( 'a' );

			foreach ( $links as $link )
			{
				$href = $link->getAttribute( 'href' );
				if ( !$href )
					continue;

				$url = parse_url( $href );
				if ( array_key_exists( 'host', $url ) ) {
					// absolute URL, use it as-is
					$url = $href;
				} else {
					// relative URL, make it absolute:
					$url = trailingslashit( $this->this_url ) . ltrim( $href, '/' );
				}

				$len = strlen($this->base_url);
				if ( strncasecmp( $url, $this->base_url, $len ) == 0 ) {
					// It's a URL to a media file on our site (base_url for
					// file uploads matches) so strip base_url off the
					// absolute URL, and append it to the base file path
					// for media files to get the actual file location.
					$file_path = path_join( trailingslashit( $this->base_path ), ltrim( substr( $url, $len ), '/' ) );
					if ( file_exists( $file_path ) ) {
						// Bingo!
						$info = array();
						if ( !empty( $file_cmd ) )
							$info[] = exec( escapeshellcmd( $file_cmd ) . ' -b ' . escapeshellarg( $file_path ) );
						$info[] = $this->format_bytes( filesize( $file_path ) );
						$info_str = ' (' . implode( ', ', $info ) . ') ';
						$description = $dom->createTextNode( $info_str );
						if ( $link == $link->parentNode->lastChild )
							$link->parentNode->appendChild( $description );
						else
							$link->parentNode->insertBefore( $description, $link->nextSibling );
					}
				} else {
					if ( isset( $_GET['lfi_debug'] ) )
						echo "\n<!--\nlink-file-info ignoring link '$href':\n" . print_r($url, true) . " -->\n";
				}
			}

			// We end up with implicit HTML & BODY elements, need to
			// get rid of this before output.  Emitting only the BODY
			// element gets us part the way there...
			$body = $doc->getElementsByTagName( 'body' )->item(0);
			$html = $dom->saveXML( $body );
			// ...now $html is "<body>....</body>", so return everything
			// between the first '>' and last '<' characters.
			$first = strpos( $html, '>' );
			$last  = strrpos( $html, '<' );
			$first++;	// Start one past the first '>'.
			return substr( $html, $first, $last - $first );
		}
	}
}

if ( class_exists( 'LinkFileTypesPlugin' ) ) {
	$link_file_types_plugin = new LinkFileTypesPlugin();
}

?>
