=== wp-featured2rss ===
Contributors: cadeyrn
Donate link: https://paypal.me/petermolnar/3
Tags: RSS, feed, featured image, attachment
Requires at least: 3.0
Tested up to: 4.4
Stable tag: 0.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Required minimum PHP version: 5.3

WordPress plugin to add a resized featured image to RSS feed as enclosure (which WordPress doesn't do by default)

== Description ==

For an unknown reason, WordPress doesn't adding featured images as enclosures to my RSS2 feed; this plugin is supposed to take care of that instead.

It's strictly only using resized images to maintain compatibility in case access to the full size image is blocked for various reasons (like not wanting people to be able to access it).

== Installation ==

1. Upload contents of `wp-featured2rss` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress

== Frequently Asked Questions ==

== Changelog ==

Version numbering logic:

* every A. indicates BIG changes.
* every .B version indicates new features.
* every ..C indicates bugfixes for A.B version.

= 0.1 =
*2015-12-14*

* initial public release
