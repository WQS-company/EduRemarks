<?php
// ajax/generate_id_cards.php
ob_start(); // start buffering to suppress stray output
error_reporting(E_ERROR);
ini_set('display_errors', 0);
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    die(json_encode(['success'=>false,'message'=>'Invalid request.']));
}


function sendError($msg) {
    ob_end_clean();
    die(json_encode(['success'=>false, 'message'=>$msg]));
}

$school_id    = $active_school['id'] ?? null;
$template_key = sanitize($_POST['template_key'] ?? '');
$card_type    = sanitize($_POST['card_type'] ?? 'student');
$member_ids   = array_map('intval', $_POST['member_ids'] ?? []);

if (!$school_id || !$template_key || empty($member_ids)) {
    sendError('Missing required parameters.');
}

// 1. Validate template exists
$tpl_stmt = $pdo->prepare("SELECT * FROM id_card_templates WHERE template_key = ? AND is_active = 1");
$tpl_stmt->execute([$template_key]);
$template = $tpl_stmt->fetch();
if (!$template) sendError('Invalid template.');

// 2. Fetch credit cost
$cost_row = $pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'credit_cost_id_card'")->fetchColumn();
$cost_per  = (int)($cost_row ?: 10);
$total_cost= count($member_ids) * $cost_per;

// 3. Check + deduct credits
$stmt = $pdo->prepare("SELECT credits, billing_mode, subscription_active, subscription_start, subscription_end FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school_data = $stmt->fetch();
$current_credits = (int)($school_data['credits'] ?? 0);

$is_subscribed = false;
if ($school_data['billing_mode'] === 'subscription' && $school_data['subscription_active'] == 1) {
    $today = date('Y-m-d');
    if ($today >= $school_data['subscription_start'] && $today <= $school_data['subscription_end']) {
        $is_subscribed = true;
    }
}

if (!$is_subscribed) {
    if ($current_credits < $total_cost) {
        sendError("Insufficient credits. Need {$total_cost} cr, have {$current_credits} cr.");
    }
}

if (!deductCredits($pdo, $school_id, $total_cost, "ID Card Generation ({$card_type}, {$template_key}, ".count($member_ids)." cards)", null)) {
    sendError('Credit deduction failed.');
}

// 4. Fetch school info
$sch = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$sch->execute([$school_id]);
$school = $sch->fetch();

// 5. Fetch members
$members = [];
if ($card_type === 'student') {
    $ids_in = implode(',', $member_ids);
    $stmt = $pdo->prepare("SELECT id, full_name, admission_no, student_class, gender, dob, guardian_name, guardian_phone, address, image_path FROM students WHERE id IN ($ids_in) AND school_id = ?");
    $stmt->execute([$school_id]);
    $members = $stmt->fetchAll();
} else {
    $ids_in = implode(',', $member_ids);
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.email, u.phone, u.profile_picture, sd.created_at FROM users u JOIN staff_details sd ON sd.user_id = u.id WHERE u.id IN ($ids_in) AND sd.school_id = ?");
    $stmt->execute([$school_id]);
    $members = $stmt->fetchAll();
}

if (empty($members)) sendError('No valid members found.');

// 6. Build HTML for all cards (front + back, 2 per A4 page)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$base_path = dirname(dirname(__FILE__));

function buildQRUrl($data) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=' . urlencode($data);
}
function buildBarcodeUrl($data) {
    return 'https://barcodeapi.org/api/auto/' . urlencode($data);
}

function getPhoto($path, $base_path) {
    if (!empty($path)) {
        $abs = $base_path . '/' . ltrim($path, '/');
        if (file_exists($abs)) {
            $type = pathinfo($abs, PATHINFO_EXTENSION);
            $mime = in_array(strtolower($type), ['jpg','jpeg']) ? 'image/jpeg' : 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
        }
    }
    return null;
}

function getSchoolLogo($school, $base_path) {
    if (!empty($school['logo_path'])) {
        $abs = $base_path . '/' . ltrim($school['logo_path'], '/');
        if (file_exists($abs)) {
            $type = pathinfo($abs, PATHINFO_EXTENSION);
            $mime = in_array(strtolower($type), ['jpg','jpeg']) ? 'image/jpeg' : 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
        }
    }
    return null;
}

// ID card size: 85.6mm × 54mm (CR80 standard) — at 96dpi ≈ 323px × 204px
// We'll render at 2x for quality: 646px × 408px, then use CSS to scale for PDF

$school_logo_b64 = getSchoolLogo($school, $base_path);
$school_name_safe = htmlspecialchars($school['school_name']);
$school_addr_safe = htmlspecialchars($school['school_address']);

// Fetch signatures
$prop_sig = $school['proprietor_signature'] ?? null;
$dir_sig  = $school['director_signature'] ?? null;
$prop_b64 = $prop_sig ? getPhoto($prop_sig, $base_path) : null;

$cards_html = '';

foreach ($members as $m) {
    $name     = htmlspecialchars($m['full_name']);
    $id_no    = $card_type === 'student' ? htmlspecialchars($m['admission_no'] ?? '') : htmlspecialchars($m['id']);
    $role_txt = $card_type === 'student'
        ? htmlspecialchars(($m['student_class'] ?? '') . ' · ' . ($m['gender'] ?? ''))
        : 'Staff Member';
    $dob_txt  = !empty($m['dob']) ? date('d M Y', strtotime($m['dob'])) : 'N/A';
    $phone    = htmlspecialchars($m['phone'] ?? $m['guardian_phone'] ?? '---');
    $email    = htmlspecialchars($m['email'] ?? '---');
    $photo_b64 = getPhoto(
        $card_type === 'student' ? ($m['image_path'] ?? null) : ($m['profile_picture'] ?? null),
        $base_path
    );

    // Secure verification URL QR payload
    $qr_data = $base_url . "/verify.php?id=" . urlencode(base64_encode($m['id'])) . "&t=" . $card_type . "&s=" . $school_id;
    $bc_data     = $id_no ?: $m['id'];

    // Use default picture if no photo provided
    $photo_html = $photo_b64
        ? "<img src=\"{$photo_b64}\" style=\"width:100%;height:100%;object-fit:cover;\">"
        : "<img src=\"../img/default_picture.png\" style=\"width:100%;height:100%;object-fit:cover;\">";
    $logo_html = $school_logo_b64
        ? "<img src=\"{$school_logo_b64}\" style=\"max-height:32px;max-width:70px;object-fit:contain;\">"
        : "<span style=\"font-size:10px;font-weight:800;color:#fff;\">LOGO</span>";

    // Generate front and back based on template
    $front = buildTemplateFront($template_key, $school, $school_name_safe, $school_addr_safe, $logo_html, $name, $id_no, $role_txt, $dob_txt, $phone, $email, $photo_html, $qr_data, $bc_data, $template, $card_type);
    $back  = buildTemplateBack($template_key, $school, $school_name_safe, $logo_html, $prop_b64, $school_addr_safe, $card_type);

    $cards_html .= "
    <div class='card-page'>
        <div class='id-card card-left'>{$front}</div>
        <div class='id-card card-right'>{$back}</div>
    </div>";
}

function buildTemplateFront($key, $school, $school_name, $addr, $logo_html, $name, $id_no, $role_txt, $dob, $phone, $email, $photo_html, $qr_data, $bc_data, $template, $card_type) {
    $qr_img  = $template['has_qr']      ? "<img crossorigin='anonymous' src='".buildQRUrl($qr_data)."' width='60' height='60' style='display:block;'>" : '';
    $bc_img  = $template['has_barcode'] ? "<img crossorigin='anonymous' src='".buildBarcodeUrl($bc_data)."' style='max-width:120px;height:32px;object-fit:contain;display:block;'>" : '';

    $year = date('Y') + 1;

    switch($key) {
        case 'tpl_crimson_wave':
            return "
            <div style='background:#fff;width:100%;height:100%;font-family:Arial,sans-serif;position:relative;overflow:hidden;border-radius:8px;'>
                <div style='background:linear-gradient(135deg,#c0392b,#e74c3c);height:70px;display:flex;align-items:center;justify-content:space-between;padding:0 14px;position:relative;'>
                    <div style='position:absolute;bottom:-18px;left:0;right:0;height:36px;background:#c0392b;clip-path:ellipse(55% 100% at 50% 0);'></div>
                    <div style='position:absolute;top:-40px;left:-30px;width:100px;height:80px;background:rgba(255,255,255,.08);border-radius:50%;'></div>
                    <div style='z-index:2;'>{$logo_html}</div>
                    <div style='text-align:right;z-index:2;'><div style='color:#fff;font-size:7px;text-transform:uppercase;letter-spacing:1px;opacity:.8;'>Institution</div><div style='color:#fff;font-size:9px;font-weight:700;'>{$school_name}</div></div>
                </div>
                <div style='display:flex;gap:10px;padding:16px 14px 0;'>
                    <div style='width:55px;height:65px;border-radius:6px;overflow:hidden;flex-shrink:0;border:2px solid #c0392b;margin-top:-8px;background:#f1f5f9;'>{$photo_html}</div>
                    <div>
                        <div style='font-size:13px;font-weight:700;color:#1e293b;margin-top:4px;'>{$name}</div>
                        <div style='font-size:8px;color:#c0392b;font-weight:600;text-transform:uppercase;'>{$role_txt}</div>
                        <div style='font-size:7px;color:#64748b;margin-top:4px;'>ID: {$id_no}</div>
                    </div>
                    <div style='margin-left:auto;'>{$qr_img}</div>
                </div>
                <div style='margin:10px 14px 0;padding-top:8px;border-top:1px solid #f1f5f9;display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:7px;'>
                    <div><span style='color:#94a3b8;'>D.O.B:</span> <strong>{$dob}</strong></div>
                    <div><span style='color:#94a3b8;'>Phone:</span> <strong>{$phone}</strong></div>
                    <div><span style='color:#94a3b8;'>Valid:</span> <strong>Dec 31, {$year}</strong></div>
                    <div style='text-align:right;'>{$bc_img}</div>
                </div>
            </div>";

        case 'tpl_azure_diamond':
            return "
            <div style='background:#fff;width:100%;height:100%;font-family:Arial,sans-serif;position:relative;overflow:hidden;border-radius:8px;'>
                <div style='background:linear-gradient(135deg,#1e3a8a,#2563eb);height:65px;display:flex;align-items:center;padding:0 14px;gap:8px;position:relative;overflow:hidden;'>
                    <div style='position:absolute;right:-20px;top:-20px;width:80px;height:80px;background:rgba(255,255,255,.07);border-radius:8px;transform:rotate(20deg);'></div>
                    <div style='position:absolute;right:10px;bottom:-20px;width:50px;height:50px;background:rgba(255,255,255,.05);border-radius:6px;transform:rotate(15deg);'></div>
                    <div style='z-index:2;'>{$logo_html}</div>
                    <div style='z-index:2;flex:1;'><div style='color:#bfdbfe;font-size:7px;font-weight:600;text-transform:uppercase;'>{$school_name}</div></div>
                </div>
                <div style='display:flex;gap:10px;padding:10px 14px 0;'>
                    <div style='width:60px;height:70px;border-radius:50%;overflow:hidden;flex-shrink:0;border:3px solid #2563eb;margin-top:-14px;background:#e2e8f0;position:relative;z-index:1;'>{$photo_html}</div>
                    <div style='flex:1;'>
                        <div style='font-size:13px;font-weight:700;color:#1e293b;'>{$name}</div>
                        <div style='font-size:8px;color:#2563eb;font-weight:600;'>{$role_txt}</div>
                        <div style='font-size:7px;color:#64748b;margin-top:2px;'>ID: {$id_no}</div>
                    </div>
                </div>
                <div style='margin:8px 14px 0;font-size:7px;'>
                    <table style='width:100%;border-collapse:collapse;'>
                        <tr><td style='color:#94a3b8;padding:2px 0;'>D.O.B</td><td style='font-weight:600;'>{$dob}</td><td style='color:#94a3b8;'>Phone</td><td style='font-weight:600;'>{$phone}</td></tr>
                        <tr><td style='color:#94a3b8;padding:2px 0;'>Email</td><td colspan='3' style='font-weight:600;font-size:6px;'>{$email}</td></tr>
                    </table>
                </div>
                <div style='margin:6px 14px;display:flex;justify-content:space-between;align-items:flex-end;'>
                    <div>{$bc_img}</div>
                    <div>{$qr_img}</div>
                </div>
            </div>";

        case 'tpl_midnight_elite':
            return "
            <div style='background:#0f172a;width:100%;height:100%;font-family:Arial,sans-serif;position:relative;overflow:hidden;border-radius:8px;'>
                <div style='position:absolute;top:-30px;right:-30px;width:100px;height:100px;background:rgba(99,102,241,.2);border-radius:50%;'></div>
                <div style='position:absolute;bottom:-20px;left:-20px;width:80px;height:80px;background:rgba(244,180,0,.1);border-radius:50%;'></div>
                <div style='padding:12px 14px 8px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,.08);'>
                    <div>{$logo_html}</div>
                    <div style='text-align:right;'><div style='color:#94a3b8;font-size:6px;text-transform:uppercase;letter-spacing:1px;'>Official ID</div><div style='color:#F4B400;font-size:7px;font-weight:700;'>{$school_name}</div></div>
                </div>
                <div style='display:flex;gap:12px;padding:10px 14px 0;align-items:flex-start;'>
                    <div style='width:55px;height:65px;border-radius:6px;overflow:hidden;flex-shrink:0;border:2px solid #F4B400;background:#1e293b;'>{$photo_html}</div>
                    <div>
                        <div style='font-size:12px;font-weight:700;color:#fff;line-height:1.2;'>{$name}</div>
                        <div style='font-size:7px;color:#F4B400;font-weight:600;margin:2px 0;text-transform:uppercase;'>{$role_txt}</div>
                        <div style='font-size:6px;color:#64748b;'>ID: {$id_no}</div>
                        <div style='margin-top:6px;font-size:6px;color:#94a3b8;'>Valid: Dec 31, {$year}</div>
                    </div>
                    <div style='margin-left:auto;'>{$qr_img}</div>
                </div>
                <div style='margin:6px 14px 0;padding-top:6px;border-top:1px solid rgba(255,255,255,.06);display:flex;gap:6px;font-size:6px;color:#64748b;'>
                    <span>DOB: <strong style='color:#94a3b8;'>{$dob}</strong></span>
                    <span>·</span>
                    <span>Tel: <strong style='color:#94a3b8;'>{$phone}</strong></span>
                </div>
                {$bc_img}
            </div>";

        case 'tpl_sapphire_stripe':
            return "
            <div style='background:#fff;width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;'>
                <div style='height:14px;background:repeating-linear-gradient(45deg,#1d4ed8,#1d4ed8 8px,#3b82f6 8px,#3b82f6 16px);'></div>
                <div style='padding:8px 14px;display:flex;justify-content:space-between;align-items:center;'>
                    <div>{$logo_html}</div>
                    <div style='text-align:right;font-size:7px;'>
                        <div style='font-weight:700;color:#1e293b;'>{$school_name}</div>
                        <div style='color:#64748b;font-size:6px;'>Official Identification</div>
                    </div>
                </div>
                <div style='padding:0 14px;display:flex;gap:10px;'>
                    <div style='width:55px;height:65px;border-radius:4px;overflow:hidden;flex-shrink:0;border:2px solid #1d4ed8;background:#e2e8f0;'>{$photo_html}</div>
                    <div style='flex:1;'>
                        <div style='font-size:12px;font-weight:700;color:#1e293b;'>{$name}</div>
                        <div style='font-size:7px;color:#2563eb;font-weight:600;margin:2px 0;'>{$role_txt}</div>
                        <div style='font-size:6px;color:#94a3b8;'>ID#: {$id_no}</div>
                        <div style='font-size:6px;color:#94a3b8;margin-top:2px;'>DOB: {$dob}</div>
                        <div style='font-size:6px;color:#94a3b8;'>Tel: {$phone}</div>
                    </div>
                </div>
                <div style='padding:8px 14px 4px;display:flex;justify-content:space-between;align-items:center;'>
                    <div>{$bc_img}</div>
                    <div style='font-size:6px;text-align:right;color:#94a3b8;'>Valid till Dec {$year}</div>
                </div>
                <div style='height:8px;background:#1d4ed8;'></div>
            </div>";

        case 'tpl_emerald_circuit':
            return "
            <div style='background:#f0fdf4;width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;position:relative;'>
                <div style='background:linear-gradient(135deg,#065f46,#059669);height:60px;display:flex;align-items:center;padding:0 14px;gap:8px;position:relative;overflow:hidden;'>
                    <div style='position:absolute;right:0;top:0;bottom:0;width:50px;background:rgba(255,255,255,.06);clip-path:polygon(30% 0,100% 0,100% 100%,0 100%);'></div>
                    <div>{$logo_html}</div>
                    <div><div style='color:#a7f3d0;font-size:6px;text-transform:uppercase;letter-spacing:1px;'>ID Card</div><div style='color:#fff;font-size:8px;font-weight:700;'>{$school_name}</div></div>
                </div>
                <div style='display:flex;gap:10px;padding:10px 14px 0;'>
                    <div style='width:55px;height:65px;border-radius:50%;overflow:hidden;flex-shrink:0;border:3px solid #059669;background:#d1fae5;margin-top:-12px;z-index:1;'>{$photo_html}</div>
                    <div>
                        <div style='font-size:12px;font-weight:700;color:#064e3b;'>{$name}</div>
                        <div style='font-size:7px;color:#059669;font-weight:600;'>{$role_txt}</div>
                        <div style='font-size:6px;color:#6b7280;margin-top:2px;'>ID: {$id_no} | DOB: {$dob}</div>
                    </div>
                    <div style='margin-left:auto;'>{$qr_img}</div>
                </div>
                <div style='margin:6px 14px 4px;padding-top:6px;border-top:1px dashed #a7f3d0;font-size:6px;color:#374151;display:flex;gap:8px;flex-wrap:wrap;'>
                    <span>Tel: {$phone}</span><span>·</span><span>Email: {$email}</span>
                </div>
                {$bc_img}
            </div>";

        case 'tpl_solar_wave':
            return "
            <div style='background:#fff;width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;position:relative;'>
                <div style='background:linear-gradient(135deg,#92400e,#d97706,#f59e0b);height:65px;position:relative;overflow:hidden;display:flex;align-items:flex-end;padding:8px 14px;'>
                    <div style='position:absolute;bottom:-12px;left:-10px;right:-10px;height:30px;background:#fff;border-radius:50%;'></div>
                    <div style='display:flex;justify-content:space-between;width:100%;align-items:center;position:relative;z-index:1;'>
                        <div>{$logo_html}</div>
                        <div style='color:#fff;font-size:8px;font-weight:700;text-align:right;'>{$school_name}</div>
                    </div>
                </div>
                <div style='text-align:center;padding:14px 14px 0;'>
                    <div style='width:55px;height:65px;border-radius:8px;overflow:hidden;border:3px solid #f59e0b;background:#e2e8f0;margin:0 auto 8px;'>{$photo_html}</div>
                    <div style='font-size:12px;font-weight:700;color:#1e293b;'>{$name}</div>
                    <div style='font-size:7px;color:#d97706;font-weight:600;'>{$role_txt}</div>
                    <div style='font-size:6px;color:#94a3b8;margin-top:2px;'>ID: {$id_no}</div>
                </div>
                <div style='margin:6px 14px 0;display:flex;justify-content:space-between;align-items:center;font-size:6px;color:#64748b;'>
                    <span>DOB: {$dob} · Tel: {$phone}</span>
                    <div>{$qr_img}</div>
                </div>
                <div style='margin-top:4px;padding:0 14px;'>{$bc_img}</div>
            </div>";

        case 'tpl_royal_prestige':
            return "
            <div style='background:#faf5ff;width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;position:relative;'>
                <div style='background:linear-gradient(135deg,#4c1d95,#7c3aed);height:60px;padding:0 14px;display:flex;align-items:center;justify-content:space-between;'>
                    <div>{$logo_html}</div>
                    <div style='color:#fff;text-align:right;'><div style='font-size:6px;opacity:.7;text-transform:uppercase;letter-spacing:1px;'>Official</div><div style='font-size:8px;font-weight:700;'>{$school_name}</div></div>
                </div>
                <div style='padding:10px 14px;display:flex;gap:10px;'>
                    <div style='width:55px;height:70px;overflow:hidden;flex-shrink:0;clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);background:#ede9fe;'>{$photo_html}</div>
                    <div style='flex:1;'>
                        <div style='font-size:12px;font-weight:700;color:#4c1d95;'>{$name}</div>
                        <div style='font-size:7px;color:#7c3aed;font-weight:600;'>{$role_txt}</div>
                        <div style='font-size:6px;color:#6b7280;margin-top:2px;'>ID: {$id_no}</div>
                        <div style='font-size:6px;color:#6b7280;'>DOB: {$dob}</div>
                        <div style='font-size:6px;color:#6b7280;'>Tel: {$phone}</div>
                    </div>
                    <div style='text-align:right;'>{$qr_img}</div>
                </div>
                <div style='height:8px;background:linear-gradient(90deg,#4c1d95,#7c3aed);margin-top:4px;'></div>
            </div>";

        case 'tpl_navy_crest':
            return "
            <div style='background:#fff;width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;'>
                <div style='background:#1e3a5f;padding:10px 14px;display:flex;align-items:center;gap:8px;'>
                    <div style='width:36px;height:36px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;'>{$logo_html}</div>
                    <div><div style='color:#bfdbfe;font-size:6px;text-transform:uppercase;font-weight:700;letter-spacing:1px;'>OFFICIAL ID · {$school_name}</div><div style='color:#fff;font-size:8px;font-weight:700;'>".strtoupper($card_type)." CARD</div></div>
                </div>
                <div style='display:flex;gap:10px;padding:10px 14px 0;'>
                    <div style='width:55px;height:65px;overflow:hidden;border:2px solid #1e3a5f;border-radius:6px;flex-shrink:0;background:#e2e8f0;margin-top:-6px;z-index:1;'>{$photo_html}</div>
                    <div style='flex:1;'>
                        <div style='font-size:13px;font-weight:700;color:#1e293b;'>{$name}</div>
                        <div style='font-size:7px;color:#1e3a5f;font-weight:600;border-left:2px solid #1e3a5f;padding-left:5px;margin:2px 0;'>{$role_txt}</div>
                        <div style='font-size:6px;color:#64748b;'>ID: {$id_no} | DOB: {$dob}</div>
                        <div style='font-size:6px;color:#64748b;'>TEL: {$phone}</div>
                    </div>
                </div>
                <div style='margin:6px 14px;display:flex;justify-content:space-between;align-items:center;'>
                    <div>{$bc_img}</div>
                    <div style='font-size:6px;color:#94a3b8;'>Exp: Dec {$year}</div>
                </div>
            </div>";

        case 'tpl_crimson_diagonal':
            return "
            <div style='background:#fff;width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;position:relative;'>
                <div style='position:absolute;top:0;right:0;width:45%;height:100%;background:linear-gradient(180deg,#dc2626,#991b1b);clip-path:polygon(20% 0,100% 0,100% 100%,0 100%);'></div>
                <div style='position:relative;z-index:1;padding:12px 14px;'>
                    <div style='display:flex;justify-content:space-between;margin-bottom:8px;'>
                        <div>{$logo_html}</div>
                        <div style='color:#fff;text-align:right;font-size:7px;font-weight:700;'>{$school_name}</div>
                    </div>
                    <div style='display:flex;gap:10px;'>
                        <div style='width:55px;height:65px;overflow:hidden;border-radius:6px;flex-shrink:0;border:2px solid #dc2626;background:#e2e8f0;'>{$photo_html}</div>
                        <div style='flex:1;'>
                            <div style='font-size:12px;font-weight:700;color:#1e293b;'>{$name}</div>
                            <div style='font-size:7px;color:#dc2626;font-weight:600;'>{$role_txt}</div>
                            <div style='font-size:6px;color:#64748b;margin-top:2px;'>ID: {$id_no}</div>
                            <div style='font-size:6px;color:#64748b;'>DOB: {$dob}</div>
                        </div>
                        <div style='color:#fff;'>{$qr_img}</div>
                    </div>
                    <div style='margin-top:8px;'>{$bc_img}</div>
                </div>
            </div>";

        case 'tpl_platinum_modern':
        default:
            return "
            <div style='background:linear-gradient(180deg,#f8fafc,#fff);width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;'>
                <div style='background:linear-gradient(135deg,#334155,#475569);height:8px;'></div>
                <div style='padding:10px 14px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9;'>
                    <div>{$logo_html}</div>
                    <div style='text-align:right;font-size:7px;color:#475569;font-weight:600;'>{$school_name}</div>
                </div>
                <div style='display:flex;gap:10px;padding:10px 14px 0;'>
                    <div style='width:55px;height:65px;border-radius:6px;overflow:hidden;flex-shrink:0;border:2px dashed #cbd5e1;background:#f1f5f9;'>{$photo_html}</div>
                    <div style='flex:1;'>
                        <div style='font-size:12px;font-weight:700;color:#1e293b;'>{$name}</div>
                        <div style='font-size:7px;color:#475569;font-weight:600;'>{$role_txt}</div>
                        <div style='font-size:6px;color:#94a3b8;margin-top:2px;'>ID: {$id_no}</div>
                        <div style='font-size:6px;color:#94a3b8;'>DOB: {$dob} | Tel: {$phone}</div>
                        <div style='font-size:6px;color:#94a3b8;'>Email: {$email}</div>
                    </div>
                    <div>{$qr_img}</div>
                </div>
                <div style='padding:6px 14px;display:flex;justify-content:space-between;align-items:center;'>
                    <div>{$bc_img}</div>
                    <div style='font-size:6px;color:#94a3b8;'>Exp: {$year}</div>
                </div>
                <div style='background:linear-gradient(135deg,#334155,#475569);height:4px;'></div>
            </div>";
    }
}

function buildTemplateBack($key, $school, $school_name, $logo_html, $prop_b64, $addr, $card_type) {
    $prop_html = $prop_b64 ? "<img src='{$prop_b64}' style='max-height:28px;max-width:80px;object-fit:contain;'>" : "<div style='border-bottom:1px solid #94a3b8;width:80px;margin:0 auto;'></div>";
    $colors = [
        'tpl_crimson_wave' => ['#c0392b','#fff'],'tpl_azure_diamond' => ['#1e3a8a','#fff'],
        'tpl_midnight_elite' => ['#0f172a','#F4B400'],'tpl_sapphire_stripe' => ['#1d4ed8','#fff'],
        'tpl_emerald_circuit' => ['#065f46','#fff'],'tpl_solar_wave' => ['#92400e','#fff'],
        'tpl_royal_prestige' => ['#4c1d95','#fff'],'tpl_navy_crest' => ['#1e3a5f','#fff'],
        'tpl_crimson_diagonal' => ['#991b1b','#fff'],'tpl_platinum_modern' => ['#334155','#fff'],
    ];
    $c1 = $colors[$key][0] ?? '#1a56db';
    $c2 = $colors[$key][1] ?? '#fff';
    return "
    <div style='background:#fff;width:100%;height:100%;font-family:Arial,sans-serif;border-radius:8px;overflow:hidden;display:flex;flex-direction:column;'>
        <div style='background:{$c1};padding:10px 14px;display:flex;align-items:center;gap:8px;'>
            <div>{$logo_html}</div>
            <div style='color:{$c2};'><div style='font-size:8px;font-weight:700;'>{$school_name}</div><div style='font-size:6px;opacity:.7;'>Official Identification Card</div></div>
        </div>
        <div style='flex:1;padding:10px 14px;font-size:6.5px;color:#374151;line-height:1.8;'>
            <div style='margin-bottom:4px;font-weight:700;color:{$c1};font-size:7px;text-transform:uppercase;'>This card is the property of {$school_name}.</div>
            <div>If found, please return to the institution at the address below.</div>
            <div style='margin-top:6px;'><strong>Address:</strong> {$addr}</div>
            <div><strong>Type:</strong> ".ucfirst($card_type)." Identification</div>
            <div style='margin-top:8px;padding:6px 8px;background:#f8fafc;border-radius:4px;border-left:2px solid {$c1};'>
                <strong>Emergency Notice:</strong> The holder of this card is entitled to institutional privileges. Misuse is subject to disciplinary action.
            </div>
        </div>
        <div style='border-top:1px solid #f1f5f9;padding:8px 14px;display:flex;justify-content:space-between;align-items:flex-end;'>
            <div style='text-align:center;'>
                {$prop_html}
                <div style='font-size:6px;color:#94a3b8;margin-top:2px;'>Authorized Signature</div>
                <div style='font-size:6px;font-weight:700;color:#374151;'>Principal / Proprietor</div>
            </div>
            <div style='background:{$c1};color:{$c2};font-size:6px;font-weight:700;padding:4px 10px;border-radius:12px;'>".strtoupper($card_type)."</div>
        </div>
    </div>";
}

$cards_html = "
<style>
  * { box-sizing: border-box; }
  .pdf-wrap { background: #fff; font-family: Arial, sans-serif; margin: 0; padding: 0; }
  .card-page { 
      width: 210mm; height: 296mm; /* Marginally smaller than exactly 297mm mapping to prevent silent overflow into a blank page */
      display: flex; align-items: center; justify-content: center; gap: 6mm; /* Small separation between the front & back card faces */
      background: #fff; 
      page-break-after: always;
  }
  .card-page:last-of-type { page-break-after: auto; }
  .id-card {
      width: 323.5px; height: 204px; /* Exact CR80 px dimension equivalents locked at 96 DPI base */
      position: relative; overflow: hidden; 
      background: #fff; box-shadow: 0 0 0 1px #cbd5e1;
  }
  /* Subtle cutting boundary guide */
  .id-card::after {
      content: ''; position: absolute; inset: 0; border: 1px dashed rgba(0,0,0,0.15); pointer-events: none; z-index: 999;
  }
</style>
<div class='pdf-wrap'>" . $cards_html . "</div>";

// 7. Log draft as 'pending' (client will finish PDF rendering)
$log = $pdo->prepare("INSERT INTO generated_id_cards (school_id, template_key, card_type, member_ids, pdf_path, credits_used, status) VALUES (?,?,?,?,NULL,?,'pending')");
$log->execute([$school_id, $template_key, $card_type, implode(',', $member_ids), $total_cost]);
$draft_id = $pdo->lastInsertId();

ob_end_clean(); // discard any warning/html output captured by output buffering
echo json_encode([
    'success'    => true,
    'message'    => count($members) . ' ID card(s) data mapped successfully!',
    'html_data'  => $cards_html,
    'draft_id'   => $draft_id,
    'credits_used' => $total_cost,
]);
?>
