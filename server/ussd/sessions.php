<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

function sessionExists($session_id) {
    global $db;
    $stmt = $db->prepare("SELECT session_id FROM ussd_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    return $stmt->fetchColumn() ? true : false;
}

function saveSession($session_id, $updates) {
    global $db;

    if (!$db) {
        error_log("ERROR: Database connection failed in saveSession()");
        return;
    }

    $existingSession = getSession($session_id);

    // Ensure session_data is always a JSON string before decoding
    $session_data = isset($existingSession['session_data']) && is_string($existingSession['session_data'])
        ? json_decode($existingSession['session_data'], true)
        : [];

    if (!is_array($session_data)) {
        $session_data = []; // Fallback to empty array if decoding fails
    }

    // Correctly merge new values without overwriting existing ones
    foreach ($updates as $key => $value) {
        $session_data[$key] = $value;
    }

    $session_data_json = json_encode($session_data);

    if ($existingSession) {
        $stmt = $db->prepare("UPDATE ussd_sessions SET session_data = ?, updated_at = NOW() WHERE session_id = ?");
        $stmt->execute([$session_data_json, $session_id]);
    } else {
        $stmt = $db->prepare("INSERT INTO ussd_sessions (session_id, phone_number, ussd_type, menu_state, session_data) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$session_id, $updates['phone_number'], $updates['ussd_type'], 'start', $session_data_json]);
    }

    error_log("DEBUG: Updated session '$session_id' with data: " . $session_data_json);
}

function appendSessionData($session_id, $new_data) {
    global $db;

    if (!$db) {
        error_log("ERROR: Database connection failed in appendSessionData()");
        return;
    }

    // Fetch the current session data
    $stmt = $db->prepare("SELECT session_data FROM ussd_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Decode session_data
    $session_data = isset($result['session_data']) && is_string($result['session_data'])
        ? json_decode($result['session_data'], true)
        : [];

    if (!is_array($session_data)) {
        $session_data = [];
    }

    // Log session data before update
    error_log("DEBUG: Current session data before append for '$session_id': " . json_encode($session_data));

    // Merge new data ensuring `menu_state` persists
    foreach ($new_data as $key => $value) {
        $session_data[$key] = $value;
    }

    // Convert back to JSON and update the database
    $session_data_json = json_encode($session_data);

    $stmt = $db->prepare("UPDATE ussd_sessions SET session_data = ?, updated_at = NOW() WHERE session_id = ?");
    $stmt->execute([$session_data_json, $session_id]);

    // Log updated session data
    error_log("DEBUG: Updated session data after append for '$session_id': " . $session_data_json);
}


function getSession($session_id, $key = null) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM ussd_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return $key ? null : [];
    }

    // Decode session_data from JSON
    $session_data = isset($session['session_data']) && is_string($session['session_data'])
        ? json_decode($session['session_data'], true)
        : [];

    if (!is_array($session_data)) {
        $session_data = []; // Ensure it's an array
    }

    // Merge session_data with database fields (prioritizing session_data)
    $merged_session = array_merge($session, $session_data);

    // Log retrieved session data
    error_log("DEBUG: Retrieved session data for '$session_id': " . json_encode($merged_session));

    return $key ? ($merged_session[$key] ?? null) : $merged_session;
}

function deleteSession($session_id) {
    global $db;
    $stmt = $db->prepare("DELETE FROM ussd_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
}

function endSession($session_id, $custom_message = "Thank you for using our service.") {
    // Mark session as ended in the database
    appendSessionData($session_id, ['menu_state' => 'end']);

    // Ensure Panacea receives the correct USSD closing command
    if (!headers_sent()) {
        header("X-ussd-close: 1");
        header("Content-Type: text/plain");
    }

    // Output the final message
    echo $custom_message;

    // Ensure no additional output is sent
    exit;
}



?>
