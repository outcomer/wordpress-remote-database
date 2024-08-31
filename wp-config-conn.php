<?php
/**
 * Let's say you have local WordPress App that works with database that you just do not want to make local copy of.
 * If remote database require SSH to connect you must rise remote connection to this DB from local machine with SSH tunnel.
 * !! If remote database does not require SSH to connect just skip this step.
 *
 * SSH:
 * Example: plink.exe -ssh user@host -pw "******" -P remote_port -i "path to private key.ppk" -N -L 5555:127.0.0.1:3306.
 *
 * A little bit about what this means:
 * - the -N flag means that when connecting via SSH, we are not going to execute any commands.
 * - the -L flag tells SSH that we are going to port forward.
 *
 * The following portion, 5555:127.0.0.1:3306 combined with the -L flag means, literally,
 * forward all traffic on localhost (127.0.0.1) connecting on port 5555 to the remote server’s
 * port 3306 (standard MySQL listening port).
 *
 * Same effect you can get using some MySQL client, let's say Heidi SQL, Dbeaver etc.
 * 5555 port can be any - it is up to you.
 *
 * !!! And of cause SSH tunnel must be active all the time you are working with site locally.
 *
 * From now once you open your local site WP will redirect you to your real site.
 * And if you are running WP Multisite - you even will get "Error database connection".
 * This code portion solve it. It must be intergated into you local wp-config.php file.
 *
 * USAGE:
 * - in you wp-config.php include this script before defining consts DB_NAME, DB_USER, DB_PASSWORD and DB_HOST.
 * - replace definition values with this script result.
 *
 * Final code after replacement:
 *
 * $credentials = require_once 'wp-config-conn.php';
 *
 * define( 'DB_NAME', $credentials->db_name );
 * define( 'DB_USER', $credentials->db_user );
 * define( 'DB_PASSWORD', $credentials->db_pass );
 * define( 'DB_HOST', $credentials->db_host );
 *
 * And if you are running multisite define DOMAIN_CURRENT_SITE like this:
 * define( 'DOMAIN_CURRENT_SITE', $domain_local );
 *
 * Below small part of snippet that you can customize for your connections.
 *
 * @author Outcomer <773021792e@gmail.com>
 */

$domain_local = 'you-local-domain-name';

$conn_1 = (object) [
	'db_name' => 'NAME',
	'db_user' => 'USER',
	'db_pass' => 'PASS',
	'db_host' => 'HOST:PORT',
	'domain'  => $domain_local,
];

$conn_2 = (object) [
	'db_name' => 'NAME',
	'db_user' => 'USER',
	'db_pass' => 'PASS',
	// -> port used in SSH tunnel or standart port (3306) if SSH not required.
	'db_host' => 'HOST:PORT',
	'domain'  => 'you-real-site-domain-name',
];

$active_conn = $conn_2;

/* Stop edit here */

if ( $active_conn->domain === $domain_local ) {
	return $active_conn;
}

require_once __DIR__ . '/wp-includes/plugin.php';

define( 'WP_HOME', "https://{$domain_local}" );
define( 'WP_SITEURL', "https://{$domain_local}" );

/**
 * Change components of WP_Site_Query.
 *
 * @param WP_Site_Query $query The WP_Site_Query instance (passed by reference).
 */
$parse_site_query = function( WP_Site_Query &$query ) use ( $active_conn ) {
	$query->query_vars['domain'] = $active_conn->domain;
};

/**
 * Replace homeurl at runtime.
 *
 * @param string $value  The URI for themes directory.
 * @param string $option WordPress web address which is set in General Options.
 */
$option_home = function( mixed $value, string $option ) use ( $domain_local ) {
	$parsedUrl = parse_url($value) ?: [];

	if (!isset($parsedUrl['host'])) {
		return $value;
	}

	$parsedUrl['host'] = $domain_local;

	return "https://{$domain_local}" . ($parsedUrl['path'] ?? '');
};

/**
 * Replace siteurl at runtime.
 *
 * @param string $value  The URI for themes directory.
 * @param string $option WordPress web address which is set in General Options.
 */
$option_siteurl = function( mixed $value, string $option ) use ( $domain_local ) {
	$parsedUrl = parse_url($value) ?: [];

	if (!isset($parsedUrl['host'])) {
		return $value;
	}

	$parsedUrl['host'] = $domain_local;

	return "https://{$domain_local}" . ($parsedUrl['path'] ?? '');
};

/**
 * Filters an image's 'srcset' sources.
 *
 * @since 4.4.0
 *
 * @param array  $sources       One or more arrays of source data to include in the 'srcset'.
 * @param array  $size_array    An array of requested width and height values.
 * @param string $image_src     The 'src' of the image.
 * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
 * @param int    $attachment_id Image attachment ID or 0.
 */
$wp_calculate_image_srcset = function ( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ) use ( $active_conn ) {

	foreach ( $sources as &$source ) {
		$host          = wp_parse_url( $source['url'], PHP_URL_HOST );
		$source['url'] = str_replace( $host, $active_conn->domain, $source['url'] );
	}

	return $sources;
};

/**
 * Replace domain in attchments at runtime.
 *
 * @since 2.1.0
 *
 * @param string $url           URL for the given attachment.
 * @param int    $attachment_id Attachment post ID.
 */
$wp_get_attachment_url = function ( string $url, int $attachment_id ) use ( $active_conn ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	return str_replace( $host, $active_conn->domain, $url );
};

add_action( 'parse_site_query', $parse_site_query );
add_filter( 'option_home', $option_home, 10, 2 );
add_filter( 'option_siteurl', $option_siteurl, 10, 2 );
add_filter( 'wp_calculate_image_srcset', $wp_calculate_image_srcset, 10, 5 );
add_filter( 'wp_get_attachment_url', $wp_get_attachment_url, 10, 2 );

return $active_conn;
