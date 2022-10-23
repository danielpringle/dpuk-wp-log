<?php

/*
Plugin Name: DPUK WP-Log
Plugin URI: danielpringle.co.uk
Description: Developing a activity logger for WordPress
Author: Daniel Pringle
Version: 1.7.2
Author URI: danielpringle.co.uk
Text Domain: dpuk-wp-log
*/


/**
 * Write an entry to a log file in the uploads directory.
 * 
 * @since 1.0.0
 * 
 * @param mixed $entry String or array of the information to write to the log.
 * @param string $file Optional. The file basename for the .log file.
 * @param string $mode Optional. The type of write. See 'mode' at https://www.php.net/manual/en/function.fopen.php.
 * @return boolean|int Number of bytes written to the lof file, false otherwise.
 */
if ( ! function_exists( 'plugin_log' ) ) {
    function plugin_log( $entry, $mode = 'a', $file = 'plugin' ) { 
      // Get WordPress uploads directory.
      $upload_dir = wp_upload_dir();
      $upload_dir = $upload_dir['basedir'];
      // If the entry is array, json_encode.
      if ( is_array( $entry ) ) { 
        $entry = json_encode( $entry ); 
      } 
      // Write the log file.
      $file  = $upload_dir . '/' . $file . '.log';
      $file  = fopen( $file, $mode );
      $bytes = fwrite( $file, current_time( 'mysql' ) . "::" . $entry . "\n" ); 
      fclose( $file ); 
      return $bytes;
    }
  }

/**
 * Example data that can be passed to the log. 
 *
 *   // Append an entry to the uploads/plugin.log file.
 *   plugin_log( 'Something happened.' );
 *   // Append an array entry to the uploads/plugin.log file.
 *   plugin_log( ['new_user' => 'dpuk' ] );
 *   // Write an entry to the uploads/plugin.log file, deleting the existing entries.
 *  plugin_log( 'Awesome sauce.', 'w' );
 *  // Append an entry to a different log file in the uploads directory.
 *   plugin_log( 'Simple stuff.', 'a', 'simple-stuff' );
 *
*/

/**
 * Add admin menu
 */
function be_plugin_log_viewer_menu() {

    add_management_page(
        'Log viewer',
        'Log viewer',
        'manage_options',
        'dpuk-wp-log',
        'be_plugin_log_viewer'
    );
}
add_action('admin_menu', 'be_plugin_log_viewer_menu');

/**
 *  Callback function to display log
 */
function be_plugin_log_viewer() {

    
    echo '<div class="wrap">';
    echo '<h1>Log viewer</h1>';
    
    $logFile = WP_CONTENT_DIR. '/uploads/plugin.log';

    $file_size = pretty_file_size($logFile);

    if (isset($_POST['command']) && $_POST['command'] == 'CLEAR') {
        
        file_put_contents($logFile, '');
        
        $current_user = wp_get_current_user();
        error_log('Log is erased (' . $current_user->user_login . ' - ' . $current_user->user_email . ')');
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . __('Log erased.', 'dpuk-wp-log') . '</p>';
        echo '</div>';
    }

    if (! empty($logFile) && filesize($logFile) > 0) {
      
        echo '<p>' . __('Viewing file: ', 'dpuk-wp-log') . basename($logFile) . ' (' . $file_size . ') ' . '</br> ' . $logFile . '. <a href="">' . __('Click to update', 'dpuk-wp-log') . '</a>.</p>';
        
        echo '<pre class="log">';
        // echo $lines;
        $myfile = fopen($logFile, 'r') or die(__('Unable to open file!', 'dpuk-wp-log'));
        echo fread($myfile, filesize($logFile));
        fclose($myfile);
        echo '</pre>';
        
        echo '<form method="post" action="" novalidate="novalidate" onsubmit="return confirm(\'' . __('You are about to erase the file.', 'dpuk-wp-log') . '\\n\\n' . __('Are you sure?', 'dpuk-wp-log') . '\');">';
        echo '<input type="hidden" name="command" id="command" value="CLEAR"></input>';
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" id="submit" class="button button-secondary" value="' . __('Erase log file', 'dpuk-wp-log') . '">';
        echo '</p>';
        echo '</form>';
    } else {
        
        echo '<div class="notice notice-error">';
        echo '<p>' . __('Viewer error: log file name is empty.', 'dpuk-wp-log') . '</p>';
        echo '</div>';
    }

    echo '</div>';
}

function be_plugin_log_viewer_head() {

    echo '<style type="text/css">' . PHP_EOL;
    echo 'pre.log{ background-color: #23282d; color: #ddd; overflow-y: scroll; line-height: 14px; font-size: 12px; padding: 4px; }' . PHP_EOL;
    echo '</style>' . PHP_EOL;
}
add_action('admin_head', 'be_plugin_log_viewer_head');




/**
 * Possible method to show only last X lines of the log
 * 
 */

/*

$lines = readLastLines(WP_CONTENT_DIR. '/uploads/plugin.log', 2); // return string with 5 last lines
    echo $lines;

*/

function readLastLines($filename, $num, $reverse = false) {
    
    $file = new \SplFileObject($filename, 'r');
    $file->seek(PHP_INT_MAX);
    $last_line = $file->key();
    $lines = new \LimitIterator($file, $last_line - $num, $last_line);
    $arr = iterator_to_array($lines);
    if($reverse) $arr = array_reverse($arr);
    return implode('',$arr);

    
}


/**
 * Create a prettier version of the File size.
 * 
 */
function pretty_file_size($file) {


  $fileSize = filesize($file);

  // Determine the filesize unit 
  if ($fileSize >= 1073741824) {
    $fileSize = number_format($fileSize / 1073741824, 2) . ' GB';
  } elseif ($fileSize >= 1048576) {
    $fileSize = number_format($fileSize / 1048576, 2) . ' MB';
  } elseif ($fileSize >= 1024) {
    $fileSize = number_format($fileSize / 1024, 2) . ' KB';
  } elseif ($fileSize > 1) {
    $fileSize = $fileSize. ' bytes';
  } elseif ($fileSize == 1) {
    $fileSize = '1 byte';
  } else {
    $fileSize = '0 bytes';
  }

  return $fileSize;
}


/**
 * Write to the log when a user logs in.
 */
add_filter('wp_authenticate_user', 'authenticate_user_on_login',10,2);
function authenticate_user_on_login ($user, $password) {

  date_default_timezone_set("Europe/London");

  plugin_log( [
      'login-name' => $user->user_login,
      'login-email' => $user->user_email,
      'login-date' => date("Y-m-d") . ' ' . date("h:i:sa") . ' UK time',
  ] );

      return $user;
}


/**
 * Write to the log when a post is saved.
 */
add_action( 'save_post', 'log_save_post', 10, 3 );
function log_save_post( $post_ID, $post, $update ) {

  $id = get_the_ID();
  $mod_auth = the_modified_author();
  $current_user = wp_get_current_user();
  $current_user_id = $current_user->ID;
  $current_user_name = $current_user->display_name;
  $current_queried_post_type = get_post_type( get_queried_object_id() );
  $msg ='post updated';


    plugin_log( [
        'Post Title' => $post->post_title,
        'Post ID' => $post->ID,
        'User' => $current_user_name,
        'code' => 'save_post' . $msg,    
        'login-date' => date("Y-m-d") . ' ' . date("h:i:sa") . ' UK time',
    ] );
  
}

/**
 * Write to the log when a plugin is activated/deactivated.
 */
add_action( 'activated_plugin', 'log_plugin_actions', 10, 2 );
add_action( 'deactivated_plugin', 'log_plugin_actions', 10, 2 );

/**
 * Log plugin activations and deactivations.
 *
 * @param  string $plugin
 * @param  bool   $network_wide
 * @return void
 */
function log_plugin_actions( $plugin, $network_wide ){


    $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
    $date_format = get_option( 'date_format' ) . ' · ' . get_option( 'time_format' );

    plugin_log( [
        'user'    => esc_html( wp_get_current_user()->display_name ),
        'plugin'  => $plugin_data['Name'],
        'network' => $network_wide ? '✔' : '',
        'time'    => date( $date_format, time() ),
        'action'  => 'deactivated_plugin' === current_filter() ? 'deactivated' : 'activated'
        ] );

}