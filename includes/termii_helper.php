<?php
// includes/termii_helper.php - Termii SMS Gateway Integration

/**
 * Dispatches SMS via Termii API
 * @param string $to Recipient phone number in international format (e.g., 2348000000000)
 * @param string $message Message content
 * @return array Response status and message
 */
function send_termii_sms($to, $message) {
    if (!$to) return ['success' => false, 'message' => 'Empty recipient node.'];
    if (!$message) return ['success' => false, 'message' => 'Empty message payload.'];

    $config = require dirname(__DIR__) . '/config/termii.php';
    if (!$config || empty($config['api_key'])) {
        return ['success' => false, 'message' => 'Gateway configuration failure.'];
    }

    // Modernize phone number (strip leading + if exists)
    $to = ltrim($to, '+');

    // Termii SMS Protocol
    $payload = [
        "api_key" => $config['api_key'],
        "to" => $to,
        "from" => $config['sender_id'],
        "sms" => $message,
        "type" => "plain",
        "channel" => "generic" // Use 'dnd' if you have DND enabled or 'generic'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['base_url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Network Handshake Error: ' . $error];
    }

    $res_data = json_decode($response, true);
    if (isset($res_data['message']) && ($res_data['message'] == 'Successfully Sent' || (isset($res_data['code']) && $res_data['code'] == 'ok'))) {
        return ['success' => true, 'data' => $res_data];
    }

    return ['success' => false, 'message' => $res_data['message'] ?? 'Platform gateway refused transmission.'];
}
