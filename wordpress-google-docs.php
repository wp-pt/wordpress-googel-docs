<?php
 
/**
 * Plugin Name: WordPress Google Docs
 * Description: Import Google Docs documents.
 * Version: 1.0.1
 * Author: Zorca
 * Author URI: https://zorca.org
 */
 
if ( ! defined( 'ABSPATH' ) ) { 
	exit; // Exit if accessed directly
}

/**
 * Load text domain
 **/
function bt_wpgd_load_textdomain() {
	load_plugin_textdomain( 'wordpress-google-docs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'bt_wpgd_load_textdomain' );

function bt_wpgd_enqueue() {
	$screen = get_current_screen();
	if ( $screen->base != 'toplevel_page_bt_wpgd_import' ) {
        return;
    }
	wp_enqueue_style( 'bt_wpgd_fa', plugins_url( 'css/font-awesome.min.css', __FILE__ ) );
	wp_enqueue_style( 'bt_wpgd_main', plugins_url( 'css/main.min.css', __FILE__ ) );
	wp_enqueue_script( 'bt_wpgd_script', plugins_url( 'script.js', __FILE__ ), array( 'jquery' ), true );
}
add_action( 'admin_enqueue_scripts', 'bt_wpgd_enqueue' );

function bt_wpgd_get_service() {
	require_once 'Google/autoload.php';

	$options = get_option( 'bt_wpgd_settings' );
	$client_id = isset( $options['client_id'] ) ? $options['client_id'] : '';
	$client_secret = isset( $options['client_secret'] ) ? $options['client_secret'] : '';
	$auth_code = isset( $options['auth_code'] ) ? $options['auth_code'] : '';
	
	$access_token = get_option( 'bt_wpgd_access_token' );
	$access_token = $access_token !== false ? $access_token : '';
	
	$client = new Google_Client();
	$client->setClientId( $client_id );
	$client->setClientSecret( $client_secret );
	$client->setRedirectUri( 'urn:ietf:wg:oauth:2.0:oob' );
	$client->setScopes( array( 'https://www.googleapis.com/auth/drive' ) );
	$client->setAccessType( 'offline' );
	
	if ( $access_token == '' ) {
		$client->authenticate( $auth_code );
		$access_token = $client->getAccessToken();
		update_option( 'bt_wpgd_access_token', $access_token );
	} else {
		$client->setAccessToken( $access_token );
	}

	$service = new Google_Service_Drive( $client );
	
	return $service;
}

function bt_wpgd_get_files() {

	$service = bt_wpgd_get_service();
	
	$id = $_POST['id'];
	$sort_by = $_POST['sort_by'];
	$sort_direction = $_POST['sort_direction'];
	
	$files = retrieveAllFiles( $service, $id, $sort_by, $sort_direction );
	
	foreach( $files as $f ) {
		if ( $f['mimeType'] == 'application/vnd.google-apps.folder' ) {
			echo '<tr class="bt_wpgd_item" data-type="folder" data-id="' . $f['id'] . '" data-name="' . $f['name'] . '"><td><i class="fa fa-folder-o" aria-hidden="true"></i><b>' . $f['name'] . '</b></td><td></td></tr>';
		} else {
			echo '<tr class="bt_wpgd_item" data-type="doc" data-id="' . $f['id'] . '" data-name="' . $f['name'] . '">';
			echo '<td><i class="fa fa-file-text-o" aria-hidden="true"></i>' . $f['name'] . '</td>';
			echo '<td>' . date_i18n( get_option( 'date_format' ), strtotime( $f['modifiedTime'] ) ) . '</td>';
			echo '</tr>';
		}
	}
	
	if ( count( $files ) == 0 ) {
		echo '<tr><td>&nbsp;</td></tr>';
	}

	die();
}
add_action( 'wp_ajax_bt_wpgd_get_files', 'bt_wpgd_get_files' );

// import file
function bt_wpgd_import_file() {

    require 'HTMLPurifier/HTMLPurifier.standalone.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('CSS.AllowedProperties', array());
    $config->set('AutoFormat.RemoveEmpty', true);
    $config->set('AutoFormat.RemoveEmpty.Predicate', [ 'table' => [] ]);
    $config->set('AutoFormat.RemoveSpansWithoutAttributes', true);
    $purifier = new HTMLPurifier($config);

	$service = bt_wpgd_get_service();
	
	$id = $_POST['id'];
	$name = $_POST['name'];
	$type = $_POST['type'];
	//$clear_style = $_POST['clear_style'];
	
	$response = $service->files->export($id, 'text/html', array('alt' => 'media'));
    $content = $response->getBody()->getContents();
	$content = $purifier->purify($content);
	
	$post_id = wp_insert_post( array( 'post_title' => $name, 'post_content' => $content, 'post_type' => $type ) );
    update_post_meta($post_id,'_google_docs_id', $id);

	die();
}
add_action( 'wp_ajax_bt_wpgd_import_file', 'bt_wpgd_import_file' );

if ( isset( $_GET['bt_wpgd_auth'] ) ) {
	
	$options = get_option( 'bt_wpgd_settings' );
	$client_id = isset( $options['client_id'] ) ? $options['client_id'] : '';
	$client_secret = isset( $options['client_secret'] ) ? $options['client_secret'] : '';	
	
	require_once 'Google/autoload.php';
	$client = new Google_Client();
	$client->setClientId( $client_id );
	$client->setClientSecret( $client_secret );
	$client->setRedirectUri( 'urn:ietf:wg:oauth:2.0:oob' );
	$client->setScopes( array( 'https://www.googleapis.com/auth/drive' ) );
	$client->setAccessType( 'offline' );
	delete_option( 'bt_wpgd_access_token' );
    header( 'Location: ' . $client->createAuthUrl() );
    die();
}

function bt_wpgd_admin_init() {
    register_setting( 'bt_wpgd_settings', 'bt_wpgd_settings' );
}
add_action( 'admin_init', 'bt_wpgd_admin_init' );

/**
 * Settings menu
 */
function bt_wpgd_menu() {
	add_options_page( __( 'WordPress Google Docs Settings', 'wordpress-google-docs' ), __( 'WP Google Docs', 'wordpress-google-docs' ), 'manage_options', 'bt_wpgd_settings', 'bt_wpgd_settings_callback' );
}
add_action( 'admin_menu', 'bt_wpgd_menu' );

/**
 * Import menu
 */
function bt_wpgd_import() {
	add_menu_page( __( 'WordPress Google Docs', 'wordpress-google-docs' ), __( 'WP Google Docs', 'wordpress-google-docs' ), 'manage_options', 'bt_wpgd_import', 'bt_wpgd_import_callback' );
}
add_action( 'admin_menu', 'bt_wpgd_import' );

/**
 * Settings page callback
 */
function bt_wpgd_settings_callback() {
	
	$options = get_option( 'bt_wpgd_settings' );
	$client_id = isset( $options['client_id'] ) ? $options['client_id'] : '';
	$client_secret = isset( $options['client_secret'] ) ? $options['client_secret'] : '';
	$auth_code = isset( $options['auth_code'] ) ? $options['auth_code'] : '';
	
	$auth_url = 'http' . ( isset( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . esc_url( add_query_arg( array( 'bt_wpgd_auth' => '' ) ) );

	?>
		<div class="wrap">
			<h2><?php _e( 'WordPress Google Docs Settings', 'wordpress-google-docs' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'bt_wpgd_settings' ); ?>
				<table class="form-table">
					<tbody>				
						<tr>
							<th scope="row"><label for="bt_wpgd_settings[client_id]"><?php _e( 'Google OAuth Client ID', 'wordpress-google-docs' ); ?></label></th>
							<td><input name="bt_wpgd_settings[client_id]" type="text" value="<?php echo $client_id; ?>" class="regular-text ltr">
							<p class="description"><a href="https://console.developers.google.com" target="_blank"><?php _e( 'Go to Google Developers Console to obtain client ID.', 'wordpress-google-docs' ); ?></a></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="bt_wpgd_settings[client_secret]"><?php _e( 'Google OAuth Client Secret', 'wordpress-google-docs' ); ?></label></th>
							<td><input name="bt_wpgd_settings[client_secret]" type="text" value="<?php echo $client_secret; ?>" class="regular-text ltr">
							<p class="description"><a href="https://console.developers.google.com" target="_blank"><?php _e( 'Go to Google Developers Console to obtain client secret.', 'wordpress-google-docs' ); ?></a></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="bt_wpgd_settings[auth_code]"><?php _e( 'Authorization Code', 'wordpress-google-docs' ); ?></label></th>
							<td><input name="bt_wpgd_settings[auth_code]" type="text" value="<?php echo $auth_code; ?>" class="regular-text ltr">
							<p class="description"><a href="<?php echo $auth_url; ?>" target="_blank"><?php _e( 'Click to obtain authorization code <b>(Client ID and Client Secret must be entered and saved first)</b>.', 'wordpress-google-docs' ); ?></a></p></td>
						</tr>
					</tbody>
				</table>

				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save', 'wordpress-google-docs' ); ?>"></p>
			</form>
		</div>
	<?php

}

/**
 * Import page callback
 */
function bt_wpgd_import_callback() {
	?>
		<div class="wrap">
			<h2><?php _e( 'WordPress Google Docs', 'wordpress-google-docs' ); ?></h2>
			
			<div class="bt_wpgd_wrap" data-label-importing="<?php _e( 'Importing, please wait...', 'wordpress-google-docs' ); ?>" data-label-finished="<?php _e( 'Finished!', 'wordpress-google-docs' ); ?>" data-label-not-selected="<?php _e( 'Please select document.', 'wordpress-google-docs' ); ?>">
			
				<div class="bt_wpgd_toolbar">
					<input type="submit" class="button bt_wpgd_home" value="<?php _e( 'Home', 'wordpress-google-docs' ); ?>"><label for="bt_wpgd_clear_inline_style"><input id="bt_wpgd_clear_inline_style" type="checkbox"><?php _e( 'Clear inline style', 'wordpress-google-docs' ); ?></label>
					<input type="submit" class="button bt_wpgd_import_page" value="<?php _e( 'Import as page', 'wordpress-google-docs' ); ?>">
					<input type="submit" class="button bt_wpgd_import_post" value="<?php _e( 'Import as post', 'wordpress-google-docs' ); ?>">
					<span class="bt_wpgd_message"></span>
				</div>
				
				<table class="bt_wpgd_output">
					<thead>
						<th class="bt_wpgd_sort_name" data-sort=""><?php _e( 'Name', 'wordpress-google-docs' ); ?> <span></span></th>
						<th class="bt_wpgd_sort_date" data-sort="desc"><?php _e( 'Last modified ', 'wordpress-google-docs' ); ?> <span><i class="fa fa-long-arrow-down"></i></span></th>
					</thead>
					<tbody></tbody>
				</table>
				
				<div class="bt_wpgd_loader"><?php _e( 'Loading...', 'wordpress-google-docs' ); ?></div>
			
			</div>
			
		</div>
	<?php
}

/**
 * Retrieve a list of File resources.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @return Array List of Google_Service_Drive_DriveFile resources.
 */
function retrieveAllFiles( $service, $id, $sort_by, $sort_direction ) {
	$result = array();
	$pageToken = NULL;
	
	if ( $sort_by == 'name' ) {
		$order = 'folder,name ' . $sort_direction . ',modifiedTime';
	} else {
		$order = 'folder,modifiedTime ' . $sort_direction . ',name';
	}
	
	do {
		try {
			//pageSize, pageToken
			$parameters = array( 
				'q' => "'" . $id . "' in parents and trashed = false and (mimeType = 'application/vnd.google-apps.document' or mimeType = 'application/vnd.google-apps.folder')",
				'orderBy' => $order,
				'fields' => 'files(id, name, modifiedTime, mimeType)'
			);
			//$parameters = array( 'q' => "trashed = false and (mimeType = 'application/vnd.google-apps.document' or mimeType = 'application/vnd.google-apps.folder')" );
			if ( $pageToken ) {
				$parameters['pageToken'] = $pageToken;
			}
			$files = $service->files->listFiles( $parameters );

			$result = array_merge( $result, $files->getFiles() );
			$pageToken = $files->getNextPageToken();
		} catch ( Exception $e ) {
			print "An error occurred: " . $e->getMessage();
			$pageToken = NULL;
		}
	} while ( $pageToken );
	return $result;
}