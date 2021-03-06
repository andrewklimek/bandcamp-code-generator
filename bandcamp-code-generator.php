<?php
namespace ssbandcampcodes;
/*
Plugin Name: Bandcamp Code Generator
Plugin URI:  
Description: 
Version:     1.0
Author:      Andrew J Klimek
Author URI:  https://github.com/andrewklimek
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

register_activation_hook( __FILE__, __NAMESPACE__.'\activation' );

add_shortcode( 'bccodes-import', __NAMESPACE__.'\bccodes_import' );
add_shortcode( 'bccodes', __NAMESPACE__.'\bccodes_generate' );

function bccodes_generate( $a )
{
	if ( ! current_user_can( 'manage_options' ) ) return;

	global $wpdb;
	$html = '';

	if ( !empty( $_POST['releases'] ) )
	{
		$html .= "<textarea style='height:20em;width:100%' readonly onfocus='this.select()'>";
		foreach( $_POST['releases'] as $r )
		{
			$code_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ssbccodes WHERE album='{$r}' LIMIT 1");
			$wpdb->delete( "{$wpdb->prefix}ssbccodes", [ 'id' => $code_row->id ] );
			$html .= "{$code_row->artist}\n{$code_row->album}\nhttps://bandcamp.com/yum?code={$code_row->code}\n\n";
		}
		$html .= "</textarea><br>";
	}


	$releases = $wpdb->get_results("SELECT artist, album, cat FROM {$wpdb->prefix}ssbccodes GROUP BY album ORDER BY id DESC");

	$html .= "<form id=pick-releases method=post>";
	$html .= "<p><button>submit</button></p>";
	$html .= "<input type=checkbox onclick=\"var c=this.checked;document.querySelectorAll('#pick-releases input').forEach(function(e){e.checked=c});\"> all<br>";
	$i = 1;
	foreach ( $releases as $release )
	{
		$checked =  !empty( $_POST['releases'] ) && isset( $_POST['releases'][$i] ) ? " checked" : "";
		$html .= "<label for=release-{$i}><input type=checkbox id=release-{$i} name=releases[{$i}] value='{$release->album}'{$checked}> {$release->album} ({$release->artist})</label><br>";
		++$i;
	}
	$html .= "<p><button>submit</button>";

	$html .= "</form>";

	return $html;
}

function bccodes_import( $a )
{
	
	if ( ! current_user_can( 'manage_options' ) ) return;
	
	$form = '
	<form method=post enctype=multipart/form-data>
		<p><label for=artist>artist</label>
		<input id=artist name=artist autofocus>
		<p><label for=cat>cat no<label>
		<input id=cat name=cat>
		<p><label for=codes>codes csv</label>
		<input type=file id=codes name=codes>
		<p><input type=submit value=Import name=submit>
	</form>
	';
	
	if ( empty($_FILES) ) return $form;	
	
	global $wpdb;
	$html = '';
	$success_count = 0;
	$album = '';
	$artist = filter_input( INPUT_POST, 'artist', FILTER_SANITIZE_STRING );
	$cat = filter_input( INPUT_POST, 'cat', FILTER_SANITIZE_STRING );
	$db = [];
	if ( $artist ) $db['artist'] = $artist;
	if ( $cat ) $db['cat'] = $cat;
	
	// make an array out of the uploaded file
	$codes = file($_FILES['codes']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	
	foreach ( $codes as $code )
	{
		if ( 9 !== strlen($code) )
		{
			if ( ! $album && substr( $code, 0, 7 ) === "album: " )
			{
				$album = substr( $code, 7 );
				$db['album'] = $album;
			}
			continue;
		}
		if ( false === $wpdb->insert( $wpdb->prefix . "ssbccodes", [ 'code' => $code ] + $db ) )
		{
			$html .= "<p>encountered an error on “{$code}”</p>";
		}
		else
		{
			$success_count += 1;
		}
	}
	
	$html .= "<p>{$_FILES['codes']['name']} finished. <br>{$success_count} codes added for $album";
	
	return $html . $form;
}


function activation() {
	create_database();
}

function create_database() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );// to use dbDelta()
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE {$wpdb->prefix}ssbccodes (
	id bigint(20) unsigned NOT NULL auto_increment,
	code char(9),
	album varchar(255),
	artist varchar(255),
	cat tinyint unsigned,
	PRIMARY KEY (id),
	INDEX (album)
	) ENGINE=InnoDB $charset_collate;" );

}