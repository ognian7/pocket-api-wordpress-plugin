<?php
/**
 * @package Pocket API Integration 
 * @version 1.0
 */
/*
Plugin Name: Pocket API Integration
Plugin URI: http://wordpress.org/plugins/pocket-api/
Description: This is a plugin for automatic blog feed from Pocket API - idea was submitted by pokyah.com
Author: Ognian Samokovliyski 
Version: 1.0
Author URI: http://ognian7.github.com/
*/

require_once(ABSPATH . 'wp-config.php');
require_once(ABSPATH . 'wp-includes/wp-db.php');
require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');

const PLUGIN_NAME = 'Pocket API';

add_action('admin_menu', 'pocket_api_admin_menu');

$minutes = 1;

function pocket_api_cron_recurrence_interval( $schedules ) {
    global $minutes;
    $schedules['every_n_minutes'] = array(
            'interval'  => get_option('pocket-api-interval', 10) * 60,
            'display'   => __( 'Every ' . $minutes . ' Minutes', 'textdomain' )
    );
     
    return $schedules;
}

add_filter( 'cron_schedules', 'pocket_api_cron_recurrence_interval' );


if ( ! wp_next_scheduled( 'pocket_api_interval_action_hook' ) ) {
    wp_schedule_event( time(), 'every_n_minutes', 'pocket_api_interval_action_hook' );
}

add_action('pocket_api_interval_action_hook', 'pocket_api_interval_action');

function pocket_api_interval_action() {

    $user_agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.96 Safari/537.36';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"https://getpocket.com/login");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $cookiejar = tempnam(sys_get_temp_dir(), "pocket_api_cookie_");
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
    curl_setopt($ch,CURLOPT_USERAGENT,$user_agent);
    $server_output = curl_exec ($ch);

    curl_close ($ch);


    $start = strpos($server_output, '<form method="POST" action="/login_process">');
    $end = strpos($server_output, '</form>', $start);
    $form_to_parse = substr ( $server_output, $start, $end - $start );

    preg_match_all('/<input.*name="([^"]*)".*value="([^"]*)"/',$form_to_parse,$fields);

    $fields_values = array();
    for ($f = 0; $f < count($fields[1]); $f++) {
        $fields_values[$fields[1][$f]] = $fields[2][$f];
    }
    $fields_values['feed_id'] = get_option('pocket-api-username');
    $fields_values['password'] = get_option('pocket-api-password');


    $request3a_url = 'https://getpocket.com/login_process';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,$request3a_url);

    curl_setopt($ch, CURLOPT_POST, 1);

    curl_setopt($ch, CURLOPT_POSTFIELDS,
        http_build_query($fields_values));


    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
    curl_setopt($ch, CURLOPT_USERAGENT,$user_agent);

    curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);

    curl_setopt ($ch, CURLOPT_HEADER, 1);

    $server_output = curl_exec ($ch);

    curl_close ($ch);



    $consumer_key=get_option('pocket-api-consumer_key');


    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"https://getpocket.com/v3/oauth/request");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        "consumer_key=$consumer_key&redirect_uri=myapp:authorizationFinished");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec ($ch);

    curl_close ($ch);

    if (!startsWith($server_output,'code=')) {
        exit(); // return
    }

    $output_parts = explode('=',$server_output);

    $code = $output_parts[1];

////////////////

    $ch = curl_init();

    $request2_url = sprintf('https://getpocket.com/auth/authorize?request_token=%s&redirect_uri=myapp:authorizationFinished',$code);

    curl_setopt($ch, CURLOPT_URL,$request2_url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
    curl_setopt($ch,CURLOPT_USERAGENT,$user_agent);

    $server_output = curl_exec ($ch);

    curl_close ($ch);

    $start = strpos($server_output, 'submit: function(authorized)');
    $end = strpos($server_output, 'form[0].submit()');
    $form_to_parse = substr ( $server_output, $start, $end - $start );

    preg_match_all("/<input.*type='hidden'.*name='([^']*)'.*value='([^']*)'/",$form_to_parse,$fields);
    preg_match("/<form.*action='([^']*)'/",$form_to_parse,$form_action);

//print_r($fields);

//print_r($form_action);

//////////////////
    if (count($form_action) == 2) {
        $fields_values = array();
        for ($f = 0; $f < count($fields[1]); $f++) {
            if ($fields[1][$f] == 'approve_flag') {
                $fields_values['approve_flag'] = 1;
            } else {
                $fields_values[$fields[1][$f]] = $fields[2][$f];
            }
        }


        $request3_url = 'https://getpocket.com/' . $form_action[1];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$request3_url);

        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS,
            http_build_query($fields_values));


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);

        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt ($ch, CURLOPT_HEADER, 1);

        $server_output = curl_exec ($ch);

        curl_close ($ch);

//echo $server_output;
//exit();
    }

////////////////////////

    $request4_url = 'https://getpocket.com/v3/oauth/authorize';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,$request4_url);

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        "consumer_key=$consumer_key&code=$code");

    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec ($ch);

    curl_close ($ch);

//////////////////////////
    $t = get_option('pocket-api-last_sync', 0);
    update_option( 'pocket-api-last_sync', time());

    $request5_url = 'https://getpocket.com/v3/get';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,$request5_url);

    switch (get_option('pocket-api-trigger')) {
        case 'F' :
            $opt = "&favorite=1";
            break;
        case 'A' :
            $opt = "&state=archive";
            break;
        case 'S' :
            $opt = "&tag=" . get_option('pocket-api-trigger-param');
            break;
    }

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        "consumer_key=$consumer_key&$server_output&detailType=complete&since=$t$opt");

    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec ($ch);

    curl_close ($ch);

    $result = json_decode($server_output);

    $user = get_user_by( 'login', get_option('pocket-api-wp-username') );

    foreach ($result->list as $key=>$value) {

        $tags = array();
        $cats = array();
        foreach ($value->tags as $tag => $dummy) {
            if (startsWith($tag, '#')) {
                $tags[] = substr($tag,1);
            } else {
                $cats[] = wp_create_category($tag);
            }
        }

        $id = wp_insert_post(array(post_author => $user->ID,
            post_content => ($value->has_image == "1" ? '<img src="' . $value->image->src . '" /><br />' : '') . '<a href="' . $value->given_url . '">' . (trim($value->excerpt) == "" ? "read the original" : $value->excerpt) . '</a>' ,
            post_title => $value->given_title,
            post_status => get_option('pocket-api-status') == 'P' ? 'publish' : 'draft',
            post_category => $cats,
            guid => site_url() . '?p=3'), true);

        wp_set_post_tags($id, $tags, true);
    }

}


function pocket_api_admin_menu()
{
	add_management_page(__(PLUGIN_NAME . ' Management', 'pocket-api'), __(PLUGIN_NAME, 'pocket-api'), 'publish_posts', POCKET_API_FILENAME, 'pocket_api_options_page');
    add_action( 'admin_init', 'register_pocket_api_settings' );
}

function register_pocket_api_settings() {
    register_setting( 'pocket-api-settings', 'pocket-api-wp-username' );
    register_setting( 'pocket-api-settings', 'pocket-api-username' );
    register_setting( 'pocket-api-settings', 'pocket-api-password' );
    register_setting( 'pocket-api-settings', 'pocket-api-consumer_key' );
    register_setting( 'pocket-api-settings', 'pocket-api-trigger'  );
    register_setting( 'pocket-api-settings', 'pocket-api-trigger-param' );
    register_setting( 'pocket-api-settings', 'pocket-api-interval', 'intval');

    register_setting( 'pocket-api-settings', 'pocket-api-title' );
    register_setting( 'pocket-api-settings', 'pocket-api-content' );
    register_setting( 'pocket-api-settings', 'pocket-api-status' );
    register_setting( 'pocket-api-settings', 'pocket-api-last_sync' );
}

function pocket_api_options_page($action=""){
    ?>

    <div class="wrap">
    <h1>Pocket API</h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'pocket-api-settings' ); ?>
        <?php do_settings_sections( 'pocket-api-settings' ); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">WP Username</th>
                <td><input type="text" name="pocket-api-wp-username" value="<?php echo esc_attr( get_option('pocket-api-wp-username') ); ?>" /></td>
            </tr>

            <tr valign="top">
                <th scope="row">Username</th>
                <td><input type="text" name="pocket-api-username" value="<?php echo esc_attr( get_option('pocket-api-username') ); ?>" /></td>
            </tr>

            <tr valign="top">
                <th scope="row">Password</th>
                <td><input type="password" name="pocket-api-password" value="<?php echo esc_attr( get_option('pocket-api-password') ); ?>" /></td>
            </tr>

            <tr valign="top">
                <th scope="row">Consumer Key</th>
                <td><input type="text" name="pocket-api-consumer_key" value="<?php echo esc_attr( get_option('pocket-api-consumer_key') ); ?>" /></td>
            </tr>

            <tr valign="top">
                <th scope="row">Trigger</th>
                <td>
                    <select name="pocket-api-trigger" onchange="document.querySelector('.trigger-param').style.display = this.options[this.selectedIndex].value == 'S' ? 'table-row' : 'none'">
                        <option value="F" <?php echo get_option('pocket-api-trigger') == 'F' ? 'selected' : ''; ?>>item marked as favorite</option>
                        <option value="A" <?php echo get_option('pocket-api-trigger') == 'A' ? 'selected' : ''; ?>>item archived</option>
                        <option value="S" <?php echo get_option('pocket-api-trigger') == 'S' ? 'selected' : ''; ?>>item marked with a specific tag</option>
                    </select>
                </td>
            </tr>

            <tr valign="top" class="trigger-param hidden">
                <th scope="row">Specific Tag</th>
                <td><input type="text" name="pocket-api-trigger-param" value="<?php echo esc_attr( get_option('pocket-api-trigger-param') ); ?>" /></td>
            </tr>

            <tr valign="top">
                <th scope="row">Interval (in minutes)</th>
                <td><input type="text" name="pocket-api-interval" value="<?php echo esc_attr( get_option('pocket-api-interval') ); ?>" /></td>
            </tr>
        </table>
        <hr />
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Status</th>
                <td>
                    <select name="pocket-api-status" onchange="">
                        <option  value="D" <?php echo get_option('pocket-api-status') == 'D' ? 'selected' : ''; ?>>draft</option>
                        <option  value="P" <?php echo get_option('pocket-api-status') == 'P' ? 'selected' : ''; ?>>published</option>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>

    </form>
    </div>

    <?php
}

// Helpers

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}
?>
