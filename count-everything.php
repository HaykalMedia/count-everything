<?php

/*
Plugin Name: Count Everything
Description: Async counting of visits to each post
Version: 1.0
Author: Ammar Alakkad
Author URI: http://aalakkad.me
License: MIT
*/

defined('ABSPATH') or die();

class Count_Everything
{
    public static $instance;
    public static $ajax_action  = 'visit_post';
    public static $base_table   = 'counts';
    public static $total_table  = 'counts_total';
    public static $options      = [ 'count_users' => false ];
    public static $options_name = 'counts_options';

    private function __construct()
    {
        $options = get_option(self::$options_name);
        if (isset($options['count_users']) and $options['count_users'] == true) {
            // Register Ajax listener for users
            add_action('wp_ajax_'.self::$ajax_action, [ 'Count_Everything', 'add_visit' ]);
        }
        // Register Ajax listener for non-users
        add_action('wp_ajax_nopriv_'.self::$ajax_action, [ &$this, 'add_visit' ]);
        add_action('wp_enqueue_scripts', [ &$this, 'enqueue_script' ]);
        add_action('admin_menu', [ &$this, 'dashboard_menu' ]);
        // Cron to update tables
        add_action('count_everything_update_totals', [ &$this, 'update_total' ]);
    }

    /**
     * Get singleton instance
     *
     * @return object
     */
    public static function get_instance()
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register & Enqueue script for front-end
     */
    public static function enqueue_script()
    {
        wp_register_script('count-everything', plugins_url('assets/count-everything.js', __FILE__), null, null, true);
        if (is_single()) {
            wp_localize_script('count-everything', 'countEverything', [
                'ajaxurl'     => admin_url('admin-ajax.php'),
                'postID'      => get_the_ID(),
                'countAction' => self::$ajax_action
            ]);
            wp_enqueue_script('count-everything');
        }
    }

    public function dashboard_menu()
    {
        add_options_page('Count Everything Options', 'Count Everything', 'manage_options', 'count-everything', [&$this, 'menuContent']);
    }

    /**
     * Activation method, creates necessary tables
     */
    public static function activation()
    {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        $table_name = $wpdb->prefix.self::$base_table;
        $sql        = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            total mediumint(9) NOT NULL DEFAULT 0,
            day DATE NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY {$table_name}_unique (post_id, day),
            INDEX {$table_name}_post_id (post_id),
            INDEX {$table_name}_day (day)
        )
        ENGINE = MYISAM";
        dbDelta($sql);

        $table_name = $wpdb->prefix.self::$total_table;
        $sql        = "CREATE TABLE {$table_name} (
            post_id mediumint(9) NOT NULL,
            total mediumint(9) NOT NULL DEFAULT 0,
            last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (post_id)
        )
        ENGINE = MYISAM";
        dbDelta($sql);

        $options = get_option(self::$options_name);
        if (! $options) {
            add_option(self::$options_name, self::$options);
        }

        // Register cron (event)
        wp_schedule_event(time(), 'hourly', 'count_everything_update_totals');
    }

    /**
     * Add a visit from AJAX request to given
     *
     * @param int $post_id
     */
    public static function add_visit($post_id = null)
    {
        if ($post_id == null) {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        }

        if ($post_id) {
            // insert to database
            global $wpdb;
            $table_name = $wpdb->prefix.self::$base_table;
            $today      = date('Y-m-d');
            $sql        = "INSERT INTO {$table_name} (post_id, total, day) VALUES ({$post_id}, 1, '{$today}') ON DUPLICATE KEY UPDATE total = total + 1";

            return $wpdb->query($sql);
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            die();
        }

        return false;
    }

    /**
     * Update total counts in plugin's tables
     *
     * Run on count_everything_update_totals hook (event)
     */
    public static function update_total()
    {
        global $wpdb;

        $base_table  = $wpdb->prefix.self::$base_table;
        $total_table = $wpdb->prefix.self::$total_table;

        // Update total table
        $sql   = "SELECT post_id, SUM(total) as total FROM {$base_table} GROUP BY post_id";
        $total = $wpdb->get_results($sql);
        foreach ($total as $row) {
            $sql = "INSERT INTO {$total_table} (post_id, total) VALUES ({$row->post_id}, {$row->total}) ON DUPLICATE KEY UPDATE total = {$row->total}";
            $wpdb->query($sql);
        }
    }

    /**
     * Get total visits for given $post_id
     *
     * @param int $post_id
     *
     * @return bool
     */
    public static function get($post_id)
    {
        $post_id = is_array($post_id) ? implode(', ', $post_id) : (int) $post_id;
        if (! $post_id) {
            return false;
        }

        global $wpdb;
        $total_table = $wpdb->prefix.self::$total_table;
        $sql         = "SELECT total FROM {$total_table} WHERE post_id IN ({$post_id}) LIMIT 1";

        return $wpdb->get_var($sql);
    }

    /**
     * Get visits for given $post_id between date range
     *
     * @param int|array   $post_id
     * @param date|string $from Any valid date format, even with days/weeks/months ago e.g. 2014-05-15, 1 day, -1 week, 3 months ago
     * @param date|string $to   Any valid date format, even with days/weeks/months ago e.g. 2014-05-15, 1 day, -1 week, 3 months ago
     *
     * @return int
     */
    public static function getRange($post_id, $from, $to = 'now')
    {
        global $wpdb;
        // Sanitize dates
        $from = date('Y-m-d', strtotime($from));
        $to   = date('Y-m-d', strtotime($to));
        // Sanitize id
        $post_id = is_array($post_id) ? implode(', ', $post_id) : (int) $post_id;
        if (empty($post_id)) {
            return false;
        }

        $base_table = $wpdb->prefix.self::$base_table;
        $sql        = "SELECT SUM(total) FROM {$base_table} WHERE post_id IN ({$post_id}) AND day BETWEEN '{$from}' AND '{$to}'";

        return (int) $wpdb->get_var($sql);
    }

    public static function menuContent()
    {
        $options = get_option( Count_Everything::$options_name );
        if(count($_POST)) {
            $options['count_users'] = isset($_POST['count_users']);
            update_option( Count_Everything::$options_name, $options );
        }
        ?>
        <div class="wrap">
            <h2>Count Everything options</h2>
            <br>
            <form action="" method="post" accept-charset="utf-8">
                <table class="form-table" style="width: 50%;">
                    <tr>
                        <td>
                            <label for="count_users">احتساب زيارات المحررين؟</label>
                        </td>
                        <td>
                            <input type="checkbox" id="count_users" name="count_users" value="1" <?=($options['count_users'] ? " checked" : "")?>>
                        </td>
                    </tr>
                </table>

                <input type="submit" class="button button-primary" value="Save">
            </form>
        </div>
        <?php
    }
}

if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, [ 'Count_Everything', 'activation' ]);
}
if (function_exists('add_action')) {
    add_action("plugins_loaded", [ 'Count_Everything', 'get_instance' ]);
}
