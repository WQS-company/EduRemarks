<?php
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = intval($_GET['class_id'] ?? 0);
$session_id = intval($_GET['session_id'] ?? 0);
$term_id = intval($_GET['term_id'] ?? 0);
$student_id = intval($_GET['student_id'] ?? 0);
$show_pos = isset($_GET['show_pos']) ? intval($_GET['show_pos']) : 1;
$template_id = intval($_GET['template_id'] ?? 0);

if (!$class_id || !$session_id || !$term_id) {
    die("Institutional link verification failed. Required academic parameters missing.");
}

// Fetch term and session bounds
$term_stmt = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
$term_stmt->execute([$term_id]);
$term_name = $term_stmt->fetchColumn();

$session_stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
$session_stmt->execute([$session_id]);
$session_name = $session_stmt->fetchColumn();

// Load class info
$clsQ = $pdo->prepare("SELECT name, section FROM classes WHERE id = ? AND school_id = ?");
$clsQ->execute([$class_id, $school_id]);
$classData = $clsQ->fetch();
$class_name = $classData['name'] ?? null;
$section_name = $classData['section'] ?? 'Academic Performance';

if (!$class_name) { 
    header('Location: report_management.php?class_id=' . $class_id); 
    exit; 
}

$cls = [
    'name' => $class_name,
    'school_name' => $active_school['school_name'] ?? 'EduRemarks Institution',
    'motto' => $active_school['motto'] ?? '',
    'school_address' => $active_school['address'] ?? '',
    'logo' => $active_school['logo_path'] ?? '',
    'term' => $term_name,
    'session' => $session_name,
    'next_term_date' => '', // Could be fetched from schema if exist
    'section_name' => $section_name,
    'pos_visible' => (bool)$show_pos
];

// Detect school type (Tertiary vs K-12)
$school_type_str = strtolower($active_school['school_type'] ?? '');
$is_higher_ed = (
    strpos($school_type_str, 'tertiary') !== false ||
    strpos($school_type_str, 'vocational') !== false ||
    strpos($school_type_str, 'polytechnic') !== false ||
    strpos($school_type_str, 'university') !== false ||
    strpos($school_type_str, 'college') !== false
);

// Students
$stuConditions = "class_id = ? AND school_id = ?";
$stuParams = [$class_id, $school_id];

if ($student_id) {
    $stQ = $pdo->prepare("
        SELECT s.* 
        FROM students s
        JOIN student_classes sc ON sc.student_id = s.id
        WHERE s.id = ? AND sc.class_id = ? AND sc.school_id = ?
    ");
    $stQ->execute([$student_id, $class_id, $school_id]);
} else {
    $stQ = $pdo->prepare("
        SELECT s.* 
        FROM students s
        JOIN student_classes sc ON sc.student_id = s.id
        WHERE sc.class_id = ? AND sc.school_id = ?
        ORDER BY s.full_name
    ");
    $stQ->execute([$class_id, $school_id]);
}
$students = $stQ->fetchAll();

if (empty($students)) {
    die("No students found.");
}

// Bulk Generation Billing Logic
if (!$student_id) {
    $class_count = count($students);
    $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'credit_student_result' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    $cost_per = $val ? floatval($val) : 1;
    $total_deduction = $class_count * $cost_per;

    $stmt = $pdo->prepare("SELECT credits, billing_mode, subscription_active, subscription_start, subscription_end FROM schools WHERE id = ?");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch();
    $current_credits = floatval($school['credits']);

    $is_subscribed = false;
    if ($school['billing_mode'] === 'subscription' && $school['subscription_active'] == 1) {
        $today = date('Y-m-d');
        if ($today >= $school['subscription_start'] && $today <= $school['subscription_end']) {
            $is_subscribed = true;
        }
    }

    if (!$is_subscribed) {
        if ($current_credits < $total_deduction) {
            die("Insufficient institutional liquidity to execute bulk academic synchronization.");
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE schools SET credits = credits - ? WHERE id = ?");
            $stmt->execute([$total_deduction, $school_id]);

            $stmt = $pdo->prepare("INSERT INTO credit_logs (school_id, amount, activity) VALUES (?, ?, ?)");
            $stmt->execute([$school_id, $total_deduction, "Bulk Report Generation deduction: {$class_name} ({$class_count} students)"]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Operational Synchronization Failure: " . $e->getMessage());
        }
    }
}

// Subjects
$subQ = $pdo->prepare("
    SELECT s.id, s.name 
    FROM subjects s 
    JOIN class_subjects cs ON s.id = cs.subject_id 
    WHERE cs.class_id = ?
");
$subQ->execute([$class_id]);
$subjects = $subQ->fetchAll();

// All scores
$allScores = [];
if ($students && $subjects) {
    $stuIds = array_column($students, 'id');
    $inIds  = implode(',', array_fill(0, count($stuIds), '?'));
    $params = array_merge([$class_id, $session_id, $term_id], $stuIds);
    $scQ = $pdo->prepare("SELECT * FROM student_results WHERE class_id = ? AND session_id = ? AND term_id = ? AND student_id IN ($inIds)");
    $scQ->execute($params);
    foreach ($scQ->fetchAll() as $sc) {
        $allScores[$sc['student_id']][$sc['subject_id']] = $sc;
    }
}

// All skills/traits
$allSkills = [];
if ($students) {
    $stuIds = array_column($students, 'id');
    $inIds  = implode(',', array_fill(0, count($stuIds), '?'));
    $params = array_merge([$class_id, $session_id, $term_id], $stuIds);
    $skQ = $pdo->prepare("SELECT * FROM student_traits WHERE class_id = ? AND session_id = ? AND term_id = ? AND student_id IN ($inIds)");
    $skQ->execute($params);
    foreach ($skQ->fetchAll() as $sk) {
        $allSkills[$sk['student_id']][$sk['trait_type']][strtoupper(trim($sk['trait_name']))] = $sk['rating'];
    }
}

// Ordinal suffix
function ordinal(int $n): string {
    if ($n >= 11 && $n <= 13) return $n . 'th';
    return match($n % 10) {
        1 => $n . 'st',
        2 => $n . 'nd',
        3 => $n . 'rd',
        default => $n . 'th',
    };
}

function calcGrade($total, $is_higher_ed = false) {
    if ($is_higher_ed) {
        if ($total >= 70) return 'A';
        if ($total >= 60) return 'B';
        if ($total >= 50) return 'C';
        if ($total >= 45) return 'D';
        if ($total >= 40) return 'E';
        if ($total > 0) return 'F';
        return '-';
    }
    if ($total >= 75) return 'A1';
    if ($total >= 70) return 'B2';
    if ($total >= 65) return 'B3';
    if ($total >= 60) return 'C4';
    if ($total >= 55) return 'C5';
    if ($total >= 50) return 'C6';
    if ($total >= 45) return 'D7';
    if ($total >= 40) return 'E8';
    if ($total > 0) return 'F9';
    return '-';
}

function calcRemark($grade) {
    $g = strtoupper(substr($grade, 0, 1));
    if ($g === 'A') return 'Excellent';
    if ($g === 'B') return 'Very Good';
    if ($g === 'C') return 'Good';
    if ($g === 'D') return 'Fair';
    if ($g === 'E') return 'Pass';
    if ($g === 'F') return 'Fail';
    return '-';
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Per-student totals
function studentTotals(array $scores, array $subjects): array {
    $total = 0; $count = 0;
    foreach ($subjects as $sub) {
        $sc = $scores[$sub['id']] ?? null;
        if ($sc && $sc['total'] !== null) {
            $total += $sc['total']; $count++;
        }
    }
    return ['total' => $total, 'count' => $count, 'avg' => $count ? round($total / $count, 1) : 0];
}

$studentSummaries = [];
foreach ($students as $stu) {
    $studentSummaries[$stu['id']] = studentTotals($allScores[$stu['id']] ?? [], $subjects);
}

// Tie-aware position calculation
$sorted = $studentSummaries;
uasort($sorted, fn($a, $b) => $b['total'] <=> $a['total']);
$positions = [];
$pos = 1; $prev_total = null; $prev_pos = 1; $rank = 1;
foreach ($sorted as $sid => $v) {
    if ($prev_total !== null && $v['total'] == $prev_total) {
        $positions[$sid] = $prev_pos;
    } else {
        $positions[$sid] = $pos;
        $prev_pos = $pos;
        $prev_total = $v['total'];
    }
    $pos++;
}

// Class stats
$allTotals = array_column($studentSummaries, 'total');
$classAvg  = count($allTotals) ? round(array_sum($allTotals) / count($allTotals), 1) : 0;
$highest   = count($allTotals) ? max($allTotals) : 0;
$lowest    = count($allTotals) ? min($allTotals) : 0;

// Per-subject positions (tie-aware)
$subjectPositions = [];
foreach ($subjects as $sub) {
    $sid = $sub['id'];
    $subScores = [];
    foreach ($students as $stu) {
        $sc = $allScores[$stu['id']][$sid] ?? null;
        if ($sc && $sc['total'] !== null) {
            $subScores[$stu['id']] = (int)$sc['total'];
        }
    }
    arsort($subScores);
    $p = 1; $pv = null; $pp = 1;
    foreach ($subScores as $stuId => $v) {
        if ($pv !== null && $v == $pv) { $subjectPositions[$stuId][$sid] = $pp; }
        else { $subjectPositions[$stuId][$sid] = $p; $pp = $p; $pv = $v; }
        $p++;
    }
}

$affectiveTraits  = ['PUNCTUALITY','ATTENDANCE','RELIABILITY','NEATNESS','POLITENESS','HONESTY','RELATIONSHIP WITH STUDENTS','SELF CONTROL','ATTENTIVENESS','PERSEVERANCE'];
$psychomotorTraits = ['HANDWRITING','GAMES','SPORTS','DRAWING & PAINTING','CRAFTS','MUSICAL SKILLS'];

$logoSrc = $cls['logo'] ? '../' . ltrim($cls['logo'], '/') : '';
$wmText  = strtoupper($cls['school_name'] ?? 'EDUREMARKS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?php echo get_label('Report Sheet'); ?> — <?= e($cls['name']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ══════════════════════════════════════════════════════════════
   RESET & BASE
══════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #d0cdc5; font-family: Arial, Helvetica, sans-serif; overflow-x: hidden; }

/* ══════════════════════════════════════════════════════════════
   CONTROL BAR (screen only)
══════════════════════════════════════════════════════════════ */
.control-bar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
  background: #1a2b4a; color: #fff;
  padding: 10px 16px; display: flex; align-items: center; gap: 10px;
  box-shadow: 0 2px 12px rgba(0,0,0,.4); min-height: 52px; flex-wrap: nowrap;
}
.control-bar h4 {
  margin: 0; font-size: .88rem; font-weight: 800; flex: 1; min-width: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ctrl-btn {
  border: none; border-radius: 8px; padding: 7px 14px;
  font-weight: 700; cursor: pointer; font-size: .82rem;
  text-decoration: none; display: inline-flex; align-items: center;
  gap: 5px; flex-shrink: 0; white-space: nowrap;
}
.ctrl-btn-print { background: #1e6fcf; color: #fff; }
.ctrl-btn-edit  { background: transparent; color: #fff; border: 1.5px solid rgba(255,255,255,.3); }
.ctrl-btn-back  { background: transparent; color: #94a3b8; font-size: .78rem; border: none; padding: 7px 8px; }
.btn-lbl { display: inline; }
@media(max-width:600px){
  .control-bar{padding:8px 10px;gap:6px;min-height:48px;}
  .control-bar h4{font-size:.74rem;}
  .ctrl-btn{padding:7px 9px;font-size:.74rem;border-radius:7px;gap:4px;}
  .ctrl-btn-edit .btn-lbl,.ctrl-btn-back .btn-lbl{display:none;}
}
@media(max-width:380px){
  .control-bar h4{font-size:.68rem;}
  .ctrl-btn-print .btn-lbl{display:none;}
  .ctrl-btn{padding:7px 8px;}
}
.ctrl-bar-spacer{height:52px;}
@media(max-width:600px){.ctrl-bar-spacer{height:48px;}}

/* ══════════════════════════════════════════════════════════════
   PAGE SCALER — centres scaled A4 pages on screen
══════════════════════════════════════════════════════════════ */
.page-scaler {
  width: 100%; display: flex; flex-direction: column;
  align-items: center; padding: 20px 8px 48px; gap: 0; overflow-x: hidden;
}

/* ══════════════════════════════════════════════════════════════
   PAGE WRAP — JS writes exact height so no gap below scaled page
══════════════════════════════════════════════════════════════ */
.page-wrap {
  width: 100%; display: flex; justify-content: center;
  overflow: hidden; 
}

/* ══════════════════════════════════════════════════════════════
   A4 PAGE
══════════════════════════════════════════════════════════════ */
@page { size: A4 portrait; margin: 0; }

.page {
  width:  210mm;
  height: 297mm;
  max-height: 297mm;
  background: #fffef5;
  border: 1.5px solid #bbb;
  position: relative;
  overflow: hidden;
  display: block;
  padding: 7mm 8mm 6mm;
  transform-origin: top center;
  flex-shrink: 0;
  box-shadow: 0 6px 32px rgba(0,0,0,.22);
  margin-bottom: 0;
}

@media print {
  html, body { width: 210mm; height: auto; background: white !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
  .control-bar, .ctrl-bar-spacer, .page-nav { display: none !important; }
  .page-scaler { display: block !important; width: 210mm !important; padding: 0 !important; margin: 0 !important; gap: 0 !important; background: white !important; }
  .page-wrap { display: block !important; width: 210mm !important; height: auto !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
  .page { display: block !important; width: 210mm !important; height: 297mm !important; max-height: 297mm !important; min-height: 297mm !important; padding: 7mm 8mm 6mm !important; margin: 0 !important; border: none !important; box-shadow: none !important; transform: none !important; overflow: hidden !important; page-break-before: always; page-break-after: always; page-break-inside: avoid; break-before: page; break-after: page; break-inside: avoid; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page:first-of-type { page-break-before: avoid; break-before: avoid; }
  .page:last-of-type { page-break-after: auto; break-after: auto; }
}

.watermark { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
.watermark-inner { width: 100%; height: 100%; display: flex; flex-wrap: wrap; align-items: flex-start; opacity: .05; transform: rotate(-22deg) scale(1.45); transform-origin: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.watermark-text { font-size: 9px; font-weight: bold; color: #8b6914; white-space: nowrap; padding: 5px 6px; letter-spacing: 1px; }

.pg-content { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; overflow: hidden; }

/* ── HEADER block ── */
.hdr { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-bottom: 3px; }
.logo-circle { width: 64px; height: 64px; border-radius: 50%; border: 2px solid #8b1a1a; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f5f0e8; flex-shrink: 0; }
.logo-circle img { width: 100%; height: 100%; object-fit: cover; }
.logo-inner { text-align: center; color: #8b1a1a; line-height: 1.25; padding: 2px; }
.logo-inner .li-top  { font-size: 5px;  display: block; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
.logo-inner .li-icon { font-size: 18px; display: block; margin: 1px 0; }
.logo-inner .li-name { font-size: 7.5px; font-weight: 900; display: block; }
.logo-inner .li-bot  { font-size: 5px;  display: block; font-weight: bold; letter-spacing: .2px; }

.hdr-text { flex: 1; text-align: center; padding: 0 5px; }
.hdr-school-name { font-size: 19px; font-weight: 900; color: #8b1a1a; text-transform: uppercase; font-style: italic; letter-spacing: .5px; display: block; line-height: 1.1; }
.hdr-motto   { font-size: 8.5px; color: #555; display: block; font-style: italic; margin: 1px 0; }
.hdr-address { font-size: 8.5px; color: #333; display: block; margin-top: 1px; line-height: 1.4; }
.hdr-tag     { font-size: 10px; font-weight: bold; color: #444; letter-spacing: .6px; text-transform: uppercase; display: block; margin-top: 2px; }

.stu-photo { width: 60px; height: 72px; border-radius:30px; background: #f5f0e8; flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.stu-photo img { width: 100%; height: 100%; object-fit: cover; }
.stu-photo-init { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #8b1a1a, #c0392b); color: #fff; font-size: 20px; font-weight: 900; letter-spacing: -.02em; }

.divider      { border-top: 1.5px solid #222; margin: 3px 0; flex-shrink: 0; }
.divider-thin { border-top: 1px solid #aaa;   margin: 1px 0; flex-shrink: 0; }

/* ── INFO FIELDS ── */
.info-sec { margin-top: 3px; flex-shrink: 0; }
.info-row { display: flex; align-items: baseline; margin-bottom: 4px; gap: 3px; }
.info-row:last-child { margin-bottom: 0; }
.info-row .lbl { font-weight: bold; font-size: 12px; white-space: nowrap; flex-shrink: 0; }
.info-row .val { border-bottom: 1px solid #333; flex: 1; height: 15px; font-size: 11.5px; padding-left: 3px; overflow: hidden; white-space: nowrap; min-width: 18px; }
.info-row .grp { display: flex; align-items: baseline; gap: 3px; flex: 1; margin-left: 8px; }

/* ── SECTION TITLE ── */
.sec-title { text-align: center; font-weight: bold; font-size: 13px; text-transform: uppercase; text-decoration: underline; margin: 4px 0 3px; letter-spacing: .4px; flex-shrink: 0; }

/* ── GRADES TABLE ── */
.gt { width: 100%; border-collapse: collapse; font-size: 11px; flex-shrink: 0; }
.gt th { border: 1px solid #333; text-align: center; padding: 4px 2px; font-size: 10.5px; font-weight: bold; background: #f8f4ee; }
.gt th.subj-hd { color: #8b1a1a; text-align: left; padding-left: 4px; }
.gt td { border: 1px solid #333; padding: 1px 2px; text-align: center; height: 18px; font-size: 11px; }
.gt td.subj-td { text-align: left; padding-left: 4px; font-weight: bold; font-size: 11px; }

.grade-note { margin-top: 2px; font-size: 8.5px; display: flex; justify-content: space-between; flex-shrink: 0; }
.grade-note strong { color: #c0392b; }

/* ── SKILLS ROW ── */
.brow { display: flex; gap: 6px; margin-top: 5px; flex: 1; min-height: 0; overflow: hidden; }
.brow > div { display: flex; flex-direction: column; overflow: hidden; }
.brow > div:not(.scale-box) { flex: 1; }

.trait-tbl { border-collapse: collapse; font-size: 10.5px; width: 100%; }
.trait-tbl th { border: 1px solid #555; text-align: center; padding: 3px 2px; font-size: 10px; font-weight: bold; background: #f8f4ee; white-space: nowrap; }
.trait-tbl td { border: 1px solid #555; padding: 2px 4px; }
.trait-tbl td.rc { width: 38px; text-align: center; font-weight: 700; }
.trait-tbl tbody tr { height: auto; }

.scale-box { font-size: 10px; min-width: 148px; max-width: 148px; flex-shrink: 0; }
.scale-title { color: #8b1a1a; font-weight: bold; font-size: 12px; text-align: center; margin-bottom: 4px; }
.scale-box p { margin-bottom: 3px; line-height: 1.35; }

/* ── FOOTER SIGNATURES ── */
.pg-footer { flex-shrink: 0; padding-top: 5px; margin-top: auto;margin-bottom:75px}
.foot-line { display: flex; align-items: baseline; margin-bottom: 10px; }
.foot-line:last-child { margin-bottom: 0; }
.foot-lbl { font-weight: bold; font-size: 11px; white-space: nowrap; flex-shrink: 0; text-transform: uppercase; }
.foot-ul  { flex: 1; border-bottom: 1px solid #333; margin-left: 5px; height: 12px; }

/* ── TEMPLATE SPECIFIC STYLES ── */
/* EXECUTIVE PREMIUM (1) */
.template-executive { border: 4px double #1F3C88 !important; }
.template-executive .hdr-school-name { color: #1F3C88; }
.template-executive .gt th { background: #1F3C88; color: #fff; }
.template-executive .sec-title { background: #1F3C88; color: #fff; text-decoration: none; padding: 4px; border-radius: 4px; }
.template-executive .logo-circle { border-color: #1F3C88; }

/* DYNAMIC MATRIX (2) */
.template-matrix { border: 2px solid #000 !important; }
.template-matrix .gt th { background: #000; color: #fff; }
.template-matrix .sec-title { border-bottom: 3px solid #000; text-decoration: none; display: inline-block; }
.template-matrix .info-row .lbl { color: #000; font-size: 10px; }
.template-matrix .info-row .val { font-family: 'Courier New', Courier, monospace; font-weight: 700; background: #f0f0f0; }

/* MINIMALIST NODE (3) */
.template-minimalist { border: 1px solid #eee !important; background: #fff !important; }
.template-minimalist .hdr-school-name { color: #334155; font-style: normal; }
.template-minimalist .gt th { background: #f8fafc; color: #64748b; border: none; border-bottom: 2px solid #e2e8f0; }
.template-minimalist .gt td { border: none; border-bottom: 1px solid #f1f5f9; }
.template-minimalist .sec-title { text-transform: none; text-decoration: none; color: #1e293b; font-size: 16px; border-left: 4px solid #3b82f6; padding-left: 10px; text-align: left; }
.template-minimalist .info-row .val { border-bottom-style: dashed; border-bottom-color: #cbd5e1; }

.qr-code-box, .barcode-box { position: absolute; bottom: 15mm; right: 8mm; text-align: right; }
.qr-code-box img { width: 60px; height: 60px; border: 1px solid #eee; padding: 2px; background: #fff; }
.barcode-box img { height: 35px; width: auto; max-width: 120px; }
.meta-tag { font-size: 8px; color: #94a3b8; margin-top: 4px; font-weight: 800; text-transform: uppercase; }

/* ── CENTER WATERMARK (Template 1) ── */
.center-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-30deg);
    opacity: 0.07;
    z-index: 0;
    pointer-events: none;
    width: 350px;
    height: 350px;
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* SPECTRUM ELITE (4) - Rainbow Professional */
.template-spectrum { border: 6px solid transparent !important; border-image: linear-gradient(to bottom right, #FF0000, #FF7F00, #FFFF00, #00FF00, #0000FF, #4B0082, #8F00FF) 1 !important; }
.template-spectrum .hdr-school-name { background: linear-gradient(to right, #e74c3c, #3498db); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 900; }
.template-spectrum .gt th { background: linear-gradient(to right, #1a2b4a, #2d6cdf); color: #fff; border: none; }
.template-spectrum .sec-title { color: #2d6cdf; border-bottom: 2px solid #2d6cdf; text-decoration: none; }

/* INSTITUTIONAL LAUREL (5) - Classical Heritage */
.template-laurel { border: 15px solid #fdfaf3 !important; outline: 1px solid #d4af37; background: #fdfaf3 !important; font-family: 'Times New Roman', Times, serif !important; }
.template-laurel .hdr-school-name { color: #1a1a1a; font-family: serif; letter-spacing: 2px; }
.template-laurel .gt th { background: none; color: #1a1a1a; border: 1.5px solid #1a1a1a; border-bottom: 3px double #1a1a1a; }
.template-laurel .gt td { border: 1px solid #1a1a1a; }
.template-laurel .sec-title { font-family: serif; text-transform: uppercase; letter-spacing: 3px; border-bottom: 1px solid #1a1a1a; text-decoration: none; position: relative; display: inline-block; padding: 0 40px; }
.template-laurel .sec-title::before, .template-laurel .sec-title::after { content: '⚜'; position: absolute; top: 0; font-size: 14px; }
.template-laurel .sec-title::before { left: 0; }
.template-laurel .sec-title::after { right: 0; }
.template-laurel .foot-ul { border-bottom: 1px solid #1a1a1a; }
.template-laurel .logo-circle { border-color: #d4af37; background: #fff; }

.laurel-decor { position: absolute; width: 100px; height: 100%; top: 0; opacity: 0.05; pointer-events: none; z-index: 0; }
.laurel-left { left: 0; background: url('https://www.transparentpng.com/download/laurel-wreath/laurel-wreath-transparent-background-3.png') repeat-y center; background-size: contain; }
.laurel-right { right: 0; background: url('https://www.transparentpng.com/download/laurel-wreath/laurel-wreath-transparent-background-3.png') repeat-y center; background-size: contain; }
.wax-seal { position: absolute; bottom: 30mm; right: 20mm; width: 80px; height: 80px; background: #8b0000; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 30px; border: 4px solid #700000; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transform: rotate(-10deg); font-family: serif; font-weight: 900; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

/* ── MOBILE NAVIGATION DOTS ── */
.page-nav { position: fixed; bottom: 18px; right: 14px; z-index: 9998; display: flex; flex-direction: column; gap: 6px; background: rgba(26,43,74,.85); border-radius: 20px; padding: 8px 6px; box-shadow: 0 4px 18px rgba(0,0,0,.28); backdrop-filter: blur(4px); }
.page-nav-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,.35); border: none; cursor: pointer; padding: 0; transition: background .2s, transform .2s; display: block; }
.page-nav-dot.active { background: #fff; transform: scale(1.25); }
.page-nav-dot:hover  { background: rgba(255,255,255,.7); }
.page-nav-label { font-size: .6rem; font-weight: 700; color: rgba(255,255,255,.55); text-align: center; letter-spacing: .04em; text-transform: uppercase; margin-bottom: 2px; }
@media(min-width:900px){ .page-nav { display: none; } }
@media print { .page-nav { display: none !important; } }
</style>
</head>
<body>

<!-- Control bar (screen only) -->
<div class="control-bar">
  <h4>📄 <?= e($cls['name']) ?> — <?= e($cls['school_name'] ?? '') ?></h4>
  <span style="color:#94a3b8;font-size:.8rem;flex-shrink:0;"><?= count($students) ?> students</span>
  <button onclick="window.print()" class="ctrl-btn ctrl-btn-print">
    <i class="fa-solid fa-print"></i><span class="btn-lbl">Print / PDF</span>
  </button>
  <a href="assessment_entry.php?class_id=<?= $class_id ?>" class="ctrl-btn ctrl-btn-edit">
    <i class="fa-solid fa-pen"></i><span class="btn-lbl">Edit Scores</span>
  </a>
  <a href="report_management.php?class_id=<?= $class_id ?>" class="ctrl-btn ctrl-btn-back">
    <i class="fa-solid fa-arrow-left"></i><span class="btn-lbl">Back</span>
  </a>
</div>
<div class="ctrl-bar-spacer"></div>
<div class="page-scaler" id="pageScaler">

<?php foreach ($students as $stuIdx => $stu):
    $scores  = $allScores[$stu['id']] ?? [];
    $skills  = $allSkills[$stu['id']] ?? [];
    $summary = $studentSummaries[$stu['id']];
    $stuPos  = $positions[$stu['id']] ?? 0;
    $posText = $stuPos ? ordinal($stuPos) : '—';
    $finalGrade  = calcGrade($summary['avg'], $is_higher_ed);
    $wmTextEsc = addslashes($wmText);
?>
<?php 
    $templateClass = '';
    if($template_id == 1) $templateClass = 'template-executive';
    if($template_id == 2) $templateClass = 'template-matrix';
    if($template_id == 3) $templateClass = 'template-minimalist';
    if($template_id == 4) $templateClass = 'template-spectrum';
    if($template_id == 5) $templateClass = 'template-laurel';
?>
<div class="page-wrap" id="wrap<?= $stuIdx ?>">
<div class="page <?= $templateClass ?>" id="page<?= $stuIdx ?>">
  <!-- Watermark -->
  <?php if ($template_id == 1): // Executive gets Center Logo Watermark ?>
    <div class="center-watermark" style="background-image: url('<?= $logoSrc ?: '../assets/img/default_logo_watermark.png' ?>');"></div>
  <?php elseif ($template_id == 5): // Laurel gets decorative elements ?>
    <div class="laurel-decor laurel-left"></div>
    <div class="laurel-decor laurel-right"></div>
  <?php else: ?>
    <div class="watermark"><div class="watermark-inner" id="wm<?= $stuIdx ?>"></div></div>
  <?php endif; ?>

  <div class="pg-content">
    <!-- HEADER: Logo | School Info (center) | Student Photo -->
    <div class="hdr">
      <!-- Logo -->
      <div class="logo-circle">
        <?php if ($logoSrc): ?>
          <img src="<?= $logoSrc ?>" alt="Logo"/>
        <?php else: ?>
          <div class="logo-inner">
            <span class="li-top"><?= e(substr($cls['school_name']??'School',0,18)) ?></span>
            <span class="li-icon">🏫</span>
            <span class="li-name"><?= e(strtoupper(substr($cls['school_name']??'SCH',0,6))) ?></span>
            <span class="li-bot"><?= e($cls['motto']??'Excellence') ?></span>
          </div>
        <?php endif; ?>
      </div>

      <!-- School name + address centered -->
      <div class="hdr-text">
        <span class="hdr-school-name"><?= e($cls['school_name'] ?? '') ?></span>
        <?php if (!empty($cls['motto'])): ?>
          <span class="hdr-motto">"<?= e($cls['motto']) ?>"</span>
        <?php endif; ?>
        <?php if (!empty($cls['school_address'])): ?>
          <span class="hdr-address"><?= nl2br(e($cls['school_address'])) ?></span>
        <?php endif; ?>
        <div class="divider-thin" style="margin-top:4px;"></div>
        <span class="hdr-tag"><?= e(get_label('Report Card')) ?></span>
      </div>

      <!-- Student photo -->
      <div class="stu-photo">
        <?php if ($stu['image_path']): ?>
          <img src="../<?= e($stu['image_path']) ?>"
               alt="photo"
               onerror="this.src='../img/default_picture.png'"/>
        <?php else: ?>
          <img src="../img/default_picture.png" alt="photo" style="width:100%;height:100%;object-fit:cover;"/>
        <?php endif; ?>
      </div>
    </div>

    <div class="divider"></div>

    <!-- INFO FIELDS -->
    <div class="info-sec">
      <div class="info-row">
        <span class="lbl">Name:</span>
        <div class="val">&nbsp;<?= e($stu['full_name']) ?></div>
      </div>
      <div class="info-row">
        <span class="lbl">Gender:</span>
        <div class="val" style="max-width:100px;">&nbsp;<?= e($stu['gender']) ?></div>
        <div class="grp">
          <span class="lbl">Total&nbsp;Score:</span>
          <div class="val" style="max-width:70px;">&nbsp;<?= $summary['total'] ?></div>
        </div>
        <div class="grp">
          <span class="lbl">Final&nbsp;Grade:</span>
          <div class="val">&nbsp;<?= $finalGrade ?></div>
        </div>
      </div>
      <div class="info-row">
        <span class="lbl"><?= e(get_label('Term')) ?>:</span>
        <div class="val" style="max-width:100px;">&nbsp;<?= e($cls['term']) ?></div>
        <div class="grp">
          <span class="lbl">Class&nbsp;Average:</span>
          <div class="val" style="max-width:70px;">&nbsp;<?= $classAvg ?></div>
        </div>
        <div class="grp">
          <span class="lbl">Final&nbsp;Average:</span>
          <div class="val">&nbsp;<?= $summary['avg'] ?></div>
        </div>
      </div>
      <div class="info-row">
        <span class="lbl">Highest:</span>
        <div class="val" style="max-width:90px;">&nbsp;<?= $highest ?></div>
        <div class="grp">
          <span class="lbl">Lowest:</span>
          <div class="val" style="max-width:70px;">&nbsp;<?= $lowest ?></div>
        </div>
        <div class="grp">
          <span class="lbl">No.&nbsp;In&nbsp;Class:</span>
          <div class="val">&nbsp;<?= count($students) ?></div>
        </div>
      </div>
      <div class="info-row">
        <span class="lbl"><?= e(get_label('Class')) ?>:</span>
        <div class="val" style="max-width:120px;">&nbsp;<?= e($cls['name']) ?></div>
        <?php if ($cls['pos_visible']): ?>
        <div class="grp">
          <span class="lbl">Position:</span>
          <div class="val" style="max-width:80px;">&nbsp;<strong><?= $posText ?></strong></div>
        </div>
        <?php else: ?>
        <div class="grp">
          <span class="lbl">Session:</span>
          <div class="val">&nbsp;<?= e($cls['session'] ?? '') ?></div>
        </div>
        <?php endif; ?>
        <div class="grp">
          <span class="lbl">Next <?= e(get_label('Term')) ?>:</span>
          <div class="val">&nbsp;<?= e($cls['next_term_date'] ?? '') ?></div>
        </div>
      </div>
      <?php if ($is_higher_ed): ?>
      <?php
        // Calculate GPA/CGPA for this student
        $stu_credits = 0; $stu_points = 0;
        foreach ($subjects as $sub) {
            $sc = $allScores[$stu['id']][$sub['id']] ?? null;
            if ($sc && $sc['total'] !== null && ($sub['credit_units'] ?? 0) > 0) {
                $gp = calcGrade($sc['total'], true);
                $gp_val = match($gp) { 'A'=>5, 'B'=>4, 'C'=>3, 'D'=>2, 'E'=>1, default=>0 };
                $stu_points += $gp_val * $sub['credit_units'];
                $stu_credits += $sub['credit_units'];
            }
        }
        $stu_gpa = $stu_credits > 0 ? round($stu_points / $stu_credits, 2) : 0.00;
      ?>
      <div class="info-row">
        <span class="lbl">GPA:</span>
        <div class="val" style="max-width:80px;">&nbsp;<strong><?= number_format($stu_gpa, 2) ?></strong></div>
        <div class="grp">
          <span class="lbl">Total&nbsp;Units:</span>
          <div class="val" style="max-width:60px;">&nbsp;<?= $stu_credits ?></div>
        </div>
        <div class="grp">
          <span class="lbl">Grade&nbsp;Points:</span>
          <div class="val" style="max-width:60px;">&nbsp;<?= $stu_points ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Section title -->
    <div class="sec-title"><?= e($cls['section_name'] ?? 'Academic Performance') ?></div>

    <!-- GRADES TABLE -->
    <table class="gt">
      <thead>
        <tr>
          <th class="subj-hd" style="width:22%;"><?= e(get_label('Subject')) ?></th>
          <?php if ($is_higher_ed): ?>
            <th style="width:10%;">CA (40)</th>
          <?php else: ?>
            <th style="width:9%;">CA1 (20)</th>
            <th style="width:9%;">CA2 (20)</th>
          <?php endif; ?>
          <th style="width:10%;">EXAM (60)</th>
          <th style="width:8%;">TOTAL</th>
          <th style="width:6%;">POS</th>
          <th style="width:7%;">GRADE</th>
          <th>REMARK</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $sub):
            $sc = $scores[$sub['id']] ?? null;
            $ca1  = ($sc) ? $sc['ca1']  : null;
            $ca2  = ($sc) ? $sc['ca2']  : null;
            $exam = ($sc) ? $sc['exam'] : null;
            $tot  = ($sc && $sc['total'] !== null) ? $sc['total'] : null;
            $grade  = ($tot !== null) ? calcGrade((float)$tot, $is_higher_ed) : '';
            $remark = $grade ? calcRemark($grade) : '';
            $spos   = $subjectPositions[$stu['id']][$sub['id']] ?? null;
            $sposStr = $spos ? ordinal($spos) : '—';
        ?>
        <tr>
          <td class="subj-td"><?= e($sub['name']) ?></td>
          <?php if ($is_higher_ed): ?>
            <td><?= ($ca1 !== null && $ca1 !== '') ? $ca1 : '—' ?></td>
          <?php else: ?>
            <td><?= ($ca1 !== null && $ca1 !== '') ? $ca1 : '—' ?></td>
            <td><?= ($ca2 !== null && $ca2 !== '') ? $ca2 : '—' ?></td>
          <?php endif; ?>
          <td><?= ($exam !== null && $exam !== '') ? $exam : '—' ?></td>
          <td><?= ($tot !== null && $tot !== '')  ? $tot  : '—' ?></td>
          <td><?= ($tot !== null && $tot !== '')  ? $sposStr : '—' ?></td>
          <td><?= ($grade !== null && $grade !== '') ? $grade : '—' ?></td>
          <td><?= $remark ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="grade-note">
      <?php if ($is_higher_ed): ?>
        <span><strong>Scale:</strong> A=70-100 · B=60-69 · C=50-59 · D=45-49 · E=40-44 · F=0-39</span>
      <?php else: ?>
        <span><strong>Scale:</strong> A1=75-100 · B2=70-74 · B3=65-69 · C4=60-64 · C5=55-59 · C6=50-54 · D7=45-49 · E8=40-44 · F9=0-39</span>
      <?php endif; ?>
      <span><strong><?= e(get_label('Subjects')) ?>: <?= count($subjects) ?></strong></span>
    </div>

    <!-- SKILLS + SCALE -->
    <div class="brow">
      <!-- Affective -->
      <div style="flex:1.1;">
        <table class="trait-tbl">
          <thead><tr><th colspan="2">AFFECTIVE TRAITS</th></tr><tr><th>TRAIT</th><th>RATING</th></tr></thead>
          <tbody>
            <?php foreach ($affectiveTraits as $trait):
              $r = $skills['affective'][$trait] ?? null; ?>
            <tr><td><?= e($trait) ?></td><td class="rc"><?= $r !== null ? $r : 'N/S' ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <!-- Psychomotor -->
      <div style="flex:0.9;">
        <table class="trait-tbl">
          <thead><tr><th colspan="2">PSYCHOMOTOR</th></tr><tr><th>SKILL</th><th>RATING</th></tr></thead>
          <tbody>
            <?php foreach ($psychomotorTraits as $trait):
              $r = $skills['psychomotor'][$trait] ?? null; ?>
            <tr><td><?= e($trait) ?></td><td class="rc"><?= $r !== null ? $r : 'N/S' ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <!-- Scale -->
      <div class="scale-box">
        <div class="scale-title">RATING SCALE</div>
        <p>5 — Excellent Level</p>
        <p>4 — Good Level</p>
        <p>3 — Fair/Acceptable</p>
        <p>2 — Poor Level</p>
        <p>1 — No Observable Trait</p>
        <p style="margin-top:6px;font-style:italic;color:#555;">N/S = Not Scored</p>
      </div>
    </div>

    <!-- FOOTER SIGNATURES -->
    <div class="pg-footer">
      <div class="foot-line"><span class="foot-lbl"><?= e(get_label('Form Teacher')) ?>'s Name</span><div class="foot-ul"></div></div>
      <div class="foot-line"><span class="foot-lbl"><?= e(get_label('Form Teacher')) ?>'s Comment</span><div class="foot-ul"></div></div>
      <div class="foot-line"><span class="foot-lbl"><?= e(get_label('Head Teacher')) ?>'s Comment</span><div class="foot-ul"></div></div>
      <div class="foot-line" style="margin-bottom:0;"><span class="foot-lbl"><?= e(get_label('Head Teacher')) ?>'s Signature &amp; Stamp</span><div class="foot-ul"></div></div>
    </div>
    <!-- Code Visuals (QR/Barcode) -->
    <?php if ($template_id == 1): // QR Code ?>
    <div class="qr-code-box">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('https://eduremarks.com/verify/'.$stu['admission_no']); ?>" alt="QR Verification">
        <div class="meta-tag">Identity Authenticated</div>
    </div>
    <?php elseif ($template_id == 2): // Barcode ?>
    <div class="barcode-box">
        <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text=<?php echo $stu['admission_no']; ?>&scale=2&rotate=N&includetext=1" alt="Barcode">
        <div class="meta-tag">Archival Node Indexed</div>
    </div>
    <?php elseif ($template_id == 5): // Wax Seal ?>
    <div class="wax-seal">
        <span>ER</span>
    </div>
    <?php endif; ?>

  </div><!-- /pg-content -->
</div><!-- /page -->
</div><!-- /page-wrap -->
<?php endforeach; ?>

</div><!-- /page-scaler -->

<?php if (empty($students)): ?>
<div style="text-align:center;padding:80px 20px;color:#64748b;font-family:system-ui;">
  <div style="font-size:3.5rem;margin-bottom:16px;">📭</div>
  <h3 style="margin-bottom:8px;">No students in this class</h3>
  <a href="manage-students.php?class_id=<?= $class_id ?>" style="display:inline-block;background:#1e6fcf;color:#fff;padding:11px 24px;border-radius:10px;text-decoration:none;font-weight:700;margin-top:12px;">Add Students</a>
</div>
<?php endif; ?>

<?php if (count($students) > 1): ?>
<!-- Mobile page nav dots -->
<div class="page-nav" id="pageNav">
  <div class="page-nav-label"><?= count($students) ?>p</div>
  <?php foreach ($students as $i => $s): ?>
  <button class="page-nav-dot<?= $i===0?' active':'' ?>"
          onclick="scrollToPage(<?= $i ?>)"
          title="<?= e($s['full_name']) ?>"></button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// ── Build watermarks ──────────────────────────────────────────────────────
document.querySelectorAll('[id^="wm"]').forEach(wm => {
  const txt = '<?= addslashes($wmText) ?>';
  for (let i = 0; i < 160; i++) {
    const s = document.createElement('span');
    s.className = 'watermark-text';
    s.textContent = txt;
    wm.appendChild(s);
  }
});

// ── Scale each .page to fit viewport — no blank gaps ─────────────────────
(function scalePages() {
  const pages = Array.from(document.querySelectorAll('.page'));
  const wraps = Array.from(document.querySelectorAll('.page-wrap'));
  if (!pages.length) return;

  function apply() {
    // Temporarily remove any existing transform to get natural dimensions
    pages.forEach(pg => { pg.style.transform = ''; });

    const pageNaturalW = pages[0].offsetWidth;   // natural px width  (≈794px at 96dpi)
    const pageNaturalH = pages[0].offsetHeight;  // natural px height (≈1123px at 96dpi)

    const vw  = Math.min(window.innerWidth, document.documentElement.clientWidth);
    const pad = vw < 480 ? 0 : vw < 768 ? 8 : 16;
    const scale = Math.min(1, (vw - pad * 2) / pageNaturalW);

    pages.forEach((pg, i) => {
      if (scale < 1) {
        pg.style.transform       = `scale(${scale})`;
        pg.style.transformOrigin = 'top center';
      }
      pg.style.marginBottom = '0';

      if (wraps[i]) {
        // Wrap height = exact scaled page height + gap below
        const gap = scale < 1 ? 10 : 20;
        wraps[i].style.height       = (pageNaturalH * scale + gap) + 'px';
        wraps[i].style.marginBottom = '0';
      }
    });
  }

  // Run immediately, on resize, and after everything loads
  apply();
  window.addEventListener('resize', apply, { passive: true });
  window.addEventListener('load',   apply);
})();

// ── Scroll to page ────────────────────────────────────────────────────────
function scrollToPage(idx) {
  const wrap = document.getElementById('wrap' + idx);
  if (!wrap) return;
  const top = wrap.getBoundingClientRect().top + window.scrollY - 60;
  window.scrollTo({ top, behavior: 'smooth' });
}

// ── Active dot tracking ───────────────────────────────────────────────────
(function() {
  const wraps = document.querySelectorAll('.page-wrap');
  const dots  = document.querySelectorAll('.page-nav-dot');
  if (!dots.length) return;
  function onScroll() {
    let active = 0;
    wraps.forEach((w, i) => {
      if (w.getBoundingClientRect().top <= 100) active = i;
    });
    dots.forEach((d, i) => d.classList.toggle('active', i === active));
  }
  window.addEventListener('scroll', onScroll, { passive: true });
})();
</script>
</body>
</html>
