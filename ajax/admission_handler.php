<?php
// ajax/admission_handler.php - Admission Operations Synchronizer
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

/**
 * Atomic Enrollment Protocol
 * Transitions an applicant node to a primary student record
 */
function enrollStudent($pdo, $school_id, $app, $target_class_id) {
    // 0. Resolve Admission ID Protocol
    $stmt = $pdo->prepare("SELECT adm_no_type, adm_no_pattern, adm_no_counter, unique_id FROM schools WHERE id = ?");
    $stmt->execute([$school_id]);
    $sch_set = $stmt->fetch();

    $admission_no = "";
    if ($sch_set['adm_no_type'] === 'pattern') {
        $pattern = $sch_set['adm_no_pattern'];
        $counter = (int)$sch_set['adm_no_counter'];
        $admission_no = str_replace(['{YEAR}', '{SCH}', '{ID}'], [date('Y'), $sch_set['unique_id'], str_pad($counter, 3, '0', STR_PAD_LEFT)], $pattern);
        $pdo->prepare("UPDATE schools SET adm_no_counter = adm_no_counter + 1 WHERE id = ?")->execute([$school_id]);
    } else {
        $admission_no = "ADM" . date('y') . mt_rand(1000, 9999);
    }

    // 1. Resolve Class Context
    $cstmt = $pdo->prepare("SELECT name FROM classes WHERE id = ? AND school_id = ?");
    $cstmt->execute([$target_class_id, $school_id]);
    $class_name = $cstmt->fetchColumn() ?: 'Unallocated';

    // 2. Transcribe Record to Primary Registry
    $stmt = $pdo->prepare("
        INSERT INTO students (school_id, full_name, admission_no, student_class, gender, dob, guardian_name, guardian_phone, address, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([
        $school_id, $app['full_name'], $admission_no, $class_name, 
        $app['gender'], $app['dob'] ?: null, 
        $app['parent_name'], $app['parent_phone'], $app['address']
    ]);
    
    $student_id = $pdo->lastInsertId();

    // 3. Establish Academic Mapping
    $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id, school_id) VALUES (?, ?, ?)");
    $stmt->execute([$student_id, $target_class_id, $school_id]);
    
    return $student_id;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_config') {
        // Requires Admin/Owner Auth
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'super_admin')) {
            throw new Exception("Unauthorized Access Node");
        }
        
        parse_str($_POST['data'], $input);
        $school_id = $_SESSION['school_id'];
        
        $is_active = isset($input['is_active']) ? 1 : 0;
        $title = $input['title'] ?? 'Public Admission Portal';
        $desc = $input['description'] ?? '';
        $reqs = $input['requirements'] ?? '';
        $target_session_id = !empty($input['target_session_id']) ? intval($input['target_session_id']) : null;
        $max_slots = intval($input['max_slots'] ?? 0);
        
        $theme_color = $input['theme_color'] ?? '#1F3C88';
        $require_email = isset($input['require_email']) ? 1 : 0;
        $require_dob = isset($input['require_dob']) ? 1 : 0;
        $require_address = isset($input['require_address']) ? 1 : 0;
        $declaration_text = $input['declaration_text'] ?? '';
        $success_message = $input['success_message'] ?? '';
        $contact_email = $input['contact_email'] ?? '';
        $contact_phone = $input['contact_phone'] ?? '';
        
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM admission_forms WHERE school_id = ?");
        $stmt->execute([$school_id]);
        if ($stmt->fetch()) {
            $sql = "UPDATE admission_forms SET title = ?, description = ?, requirements = ?, target_session_id = ?, is_active = ?, max_slots = ?, 
                    theme_color = ?, require_email = ?, require_dob = ?, require_address = ?, declaration_text = ?, success_message = ?, 
                    contact_email = ?, contact_phone = ? 
                    WHERE school_id = ?";
            $pdo->prepare($sql)->execute([
                $title, $desc, $reqs, $target_session_id, $is_active, $max_slots,
                $theme_color, $require_email, $require_dob, $require_address, $declaration_text, $success_message,
                $contact_email, $contact_phone,
                $school_id
            ]);
        } else {
            $sql = "INSERT INTO admission_forms (school_id, title, description, requirements, target_session_id, is_active, max_slots, 
                    theme_color, require_email, require_dob, require_address, declaration_text, success_message, 
                    contact_email, contact_phone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $school_id, $title, $desc, $reqs, $target_session_id, $is_active, $max_slots,
                $theme_color, $require_email, $require_dob, $require_address, $declaration_text, $success_message,
                $contact_email, $contact_phone
            ]);
        }
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'submit_application') {
        // Public Submission Node
        parse_str($_POST['data'], $input);
        
        $school_id = intval($input['school_id']);
        $session_id = intval($input['session_id']);
        $full_name = $input['full_name'];
        $gender = $input['gender'];
        $dob = $input['dob'];
        $class_id = intval($input['class_id']);
        $parent_name = $input['parent_name'];
        $parent_phone = $input['parent_phone'];
        $parent_email = $input['parent_email'] ?? '';
        $address = $input['address'] ?? '';
        
        if (!$school_id || !$full_name || !$class_id || !$parent_phone) {
            throw new Exception("Required institutional fields missing.");
        }
        
        // Credit Deduction Protocol: Institutional processing unit consumption
        if (!deductCredits($pdo, $school_id, 1, "Admission Application ($full_name)", 'credit_admission_applicant')) {
            throw new Exception("The institution has insufficient operational credits to process this application dossier.");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO admission_applications (school_id, session_id, full_name, gender, dob, class_id, parent_name, parent_phone, parent_email, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$school_id, $session_id, $full_name, $gender, $dob, $class_id, $parent_name, $parent_phone, $parent_email, $address]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'process_decision') {
        // Requires Admin/Owner or Authorized Staff
        if (!isset($_SESSION['role'])) throw new Exception("Session Expired.");
        $can_process = ($_SESSION['role'] === 'owner' || $_SESSION['role'] === 'super_admin' || (isset($staff_permissions) && $staff_permissions['can_manage_students']));
        if (!$can_process) {
            throw new Exception("Unauthorized Access Node");
        }
        
        parse_str($_POST['data'], $input);
        $app_id = intval($input['app_id']);
        $status = $input['status'];
        $comment = $input['comment'] ?? '';
        $send_sms = isset($input['send_sms']) ? true : false;
        $target_class_id = intval($input['target_class_id'] ?? 0);
        
        $school_id = $_SESSION['school_id'];
        
        // Fetch application to verify school and get phone
        $stmt = $pdo->prepare("SELECT * FROM admission_applications WHERE id = ? AND school_id = ?");
        $stmt->execute([$app_id, $school_id]);
        $app = $stmt->fetch();
        
        if (!$app) throw new Exception("Application Node Not Found.");
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE admission_applications SET status = ?, admin_comment = ? WHERE id = ?");
        $stmt->execute([$status, $comment, $app_id]);
        
        // Finalize Enrollment if accepted
        if ($status === 'accepted' && $target_class_id > 0) {
            enrollStudent($pdo, $school_id, $app, $target_class_id);
        }
        
        $pdo->commit();
        
        // SMS Synchronization Logic
        if ($send_sms) {
            $phone = $app['parent_phone'];
            $student_name = $app['full_name'];
            $msg = "";
            
            if ($status === 'accepted') {
                $msg = "Congratulations! $student_name has been offered admission. Please visit the school office for finalize the enrollment. - Admin";
            } elseif ($status === 'rejected') {
                $msg = "We regret to inform you that the admission application for $student_name was not successful at this time. - Admin";
            }
            
            if ($msg) {
                // SMS Credit Sync
                deductCredits($pdo, $school_id, 1, "Admission Update SMS to $phone", 'credit_per_sms');
            }
        }
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'batch_process') {
        // Requires Admin/Owner or Authorized Staff
        if (!isset($_SESSION['role'])) throw new Exception("Session Expired.");
        $can_process = ($_SESSION['role'] === 'owner' || $_SESSION['role'] === 'super_admin' || (isset($staff_permissions) && $staff_permissions['can_manage_students']));
        if (!$can_process) {
            throw new Exception("Unauthorized Access Node");
        }
        
        parse_str($_POST['data'], $input);
        $ids_raw = $input['app_ids'] ?? '';
        $ids = array_filter(explode(',', $ids_raw), 'is_numeric');
        
        if (empty($ids)) throw new Exception("No applicants selected for synchronization.");
        
        $status = $input['status'];
        $comment = $input['comment'] ?? '';
        $send_sms = isset($input['send_sms']) ? true : false;
        $custom_msg = !empty($input['custom_sms']) ? $input['custom_sms'] : null;
        $batch_class_id = $input['target_class_id'] ?? 'applied';
        
        $school_id = $_SESSION['school_id'];
        
        // 1. Fetch Selected Applicants
        $in_query = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM admission_applications WHERE id IN ($in_query) AND school_id = ?");
        $stmt->execute(array_merge($ids, [$school_id]));
        $apps = $stmt->fetchAll();
        
        if (empty($apps)) throw new Exception("Invalid applicant registry access.");
        
        // 2. Credit Audit if SMS enabled
        if ($send_sms) {
            $msg_len = $custom_msg ? strlen($custom_msg) : 100;
            $pages = ceil($msg_len / 160);
            $total_sms = count($apps) * $pages;
            
            // Deduct credits
            $activity = "Batch Admission SMS ($status) for " . count($apps) . " applicants";
            if (!deductCredits($pdo, $school_id, $total_sms, $activity, 'credit_per_sms')) {
                throw new Exception("Insufficient institutional credits to commission this broadcast.");
            }
        }
        
        // 3. Update Statuses in Bulk
        $pdo->beginTransaction();
        try {
            $update_stmt = $pdo->prepare("UPDATE admission_applications SET status = ?, admin_comment = ? WHERE id = ?");
            foreach ($apps as $app) {
                $update_stmt->execute([$status, $comment, $app['id']]);
                
                // Enrollment Synchronizer
                if ($status === 'accepted') {
                    $target_cid = ($batch_class_id === 'applied') ? $app['class_id'] : intval($batch_class_id);
                    if ($target_cid > 0) {
                        enrollStudent($pdo, $school_id, $app, $target_cid);
                    }
                }
                // Trigger SMS (Mocked for environment synchronization)
                if ($send_sms) {
                    $phone = $app['parent_phone'];
                    $name = $app['full_name'];
                    $msg = $custom_msg;
                    if (!$msg) {
                        if ($status === 'accepted') {
                            $msg = "Admission Update: " . $name . " has been offered admission at our institution. Please proceed for documentation.";
                        } else {
                            $msg = "Admission Update: We regret to inform you that the application for " . $name . " was not successful at this time.";
                        }
                    }
                    // actualSMSProviderSend($phone, $msg);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        echo json_encode(['success' => true]);
    }
    else {
        throw new Exception("Unknown Orchestration Directive");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
