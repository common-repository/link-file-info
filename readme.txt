=== Plugin Name ===
Contributors: Tim Serong
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8580490
Tags: link, links, file, type, size
Requires at least: 2.8
Tested up to: 2.8.4
Stable tag: 0.5

Display file type and size after links to local media files in posts.

== Description ==

For posts, any links to local media (images, documents, etc.) will
automatically have the file type and size appended in parentheses, so
visitors to your blog will know what they're about to download when
they click links to media.

Notes:

* Requires PHP 5+
* Only applies for full posts, not comments or post excerpts.
* Specifically only works for media files uploaded using WordPress'
media upload function (i.e. files in the wp-uploads directory).
* Parses every post through DOMDocument on view, so unlikely to be
wildly efficient.
* Link parsing might be a bit rough - needs more extensive testing.
* Invokes the 'file' program to figure out file types, which gives
very pretty and accurate output, but again probably isn't very
efficient.

== Installation ==

1. Upload `link-file-info.php` to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to the Media Settings page in WordPress and make sure "Path to
'file' command" is set correctly for your system (it defaults to
/usr/bin/file).

== Changelog ==

= 0.5 =
* Map link URLs to file paths based on WordPress upload URL and
file path, rather than (unreliably) joining a (hopefully correct)
relative URL to the base path of the wordpress install.
* Display file size even if file type info unavailable.

= 0.4 =
* More reliable determination of hostname from site url

= 0.3 =
* Add option for path to 'file' command
* Add lfi_debug GET param to dump out ignored links as comments.

= 0.2 =
* Verify prerequisites (PHP5 + DOM extension) on plugin activation.

= 0.1 =
* Initial version
