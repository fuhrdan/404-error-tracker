<?php
/**
 * Plugin Name: 404 Error Tracker
 * Description: Logs 404 errors and displays them in the WordPress dashboard.
 * Version: 1.1
 * Author: Dan Fuhr
 */

// Create a database table for storing 404 error logs on plugin activation
register_activation_hook(__FILE__, 'create_404_error_table');
function create_404_error_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . '404_error_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        ip_address varchar(100) NOT NULL,
        user_agent text NOT NULL,
        referrer_url text NOT NULL,
        requested_url text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Hook into 404 template action to log 404 errors
add_action('template_redirect', 'log_404_error');
function log_404_error()
{
    if (is_404()) {
        global $wpdb;
        $table_name = $wpdb->prefix . '404_error_log';

        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $referrer_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'None';
        $requested_url = $_SERVER['REQUEST_URI'];
        $current_time = current_time('mysql');

        $wpdb->insert(
            $table_name,
            array(
                'time' => $current_time,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'referrer_url' => $referrer_url,
                'requested_url' => $requested_url
            )
        );
    }
}

// Create the dashboard widget
add_action('wp_dashboard_setup', 'add_404_dashboard_widget');
function add_404_dashboard_widget()
{
    wp_add_dashboard_widget(
        '404_dashboard_widget',
        '404 Error Log',
        'display_404_dashboard_widget'
    );
}

// Display the dashboard widget content
function display_404_dashboard_widget()
{
    global $wpdb;
    $table_name = $wpdb->prefix . '404_error_log';

    // Check if any errors have been deleted
    if (isset($_POST['delete_error'])) {
        $delete_id = intval($_POST['delete_error']);
        $wpdb->delete($table_name, ['id' => $delete_id]);
        echo "<p>Deleted 404 error with ID: $delete_id</p>";
    }

    // Only show errors from the last 7 days
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table_name
        WHERE time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY time DESC
        LIMIT 10
    "));

    if ($results) {
        echo '<table>
                <tr>
                    <th>Date & Time</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                    <th>Referrer</th>
                    <th>Requested URL</th>
                    <th>Action</th>
                </tr>';

        foreach ($results as $row) {
            echo '<tr>
                    <td>' . esc_html($row->time) . '</td>
                    <td>' . esc_html($row->ip_address) . '</td>
                    <td>' . esc_html($row->user_agent) . '</td>
                    <td>' . esc_html($row->referrer_url) . '</td>
                    <td>' . esc_html($row->requested_url) . '</td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="delete_error" value="' . esc_attr($row->id) . '">
                            <button type="submit" class="button">Delete</button>
                        </form>
                    </td>
                  </tr>';
        }

        echo '</table>';
    } else {
        echo '<p>No 404 errors logged in the last 7 days.</p>';
    }
}
