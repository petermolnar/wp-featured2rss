<?php
/*
Plugin Name: wp-featured2rss
Plugin URI: https://github.com/petermolnar/wp-featured2rss
Description: WordPress plugin to add featured image to RSS feed as attachment (which WordPress doesn't do by default)
Version: 0.1.1
Author: Peter Molnar <hello@petermolnar.eu>
Author URI: http://petermolnar.eu/
License: GPLv3
Required minimum PHP version: 5.3
*/

/*  Copyright 2015 Peter Molnar ( hello@petermolnar.eu )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('WP_FEATURED2RSS')):

class WP_FEATURED2RSS {
	const expire = 30;

	public function __construct () {
		register_activation_hook( __FILE__ , array( &$this, 'plugin_activate' ) );
		add_action( 'rss2_item', array(&$this,'insert_enclosure_image') );
	}

	/**
	 * activate hook
	 */
	public static function plugin_activate() {
		if ( version_compare( phpversion(), 5.3, '<' ) ) {
			die( 'The minimum PHP version required for this plugin is 5.3' );
		}
	}

	/**
	 *
	 */
	public static function insert_enclosure_image ( ) {

		$post = static::fix_post();

		static::debug('insterting featured image to rss',7);

		if ($post === false )
			return false;

		$thid = get_post_thumbnail_id( $post->ID );
		if ( ! $thid )
			return false;

		if ( $cached = wp_cache_get ( $thid, __CLASS__ . __FUNCTION__ ) )
			return $cached;

		$asize = false;
		$meta = wp_get_attachment_metadata($thid);

		// creating sorted candidates array from available sizes,
		// sorted by width*height pixels
		$candidates = array();
		foreach( $meta['sizes'] as $potential => $details ) {
			if ( isset($details['width']) && isset($details['height']) ) {
				$max = ((int)$details['width']) * ((int)$details['height']);
				$candidates[ $potential ] = $max;
			}
		}
		arsort($candidates);

		foreach ($candidates as $potential => $maxsize ) {
			static::debug('checking size ' . $potential . ': ' . $meta['sizes'][$potential]['file'] . ' vs ' .$meta['file'], 7 );

			if (isset($meta['sizes'][$potential]) && isset($meta['sizes'][$potential]['file']) && $meta['sizes'][$potential]['file'] != $meta['file']) {
				static::debug( $meta['sizes'][$potential]['file'] . ' look like a resized file, using it', 7);
				$asize = $potential;
				$img = wp_get_attachment_image_src( $thid, $potential );
				break;
			}
		}

		if ( $asize == false )
			return false;

		$upload_dir = wp_upload_dir();
		// check for cached version of the image, in case the plugin is used
		// in tandem with [wp-resized2cache](https://github.com/petermolnar/wp-resized2rss)
		$cached = WP_CONTENT_DIR . '/cache/' . $meta['sizes'][$asize]['file'];
		$file = $upload_dir['basedir'] . '/' . $meta['sizes'][$asize]['file'];

		if ( file_exists($cached))
			$fsize = filesize($cached);
		elseif ( file_exists($file) )
			$fsize = filesize($file);
		else
			return false;

		$mime = $meta['sizes'][$asize]['mime-type'];
		$str = sprintf('<enclosure url="%s" type="%s" length="%s" />',static::fix_url($img[0]),$mime,$fsize);

		wp_cache_set ( $thid, $str, __CLASS__ . __FUNCTION__, static::expire );

		echo $str;
	}

	/**
	 * do everything to get the Post object
	 */
	public static function fix_post ( &$post = null ) {
		if ($post === null || !static::is_post($post))
			global $post;

		if (static::is_post($post))
			return $post;

		return false;
	}

	/**
	 * test if an object is actually a post
	 */
	public static function is_post ( &$post ) {
		if ( !empty($post) && is_object($post) && isset($post->ID) && !empty($post->ID) )
			return true;

		return false;
	}

	/**
	 *
	 * debug messages; will only work if WP_DEBUG is on
	 * or if the level is LOG_ERR, but that will kill the process
	 *
	 * @param string $message
	 * @param int $level
	 *
	 * @output log to syslog | wp_die on high level
	 * @return false on not taking action, true on log sent
	 */
	public static function debug( $message, $level = LOG_NOTICE ) {
		if ( empty( $message ) )
			return false;

		if ( @is_array( $message ) || @is_object ( $message ) )
			$message = json_encode($message);

		$levels = array (
			LOG_EMERG => 0, // system is unusable
			LOG_ALERT => 1, // Alert 	action must be taken immediately
			LOG_CRIT => 2, // Critical 	critical conditions
			LOG_ERR => 3, // Error 	error conditions
			LOG_WARNING => 4, // Warning 	warning conditions
			LOG_NOTICE => 5, // Notice 	normal but significant condition
			LOG_INFO => 6, // Informational 	informational messages
			LOG_DEBUG => 7, // Debug 	debug-level messages
		);

		// number for number based comparison
		// should work with the defines only, this is just a make-it-sure step
		$level_ = $levels [ $level ];

		// in case WordPress debug log has a minimum level
		if ( defined ( 'WP_DEBUG_LEVEL' ) ) {
			$wp_level = $levels [ WP_DEBUG_LEVEL ];
			if ( $level_ > $wp_level ) {
				return false;
			}
		}

		// ERR, CRIT, ALERT and EMERG
		if ( 3 >= $level_ ) {
			wp_die( '<h1>Error:</h1>' . '<p>' . $message . '</p>' );
			exit;
		}

		$trace = debug_backtrace();
		$caller = $trace[1];
		$parent = $caller['function'];

		if (isset($caller['class']))
			$parent = $caller['class'] . '::' . $parent;

		return error_log( "{$parent}: {$message}" );
	}

	/**
	 *
	 */
	public static function fix_url ( $url, $absolute = true ) {
		// move to generic scheme
		$url = str_replace ( array('http://', 'https://'), 'https://', $url );

		$domain = parse_url(get_bloginfo('url'), PHP_URL_HOST);
		// relative to absolute
		if ($absolute && !stristr($url, $domain)) {
			$url = 'https://' . $domain . '/' . ltrim($url, '/');
		}

		return $url;
	}

}

$WP_FEATURED2RSS = new WP_FEATURED2RSS();

endif;
