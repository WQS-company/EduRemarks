<?php
// includes/functions.php

/**
 * Global Resource Pricing Synchronizer
 */
function getCreditRate($key, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? (float)$val : 1.0;
    } catch (Exception $e) { return 1.0; }
}

/**
 * Generates a unique school ID in the format ER123456XY
 */
function generateSchoolID($pdo) {
    do {
        $numbers = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $letters = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 2);
        $unique_id = "ER" . $numbers . $letters;
        
        // Check if it already exists
        $stmt = $pdo->prepare("SELECT id FROM schools WHERE unique_id = ?");
        $stmt->execute([$unique_id]);
        $exists = $stmt->fetch();
    } while ($exists);
    
    return $unique_id;
}

function sanitize($data) {
    if (class_exists('Security')) {
        return Security::sanitize($data);
    }
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Deduct credits from school and log activity
 */
function deductCredits($pdo, $school_id, $amount, $activity, $rate_key = null) {
    if (!$school_id) return true;

    // Resolve dynamic rate if key provided
    if ($rate_key) {
        $rate = getCreditRate($rate_key, $pdo);
        $amount = $amount * $rate;
    }

    if ($amount <= 0) return true;

    try {
        // 1. Check current balance and billing mode
        $stmt = $pdo->prepare("SELECT credits, billing_mode, subscription_active, subscription_start, subscription_end FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch();

        if (!$school) return false;

        // Bypass if subscription is active and within dates
        if ($school['billing_mode'] === 'subscription' && $school['subscription_active'] == 1) {
            $today = date('Y-m-d');
            if ($today >= $school['subscription_start'] && $today <= $school['subscription_end']) {
                return true; // Subscription active, no credits needed
            }
        }

        if ($school['credits'] < $amount) {
            return false; // Insufficient credits
        }

        // 2. Atomic Update
        $stmt = $pdo->prepare("UPDATE schools SET credits = credits - ? WHERE id = ?");
        $stmt->execute([$amount, $school_id]);

        // 3. Log the activity
        $stmt = $pdo->prepare("INSERT INTO credit_logs (school_id, amount, activity) VALUES (?, ?, ?)");
        $stmt->execute([$school_id, $amount, $activity]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}
function hasFeature($feature) {
    global $active_school;
    // Strict Opt-in Protocol: Premium features require explicit enablement.
    // If Super Admin hasn't initialized features, default is FALSE for high-tier nodes.
    if (!isset($active_school['feature_access']) || is_null($active_school['feature_access'])) {
        $restricted = ['SMS_ALERTS', 'CBT_EXAMS', 'ADMISSION_PORTAL', 'STUDENT_PORTAL', 'COURSE_CURRICULUM']; // Premium nodes
        return !in_array($feature, $restricted);
    }
    
    $features = explode(',', $active_school['feature_access']);
    return in_array($feature, $features);
}

/**
 * Get active unread notifications for a user based on role
 */
function get_support_notifications($pdo, $user_id, $role) {
    if ($role === 'super_admin') {
        // Super Admin sees all unread messages from owners/staff
        $stmt = $pdo->prepare("SELECT m.*, u.full_name as sender_name, u.profile_picture FROM support_messages m 
                               JOIN users u ON m.sender_id = u.id 
                               WHERE m.is_read = 0 AND m.sender_role != 'super_admin' 
                               ORDER BY m.created_at DESC LIMIT 5");
        $stmt->execute();
        $msgs = $stmt->fetchAll();

        // Total count
        $count = $pdo->query("SELECT COUNT(*) FROM support_messages WHERE is_read = 0 AND sender_role != 'super_admin'")->fetchColumn();
    } else {
        // Owners/Staff see unread messages from super_admin in their tickets
        $stmt = $pdo->prepare("SELECT m.*, u.full_name as sender_name, u.profile_picture FROM support_messages m 
                               JOIN users u ON m.sender_id = u.id 
                               JOIN school_requests r ON m.ticket_id = r.id
                               WHERE m.is_read = 0 AND m.sender_role = 'super_admin' AND r.user_id = ? 
                               ORDER BY m.created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $msgs = $stmt->fetchAll();

        // Total count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_messages m JOIN school_requests r ON m.ticket_id = r.id 
                               WHERE m.is_read = 0 AND m.sender_role = 'super_admin' AND r.user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetchColumn();
    }
    
    return ['count' => $count, 'notifications' => $msgs];
}

function time_ago($timestamp) {
    if (!$timestamp) return "N/A";
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return "just now";
    if ($diff < 3600) return round($diff / 60) . "m ago";
    if ($diff < 86400) return round($diff / 3600) . "h ago";
    return date('M d', $time);
}
/**
 * Handle Institutional Media Upload (Staff/Student profile pictures)
 * Validates size based on global settings and deducts credits
 */
function handleMediaUpload($pdo, $school_id, $file, $sub_dir) {
    if (!$school_id || !isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    // 1. Fetch Global Resource Protocols
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('max_upload_size', 'credit_cost_image_upload')");
    $stmt->execute();
    $settings = [];
    while($row = $stmt->fetch()) $settings[$row['setting_key']] = $row['setting_value'];

    $max_size = ((float)($settings['max_upload_size'] ?? 2)) * 1024 * 1024; // MB to Bytes
    $credit_cost = (int)($settings['credit_cost_image_upload'] ?? 50);

    // 2. Validate File Size
    if ($file['size'] > $max_size) {
        throw new Exception("File exceeds institutional limit of " . ($settings['max_upload_size'] ?? 2) . "MB");
    }

    // 3. Validate File Type
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        throw new Exception("Invalid asset format. Please use JPG, PNG or WEBP.");
    }

    // 4. Check Credit Economics
    if (!deductCredits($pdo, $school_id, $credit_cost, "Profile Image Upload ($sub_dir)")) {
        throw new Exception("Insufficient institutional credits to commission this asset.");
    }

    // 5. Atomic Asset Relocation
    $upload_dir = dirname(__DIR__) . '/uploads/' . $sub_dir . '/';
    if (!is_dir($upload_dir)) {
        if (!@mkdir($upload_dir, 0755, true)) {
            throw new Exception("Institutional repository creation failed. Check permissions.");
        }
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'media_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return 'uploads/' . $sub_dir . '/' . $filename;
    }

    return null;
}

/**
 * Institutional Terminology Synchronizer
 * Dynamically switches labels based on school type (K-12 vs Tertiary)
 */
function get_label($key) {
    global $active_school;
    
    // Determine context (Higher Ed nodes like Tertiary, Vocational, polytechnic, university, college)
    $type = strtolower($active_school['school_type'] ?? '');
    $is_higher_ed = (
        strpos($type, 'tertiary') !== false || 
        strpos($type, 'vocational') !== false || 
        strpos($type, 'polytechnic') !== false || 
        strpos($type, 'university') !== false || 
        strpos($type, 'college') !== false
    );

    $dictionary = [
        'Term'         => $is_higher_ed ? 'Semester' : 'Term',
        'Terms'        => $is_higher_ed ? 'Semesters' : 'Terms',
        'Session'      => $is_higher_ed ? 'Academic Session' : 'Session',
        '1st Term'     => $is_higher_ed ? 'First Semester' : '1st Term',
        '2nd Term'     => $is_higher_ed ? 'Second Semester' : '2nd Term',
        '3rd Term'     => $is_higher_ed ? 'Third Semester' : '3rd Term',
        'Class'        => $is_higher_ed ? 'Level' : 'Class',
        'Classes'      => $is_higher_ed ? 'Levels' : 'Classes',
        'Subject'      => $is_higher_ed ? 'Course' : 'Subject',
        'Subjects'     => $is_higher_ed ? 'Courses' : 'Subjects',
        'Classrooms'   => $is_higher_ed ? 'Lecture Halls' : 'Classrooms',
        'Teacher'      => $is_higher_ed ? 'Lecturer' : 'Teacher',
        'Teachers'     => $is_higher_ed ? 'Lecturers' : 'Teachers',
        'Staff'        => $is_higher_ed ? 'Faculty' : 'Staff',
        'Admission No' => $is_higher_ed ? 'Matric Number' : 'Admission No',
        'Broadsheet'   => $is_higher_ed ? 'Transcript' : 'Broadsheet',
        'Report Card'  => $is_higher_ed ? 'Statement of Result' : 'Report Card',
        'Report Sheet' => $is_higher_ed ? 'Result Entry' : 'Report Sheet',
        'Report Sheets'=> $is_higher_ed ? 'Result Entry' : 'Report Sheets',
        'Pupils'       => $is_higher_ed ? 'Students' : 'Pupils',
        'Academic Audit' => $is_higher_ed ? 'Academic Registry' : 'Academic Audit',
        'Form Teacher' => $is_higher_ed ? 'Level Adviser' : 'Form Teacher',
        'Head Teacher' => $is_higher_ed ? 'Registrar' : 'Head Teacher',
        'Section'      => $is_higher_ed ? 'Department' : 'Section',
        'Sections'     => $is_higher_ed ? 'Departments' : 'Sections',
        'Class Name'   => $is_higher_ed ? 'Level Name' : 'Class Name',
        'Subject Name' => $is_higher_ed ? 'Course Title' : 'Subject Name',
        'Subject Code' => $is_higher_ed ? 'Course Code' : 'Subject Code'
    ];

    return $dictionary[$key] ?? $key;
}
?>
