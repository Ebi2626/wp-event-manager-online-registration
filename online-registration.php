<?php
$redirectUrl = $_SERVER['HTTP_REFERER'];
$parse_uri = explode('wp-content', $_SERVER['SCRIPT_FILENAME']);
require_once($parse_uri[0] . 'wp-load.php');

if (!is_user_logged_in()) {
    return header('Location: ' . wp_login_url());
}

$queries = array();
parse_str($_SERVER['QUERY_STRING'], $queries);
$eventId = (int) $queries['eventId'];
$userId = (int) get_current_user_id();
$date = (string) date('Y-m-d');

function isUserRegisteredOnEvent($userId, $eventId)
{
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}event_members WHERE EventMember = {$userId} AND EventId = {$eventId}");
}

function register_on_event($eventId, $userId, $date)
{
    global $wpdb;
    return $wpdb->insert(
        $wpdb->prefix . "event_members",
        array(
            'EventId' => $eventId,
            'EventMember' => $userId,
            'DateOfRegister' => $date
        ),
        array('%d', '%d', '%s')
    );
}

$registrationResult = false;

if (!isUserRegisteredOnEvent($userId, $eventId)) {
    $registrationResult = register_on_event($eventId, $userId, $date);
}

$queryToRemoveFromRedirectUrl = strstr($redirectUrl, "?");
$redirectUrl = str_replace($queryToRemoveFromRedirectUrl, "", $redirectUrl);

if ($registrationResult) {
    wp_redirect($redirectUrl . '?status=success');
} else {
    wp_redirect($redirectUrl . '?status=error');
}
