<?php
// student/transcript.php
require_once 'auth.php';

$type = strtolower($student['school_type'] ?? '');
$is_higher_ed = (
    strpos($type, 'tertiary') !== false || 
    strpos($type, 'vocational') !== false || 
    strpos($type, 'polytechnic') !== false || 
    strpos($type, 'university') !== false || 
    strpos($type, 'college') !== false
);

// Fetch all sessions where student has results
$sessions_stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.name
    FROM academic_sessions s
    JOIN student_results r ON r.session_id = s.id
    WHERE r.student_id = ?
    ORDER BY s.created_at ASC
");
$sessions_stmt->execute([$student_id]);
$all_sessions = $sessions_stmt->fetchAll();

// Session range selection
$from_session_id = intval($_GET['from_session'] ?? 0);
$to_session_id = intval($_GET['to_session'] ?? 0);

// Default to all sessions
if (empty($all_sessions)) {
    $from_session_id = 0;
    $to_session_id = 0;
} else {
    if (!$from_session_id) $from_session_id = $all_sessions[0]['id'];
    if (!$to_session_id) $to_session_id = end($all_sessions)['id'];
}

// Get session names
$from_session_name = '';
$to_session_name = '';
if ($from_session_id) {
    $stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
    $stmt->execute([$from_session_id]);
    $from_session_name = $stmt->fetchColumn() ?: '';
}
if ($to_session_id) {
    $stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
    $stmt->execute([$to_session_id]);
    $to_session_name = $stmt->fetchColumn() ?: '';
}

// Fetch all results for the selected session range
$results = [];
$terms_summary = [];
if ($from_session_id && $to_session_id) {
    $res_stmt = $pdo->prepare("
        SELECT r.*, s.name as subject_name, s.code as subject_code, s.credit_units,
               sess.name as session_name, t.name as term_name,
               c.name as class_name
        FROM student_results r
        JOIN subjects s ON s.id = r.subject_id
        JOIN academic_sessions sess ON sess.id = r.session_id
        JOIN academic_terms t ON t.id = r.term_id
        LEFT JOIN classes c ON c.id = r.class_id
        WHERE r.student_id = ? AND r.session_id >= ? AND r.session_id <= ?
        ORDER BY sess.created_at ASC, t.created_at ASC, s.name ASC
    ");
    $res_stmt->execute([$student_id, $from_session_id, $to_session_id]);
    $results = $res_stmt->fetchAll();

    // Group by session > term
    foreach ($results as $r) {
        $key = $r['session_id'] . '_' . $r['term_id'];
        if (!isset($terms_summary[$key])) {
            $terms_summary[$key] = [
                'session_id' => $r['session_id'],
                'session_name' => $r['session_name'],
                'term_id' => $r['term_id'],
                'term_name' => $r['term_name'],
                'class_name' => $r['class_name'],
                'subjects' => [],
                'total_score' => 0,
                'count' => 0,
                'total_credits' => 0,
                'total_points' => 0,
            ];
        }
        $terms_summary[$key]['subjects'][] = $r;
        $terms_summary[$key]['total_score'] += $r['total'];
        $terms_summary[$key]['count']++;
        if ($is_higher_ed && $r['credit_units'] > 0) {
            $grade_point = 0;
            $score = $r['total'];
            if ($score >= 70) $grade_point = 5;
            elseif ($score >= 60) $grade_point = 4;
            elseif ($score >= 50) $grade_point = 3;
            elseif ($score >= 45) $grade_point = 2;
            elseif ($score >= 40) $grade_point = 1;
            $terms_summary[$key]['total_points'] += ($grade_point * $r['credit_units']);
            $terms_summary[$key]['total_credits'] += $r['credit_units'];
        }
    }
}

// Calculate cumulative stats
$total_score_sum = 0;
$total_count = 0;
$cumulative_points = 0;
$cumulative_credits = 0;
$all_subjects = [];

foreach ($terms_summary as $ts) {
    $total_score_sum += $ts['total_score'];
    $total_count += $ts['count'];
    $cumulative_points += $ts['total_points'];
    $cumulative_credits += $ts['total_credits'];
    foreach ($ts['subjects'] as $sub) {
        $all_subjects[$sub['subject_id']] = $sub['subject_name'];
    }
}

$overall_avg = $total_count > 0 ? round($total_score_sum / $total_count, 1) : 0;
$cgpa = $cumulative_credits > 0 ? round($cumulative_points / $cumulative_credits, 2) : 0;

function transCalcGrade($total, $is_higher_ed = false) {
    if ($is_higher_ed) {
        if ($total >= 70) return 'A'; if ($total >= 60) return 'B'; if ($total >= 50) return 'C';
        if ($total >= 45) return 'D'; if ($total >= 40) return 'E'; return $total > 0 ? 'F' : '-';
    } else {
        if ($total >= 75) return 'A1'; if ($total >= 70) return 'B2'; if ($total >= 65) return 'B3';
        if ($total >= 60) return 'C4'; if ($total >= 55) return 'C5'; if ($total >= 50) return 'C6';
        if ($total >= 45) return 'D7'; if ($total >= 40) return 'E8'; return $total > 0 ? 'F9' : '-';
    }
}
function transCalcRemark($grade) {
    $g = substr($grade, 0, 1);
    if ($g === 'A') return 'Excellent'; if ($g === 'B') return 'Very Good';
    if ($g === 'C') return 'Good'; if ($g === 'D') return 'Fair';
    if ($g === 'E' || $grade === 'E8') return 'Pass'; if ($g === 'F') return 'Fail';
    return '-';
}
function te($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

$logoSrc = $student['logo_path'] ? '../' . ltrim($student['logo_path'], '/') : '';
$wmText = strtoupper($student['school_name']);
$has_transcript = !empty($terms_summary);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= $is_higher_ed ? 'Transcript' : get_label('Broadsheet') ?> — <?= te($student['full_name']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #f1f5f9; font-family: 'Segoe UI', Arial, Helvetica, sans-serif; overflow-x: hidden; }

.control-bar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
  background: #1a2b4a; color: #fff;
  padding: 10px 16px; display: flex; align-items: center; gap: 10px;
  box-shadow: 0 2px 12px rgba(0,0,0,.1); min-height: 52px; flex-wrap: wrap;
}
.control-bar h4 { margin: 0; font-size: .88rem; font-weight: 800; flex: 1; min-width: 200px; }
.ctrl-btn {
  border: none; border-radius: 8px; padding: 7px 14px;
  font-weight: 700; cursor: pointer; font-size: .82rem;
  text-decoration: none; display: inline-flex; align-items: center;
  gap: 5px; flex-shrink: 0; transition: background .2s;
}
.ctrl-btn-print { background: #1e6fcf; color: #fff; }
.ctrl-btn-back  { background: transparent; color: #94a3b8; border: none; }
.ctrl-select {
  background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
  color: #fff; border-radius: 8px; padding: 6px 10px; font-size: 0.8rem;
  font-weight: 600; cursor: pointer;
}
.ctrl-select option { color: #1e293b; background: #fff; }
.ctrl-label { color: rgba(255,255,255,0.7); font-size: 0.75rem; font-weight: 600; }

.page-scaler {
  width: 100%; display: flex; flex-direction: column;
  align-items: center; padding: 70px 8px 48px; gap: 0; overflow-x: hidden;
}
.page-wrap { width: 100%; display: flex; justify-content: center; overflow: hidden; }

@page { size: A4 portrait; margin: 0; }
.page {
  width: 210mm; min-height: 297mm;
  background: #fffef5; border: 1.5px solid #bbb;
  position: relative; overflow: hidden; display: block;
  padding: 7mm 8mm 6mm; transform-origin: top center; flex-shrink: 0;
  box-shadow: 0 6px 32px rgba(0,0,0,.12);
}

@media print {
  html, body { background: white !important; margin: 0 !important; padding: 0 !important; }
  .control-bar, .no-print { display: none !important; }
  .page-scaler { padding: 0 !important; }
  .page { border: none !important; box-shadow: none !important; transform: none !important; margin: 0 !important; min-height: auto !important; }
  .page-break { page-break-before: always; }
}

.watermark { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
.watermark-inner { width: 100%; height: 100%; display: flex; flex-wrap: wrap; align-items: flex-start; opacity: .04; transform: rotate(-22deg) scale(1.45); }
.watermark-text { font-size: 9px; font-weight: bold; color: #8b6914; white-space: nowrap; padding: 5px 6px; letter-spacing: 1px; }

.pg-content { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; }

.hdr { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-bottom: 3px; }
.logo-circle { width: 60px; height: 60px; border-radius: 50%; border: 2px solid #8b1a1a; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f5f0e8; flex-shrink: 0; }
.logo-circle img { width: 100%; height: 100%; object-fit: cover; }
.hdr-text { flex: 1; text-align: center; }
.hdr-school-name { font-size: 18px; font-weight: 900; color: #8b1a1a; text-transform: uppercase; font-style: italic; display: block; line-height: 1.1; }
.hdr-motto { font-size: 8.5px; color: #555; display: block; font-style: italic; }
.hdr-address { font-size: 8.5px; color: #333; display: block; line-height: 1.4; }
.hdr-tag { font-size: 11px; font-weight: bold; color: #444; text-transform: uppercase; display: block; margin-top: 2px; letter-spacing: 1px; }

.stu-photo { width: 55px; height: 66px; border-radius: 30px; background: #f5f0e8; flex-shrink: 0; overflow: hidden; }
.stu-photo img { width: 100%; height: 100%; object-fit: cover; }

.divider { border-top: 1.5px solid #222; margin: 3px 0; }
.divider-thin { border-top: 1px solid #aaa; margin: 1px 0; }

.info-sec { margin-top: 3px; flex-shrink: 0; }
.info-row { display: flex; align-items: baseline; margin-bottom: 3px; gap: 3px; }
.info-row .lbl { font-weight: bold; font-size: 11px; white-space: nowrap; }
.info-row .val { border-bottom: 1px solid #333; flex: 1; height: 14px; font-size: 10.5px; padding-left: 3px; overflow: hidden; white-space: nowrap; }
.info-row .grp { display: flex; align-items: baseline; gap: 3px; flex: 1; margin-left: 8px; }

.sec-title { text-align: center; font-weight: bold; font-size: 12px; text-transform: uppercase; text-decoration: underline; margin: 6px 0 3px; }

.summary-box {
  display: flex; gap: 8px; margin: 6px 0; flex-wrap: wrap;
}
.summary-item {
  flex: 1; min-width: 80px; background: #f8f4ee; border: 1px solid #d4c5a0;
  border-radius: 4px; padding: 5px 8px; text-align: center;
}
.summary-item .s-label { font-size: 8px; font-weight: 700; color: #8b6914; text-transform: uppercase; display: block; }
.summary-item .s-value { font-size: 14px; font-weight: 900; color: #1a2b4a; display: block; }

.gt { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 4px; }
.gt th { border: 1px solid #333; text-align: center; padding: 3px 2px; background: #f8f4ee; font-size: 9px; }
.gt th.subj-hd { color: #8b1a1a; text-align: left; padding-left: 4px; }
.gt td { border: 1px solid #333; padding: 1px 2px; text-align: center; height: 16px; font-size: 9.5px; }
.gt td.subj-td { text-align: left; padding-left: 4px; font-weight: bold; }
.gt td.total-td { font-weight: 900; color: #1a2b4a; }
.gt td.grade-td { font-weight: 800; }

.term-header {
  background: #1a2b4a; color: #fff; padding: 4px 10px; font-size: 10px;
  font-weight: 800; margin-top: 8px; border-radius: 3px; display: flex;
  justify-content: space-between; align-items: center;
}
.term-avg { color: #fbbf24; font-weight: 900; }

.grade-note { margin-top: 3px; font-size: 8px; display: flex; justify-content: space-between; }
.grade-note strong { color: #c0392b; }

.cumulative-box {
  margin-top: 8px; padding: 8px 12px; border: 2px solid #8b1a1a;
  border-radius: 6px; background: #fdf8f0;
}
.cumulative-title { font-size: 11px; font-weight: 900; color: #8b1a1a; text-transform: uppercase; text-align: center; margin-bottom: 5px; text-decoration: underline; }
.cumulative-stats { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.cum-stat { text-align: center; }
.cum-stat .c-val { font-size: 16px; font-weight: 900; color: #1a2b4a; display: block; }
.cum-stat .c-lbl { font-size: 8px; font-weight: 700; color: #8b6914; text-transform: uppercase; display: block; }

.pg-footer { flex-shrink: 0; padding-top: 5px; margin-top: auto; margin-bottom: 30px; }
.foot-line { display: flex; align-items: baseline; margin-bottom: 8px; }
.foot-lbl { font-weight: bold; font-size: 10px; white-space: nowrap; text-transform: uppercase; }
.foot-ul { flex: 1; border-bottom: 1px solid #333; margin-left: 5px; height: 12px; }

.empty-state {
  text-align: center; padding: 60px 30px; max-width: 500px; margin: 0 auto;
}
.empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 15px; }
.empty-state h5 { font-weight: 800; color: #475569; margin-bottom: 8px; }
.empty-state p { color: #94a3b8; font-size: 0.9rem; }

@media(max-width: 600px) {
  .control-bar h4 { font-size: .75rem; }
  .ctrl-btn span { display: none; }
  .summary-box { flex-direction: column; }
}
</style>
</head>
<body>

<div class="control-bar">
  <h4>📄 <?= $is_higher_ed ? 'Official Transcript' : get_label('Broadsheet') ?> — <?= te($student['full_name']) ?></h4>
  
  <span class="ctrl-label">From:</span>
  <select class="ctrl-select" id="fromSession" onchange="updateRange()">
      <?php foreach ($all_sessions as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $from_session_id == $s['id'] ? 'selected' : '' ?>><?= te($s['name']) ?></option>
      <?php endforeach; ?>
  </select>
  
  <span class="ctrl-label">To:</span>
  <select class="ctrl-select" id="toSession" onchange="updateRange()">
      <?php foreach ($all_sessions as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $to_session_id == $s['id'] ? 'selected' : '' ?>><?= te($s['name']) ?></option>
      <?php endforeach; ?>
  </select>
  
  <button onclick="window.print()" class="ctrl-btn ctrl-btn-print">
    <i class="fa-solid fa-print"></i><span>Print / PDF</span>
  </button>
  <a href="reports.php" class="ctrl-btn ctrl-btn-back">
    <i class="fa-solid fa-arrow-left"></i><span>Back</span>
  </a>
</div>

<div class="page-scaler">
  <div class="page-wrap">
    <?php if (!$has_transcript): ?>
    <div class="page" style="display:flex; align-items:center; justify-content:center;">
      <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h5>No Results Found</h5>
        <p>No academic results have been recorded for your account yet. Please contact your school administrator for assistance.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="page" id="transcriptPage">
      <div class="watermark"><div class="watermark-inner" id="wmInner"></div></div>
      
      <div class="pg-content">
        <!-- Header -->
        <div class="hdr">
          <div class="logo-circle">
            <?php if ($logoSrc): ?>
              <img src="<?= $logoSrc ?>" alt="Logo"/>
            <?php else: ?>
              <div style="color:#8b1a1a; font-size:9px; font-weight:900;">LOGO</div>
            <?php endif; ?>
          </div>
          <div class="hdr-text">
            <span class="hdr-school-name"><?= te($student['school_name']) ?></span>
            <?php if (!empty($student['motto'])): ?><span class="hdr-motto">"<?= te($student['motto']) ?>"</span><?php endif; ?>
            <span class="hdr-address"><?= nl2br(te($student['school_address'])) ?></span>
            <div class="divider-thin"></div>
            <span class="hdr-tag"><?= $is_higher_ed ? 'OFFICIAL ACADEMIC TRANSCRIPT' : strtoupper(get_label('Broadsheet')) ?></span>
          </div>
          <div class="stu-photo">
            <img src="<?= $student['image_path'] ? '../'.te($student['image_path']) : '../img/default_picture.png' ?>" alt="" onerror="this.src='../img/default_picture.png'"/>
          </div>
        </div>

        <div class="divider"></div>

        <!-- Student Info -->
        <div class="info-sec">
          <div class="info-row">
            <span class="lbl">Name:</span>
            <div class="val">&nbsp;<?= te($student['full_name']) ?></div>
          </div>
          <div class="info-row">
            <span class="lbl"><?= get_label('Admission No') ?>:</span>
            <div class="val" style="max-width:130px;">&nbsp;<?= te($student['admission_no']) ?></div>
            <div class="grp"><span class="lbl">Gender:</span><div class="val" style="max-width:80px;">&nbsp;<?= te($student['gender']) ?></div></div>
          </div>
          <div class="info-row">
            <span class="lbl">Period:</span>
            <div class="val">&nbsp;<?= te($from_session_name) ?> — <?= te($to_session_name) ?></div>
          </div>
        </div>

        <div class="divider"></div>

        <!-- Summary Stats -->
        <div class="summary-box">
          <div class="summary-item">
            <span class="s-label">Sessions</span>
            <span class="s-value"><?= count(array_unique(array_column($results, 'session_id'))) ?></span>
          </div>
          <div class="summary-item">
            <span class="s-label"><?= get_label('Terms') ?></span>
            <span class="s-value"><?= count($terms_summary) ?></span>
          </div>
          <div class="summary-item">
            <span class="s-label"><?= get_label('Subjects') ?></span>
            <span class="s-value"><?= count($all_subjects) ?></span>
          </div>
          <div class="summary-item">
            <span class="s-label">Total Entries</span>
            <span class="s-value"><?= $total_count ?></span>
          </div>
          <?php if ($is_higher_ed): ?>
          <div class="summary-item">
            <span class="s-label">CGPA</span>
            <span class="s-value" style="color:#8b1a1a;"><?= number_format($cgpa, 2) ?></span>
          </div>
          <?php else: ?>
          <div class="summary-item">
            <span class="s-label">Overall Average</span>
            <span class="s-value" style="color:#8b1a1a;"><?= number_format($overall_avg, 1) ?>%</span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Results by Term -->
        <?php 
        $current_session = 0;
        foreach ($terms_summary as $ts): 
            $term_avg = $ts['count'] > 0 ? round($ts['total_score'] / $ts['count'], 1) : 0;
            $term_gpa = $ts['total_credits'] > 0 ? round($ts['total_points'] / $ts['total_credits'], 2) : 0;
            $is_new_session = ($ts['session_id'] != $current_session);
            $current_session = $ts['session_id'];
        ?>
        
        <?php if ($is_new_session): ?>
        <div class="term-header" style="background: #2d5faa; margin-top: 10px;">
            <span><i class="fas fa-calendar-alt me-1"></i> <?= te($ts['session_name']) ?></span>
        </div>
        <?php endif; ?>

        <div class="term-header" style="background: #8b6914;">
            <span><?= te($ts['term_name']) ?> — <?= te($ts['class_name']) ?></span>
            <span class="term-avg">
                <?php if ($is_higher_ed): ?>
                    GPA: <?= number_format($term_gpa, 2) ?>
                <?php else: ?>
                    Avg: <?= number_format($term_avg, 1) ?>%
                <?php endif; ?>
            </span>
        </div>

        <table class="gt">
          <thead>
            <tr>
              <th class="subj-hd" style="width:25%;"><?= strtoupper(get_label('Subject')) ?></th>
              <?php if ($is_higher_ed): ?>
                <th style="width:10%;">CA (40)</th>
              <?php else: ?>
                <th style="width:8%;">CA1 (20)</th>
                <th style="width:8%;">CA2 (20)</th>
              <?php endif; ?>
              <th style="width:10%;">EXAM (60)</th>
              <th style="width:10%;">TOTAL</th>
              <th style="width:10%;">GRADE</th>
              <?php if ($is_higher_ed): ?>
                <th style="width:8%;">CR</th>
              <?php endif; ?>
              <th>REMARK</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ts['subjects'] as $sub):
              $grade = transCalcGrade($sub['total'], $is_higher_ed);
            ?>
            <tr>
              <td class="subj-td"><?= te($sub['subject_name']) ?></td>
              <?php if ($is_higher_ed): ?>
                <td><?= $sub['ca1'] ?></td>
              <?php else: ?>
                <td><?= $sub['ca1'] ?></td>
                <td><?= $sub['ca2'] ?></td>
              <?php endif; ?>
              <td><?= $sub['exam'] ?></td>
              <td class="total-td"><?= $sub['total'] ?></td>
              <td class="grade-td"><?= $grade ?></td>
              <?php if ($is_higher_ed): ?>
                <td><?= $sub['credit_units'] ?: '-' ?></td>
              <?php endif; ?>
              <td style="font-size: 8.5px;"><?= transCalcRemark($grade) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($is_higher_ed): ?>
        <div class="grade-note">
          <span><strong>Scale:</strong> A=70-100 · B=60-69 · C=50-59 · D=45-49 · E=40-44 · F=0-39</span>
          <span><strong>CR = Credit Units</strong></span>
        </div>
        <?php else: ?>
        <div class="grade-note">
          <span><strong>Scale:</strong> A1=75-100 · B2=70-74 · B3=65-69 · C4=60-64 · C5=55-59 · C6=50-54 · D7=45-49 · E8=40-44 · F9=0-39</span>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>

        <!-- Cumulative Summary -->
        <?php if ($has_transcript): ?>
        <div class="cumulative-box">
          <div class="cumulative-title">
            <?= $is_higher_ed ? 'Cumulative Summary' : 'Overall Summary' ?>
            (<?= te($from_session_name) ?> — <?= te($to_session_name) ?>)
          </div>
          <div class="cumulative-stats">
            <?php if ($is_higher_ed): ?>
              <div class="cum-stat">
                <span class="c-val"><?= number_format($cgpa, 2) ?></span>
                <span class="c-lbl">Cumulative GPA</span>
              </div>
              <div class="cum-stat">
                <span class="c-val"><?= $cumulative_credits ?></span>
                <span class="c-lbl">Total Credits</span>
              </div>
            <?php else: ?>
              <div class="cum-stat">
                <span class="c-val"><?= number_format($overall_avg, 1) ?>%</span>
                <span class="c-lbl">Overall Average</span>
              </div>
            <?php endif; ?>
            <div class="cum-stat">
              <span class="c-val"><?= $total_count ?></span>
              <span class="c-lbl">Total Entries</span>
            </div>
            <div class="cum-stat">
              <span class="c-val"><?= count(array_unique(array_column($results, 'session_id'))) ?></span>
              <span class="c-lbl">Sessions</span>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="pg-footer">
          <div class="foot-line"><span class="foot-lbl"><?= get_label('Head Teacher') ?>'s Signature & Stamp</span><div class="foot-ul"></div></div>
          <div class="foot-line" style="margin-bottom:0;"><span class="foot-lbl">Date Issued</span><div class="foot-ul"></div></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Watermark
(function() {
  const wm = document.getElementById('wmInner');
  if (!wm) return;
  const txt = '<?= addslashes($wmText) ?>';
  for (let i = 0; i < 150; i++) {
    const s = document.createElement('span');
    s.className = 'watermark-text';
    s.textContent = txt + ' ';
    wm.appendChild(s);
  }
})();

// Range selector
function updateRange() {
  const from = document.getElementById('fromSession').value;
  const to = document.getElementById('toSession').value;
  window.location.href = 'transcript.php?from_session=' + from + '&to_session=' + to;
}

// Scaler
(function() {
  const page = document.getElementById('transcriptPage');
  if (!page) return;
  const wrap = page.parentElement;
  function scale() {
    const vw = window.innerWidth;
    const pageW = 794;
    const scale = Math.min(1, (vw - 20) / pageW);
    if (scale < 1) {
      page.style.transform = 'scale(' + scale + ')';
      wrap.style.height = (page.offsetHeight * scale) + 'px';
    } else {
      page.style.transform = 'none';
      wrap.style.height = 'auto';
    }
  }
  scale();
  window.addEventListener('resize', scale);
})();
</script>

</body>
</html>
