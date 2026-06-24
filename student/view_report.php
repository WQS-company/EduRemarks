<?php
// student/view_report.php
require_once 'auth.php';

$session_id = intval($_GET['session_id'] ?? 0);
$term_id = intval($_GET['term_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);
$student_id = $_SESSION['student_id'];

if (!$session_id || !$term_id || !$class_id) {
    die("Institutional link verification failed. Required academic parameters missing.");
}

// Fetch term and session bounds
$term_stmt = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
$term_stmt->execute([$term_id]); $term_name = $term_stmt->fetchColumn();
$session_stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
$session_stmt->execute([$session_id]); $session_name = $session_stmt->fetchColumn();

// Load class info
$clsQ = $pdo->prepare("SELECT name, section FROM classes WHERE id = ? AND school_id = ?");
$clsQ->execute([$class_id, $school_id]);
$classData = $clsQ->fetch();
$class_name = $classData['name'] ?? null;
$section_name = $classData['section'] ?? 'Academic Performance';

if (!$class_name) { die("Class alignment failure."); }

$cls = [
    'name' => $class_name,
    'school_name' => $student['school_name'],
    'motto' => $student['motto'] ?? '',
    'school_address' => $student['school_address'] ?? '',
    'logo' => $student['logo_path'] ?? '',
    'term' => $term_name,
    'session' => $session_name,
    'next_term_date' => '', 
    'section_name' => $section_name,
    'pos_visible' => true
];

// All students who had results in this class node (for historical accuracy and position calc)
$stQ = $pdo->prepare("
    SELECT DISTINCT s.* 
    FROM students s 
    JOIN student_results sr ON sr.student_id = s.id 
    WHERE sr.class_id = ? AND sr.session_id = ? AND sr.term_id = ? AND sr.school_id = ?
    ORDER BY s.full_name
");
$stQ->execute([$class_id, $session_id, $term_id, $school_id]);
$allStudents = $stQ->fetchAll();

// If no results yet, fall back to current class mapping to at least show the roster
if (empty($allStudents)) {
    $stQ = $pdo->prepare("SELECT s.* FROM students s JOIN student_classes sc ON sc.student_id = s.id WHERE sc.class_id = ? AND sc.school_id = ? ORDER BY s.full_name");
    $stQ->execute([$class_id, $school_id]);
    $allStudents = $stQ->fetchAll();
}

$student_data = $student; // Fallback to current session student data
foreach($allStudents as $st) {
    if($st['id'] == $student_id) { $student_data = $st; break; }
}

// Safety: If student not in results but they ARE the one viewing, ensure they are in the peer group for calcs
if (!$allStudents || !in_array($student_id, array_column($allStudents, 'id'))) {
    $allStudents[] = $student;
}

// Subjects
$subQ = $pdo->prepare("SELECT s.id, s.name FROM subjects s JOIN class_subjects cs ON s.id = cs.subject_id WHERE cs.class_id = ?");
$subQ->execute([$class_id]);
$subjects = $subQ->fetchAll();

// All scores
$allScores = [];
$stuIds = array_column($allStudents, 'id');
$inIds  = implode(',', array_fill(0, count($stuIds), '?'));
$params = array_merge([$class_id, $session_id, $term_id], $stuIds);
$scQ = $pdo->prepare("SELECT * FROM student_results WHERE class_id = ? AND session_id = ? AND term_id = ? AND student_id IN ($inIds)");
$scQ->execute($params);
foreach ($scQ->fetchAll() as $sc) {
    $allScores[$sc['student_id']][$sc['subject_id']] = $sc;
}

// All skills/traits
$allSkills = [];
$skQ = $pdo->prepare("SELECT * FROM student_traits WHERE class_id = ? AND session_id = ? AND term_id = ? AND student_id IN ($inIds)");
$skQ->execute($params);
foreach ($skQ->fetchAll() as $sk) {
    $allSkills[$sk['student_id']][$sk['trait_type']][strtoupper(trim($sk['trait_name']))] = $sk['rating'];
}

function ordinal(int $n): string {
    if ($n >= 11 && $n <= 13) return $n . 'th';
    return match($n % 10) { 1 => $n . 'st', 2 => $n . 'nd', 3 => $n . 'rd', default => $n . 'th' };
}
$school_type = strtolower($active_school['school_type'] ?? '');
$is_higher_ed = (str_contains($school_type, 'tertiary') || str_contains($school_type, 'vocational') || str_contains($school_type, 'university'));

function calcGrade($total, $is_higher_ed = false) {
    if ($is_higher_ed) {
        if ($total >= 70) return 'A'; if ($total >= 60) return 'B'; if ($total >= 50) return 'C';
        if ($total >= 45) return 'D'; if ($total >= 40) return 'E'; return $total > 0 ? 'F' : '-';
    } else {
        if ($total >= 75) return 'A1'; if ($total >= 70) return 'B2'; if ($total >= 65) return 'B3';
        if ($total >= 60) return 'C4'; if ($total >= 55) return 'C5'; if ($total >= 50) return 'C6';
        if ($total >= 45) return 'D7'; if ($total >= 40) return 'E8'; return $total > 0 ? 'F9' : '-';
    }
}
function calcRemark($grade) {
    $g = substr($grade, 0, 1);
    if ($g === 'A') return 'Excellent'; if ($g === 'B') return 'Very Good';
    if ($g === 'C') return 'Good'; if ($g === 'D') return 'Fair';
    if ($g === 'E' || $grade === 'E8') return 'Pass'; if ($g === 'F') return 'Fail';
    return '-';
}
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Per-student totals
$studentSummaries = [];
foreach ($allStudents as $stu) {
    $total = 0; $count = 0;
    foreach ($subjects as $sub) {
        $sc = $allScores[$stu['id']][$sub['id']] ?? null;
        if ($sc && $sc['total'] !== null) { $total += $sc['total']; $count++; }
    }
    $studentSummaries[$stu['id']] = ['total' => $total, 'count' => $count, 'avg' => $count ? round($total / $count, 1) : 0];
}

// Class positions
$sorted = $studentSummaries;
uasort($sorted, fn($a, $b) => $b['total'] <=> $a['total']);
$positions = []; $pos = 1; $prev_total = null; $prev_pos = 1;
foreach ($sorted as $sid => $v) {
    if ($prev_total !== null && $v['total'] == $prev_total) { $positions[$sid] = $prev_pos; }
    else { $positions[$sid] = $pos; $prev_pos = $pos; $prev_total = $v['total']; }
    $pos++;
}

// Subject positions
$subjectPositions = [];
foreach ($subjects as $sub) {
    $sid = $sub['id'];
    $subScores = [];
    foreach ($allStudents as $stu) {
        $sc = $allScores[$stu['id']][$sid] ?? null;
        if ($sc && $sc['total'] !== null) { $subScores[$stu['id']] = (int)$sc['total']; }
    }
    arsort($subScores);
    $p = 1; $pv = null; $pp = 1;
    foreach ($subScores as $stuId => $v) {
        if ($pv !== null && $v == $pv) { $subjectPositions[$stuId][$sid] = $pp; }
        else { $subjectPositions[$stuId][$sid] = $p; $pp = $p; $pv = $v; }
        $p++;
    }
}

// Class stats
$allTotals = array_column($studentSummaries, 'total');
$classAvg = count($allTotals) ? round(array_sum($allTotals) / count($allTotals), 1) : 0;
$highest = count($allTotals) ? max($allTotals) : 0;
$lowest = count($allTotals) ? min($allTotals) : 0;

$affectiveTraits  = ['PUNCTUALITY','ATTENDANCE','RELIABILITY','NEATNESS','POLITENESS','HONESTY','RELATIONSHIP WITH STUDENTS','SELF CONTROL','ATTENTIVENESS','PERSEVERANCE'];
$psychomotorTraits = ['HANDWRITING','GAMES','SPORTS','DRAWING & PAINTING','CRAFTS','MUSICAL SKILLS'];

$logoSrc = $cls['logo'] ? '../' . ltrim($cls['logo'], '/') : '';
$wmText  = strtoupper($cls['school_name']);

// Current student data
$scores  = $allScores[$student_id] ?? [];
$skills  = $allSkills[$student_id] ?? [];
$summary = $studentSummaries[$student_id];
$stuPos  = $positions[$student_id] ?? 0;
$posText = $stuPos ? ordinal($stuPos) : '—';
$finalGrade = calcGrade($summary['avg'], $is_higher_ed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Report — <?= e($student_data['full_name']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #f1f5f9; font-family: Arial, Helvetica, sans-serif; overflow-x: hidden; }

.control-bar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
  background: #1a2b4a; color: #fff;
  padding: 10px 16px; display: flex; align-items: center; gap: 10px;
  box-shadow: 0 2px 12px rgba(0,0,0,.1); min-height: 52px;
}
.control-bar h4 {
  margin: 0; font-size: .88rem; font-weight: 800; flex: 1;
}
.ctrl-btn {
  border: none; border-radius: 8px; padding: 7px 14px;
  font-weight: 700; cursor: pointer; font-size: .82rem;
  text-decoration: none; display: inline-flex; align-items: center;
  gap: 5px; flex-shrink: 0; transition: background .2s;
}
.ctrl-btn-print { background: #1e6fcf; color: #fff; }
.ctrl-btn-back  { background: transparent; color: #94a3b8; border: none; }

.page-scaler {
  width: 100%; display: flex; flex-direction: column;
  align-items: center; padding: 70px 8px 48px; gap: 0; overflow-x: hidden;
}
.page-wrap { width: 100%; display: flex; justify-content: center; overflow: hidden; }

@page { size: A4 portrait; margin: 0; }
.page {
  width: 210mm; height: 297mm; max-height: 297mm;
  background: #fffef5; border: 1.5px solid #bbb;
  position: relative; overflow: hidden; display: block;
  padding: 7mm 8mm 6mm; transform-origin: top center; flex-shrink: 0;
  box-shadow: 0 6px 32px rgba(0,0,0,.12);
}

@media print {
  html, body { background: white !important; margin: 0 !important; padding: 0 !important; }
  .control-bar, .page-nav { display: none !important; }
  .page-scaler { padding: 0 !important; }
  .page { border: none !important; box-shadow: none !important; transform: none !important; margin: 0 !important; }
}

.watermark { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
.watermark-inner { width: 100%; height: 100%; display: flex; flex-wrap: wrap; align-items: flex-start; opacity: .05; transform: rotate(-22deg) scale(1.45); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.watermark-text { font-size: 9px; font-weight: bold; color: #8b6914; white-space: nowrap; padding: 5px 6px; letter-spacing: 1px; }

.pg-content { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; }

.hdr { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-bottom: 3px; }
.logo-circle { width: 64px; height: 64px; border-radius: 50%; border: 2px solid #8b1a1a; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f5f0e8; flex-shrink: 0; }
.logo-circle img { width: 100%; height: 100%; object-fit: cover; }

.hdr-text { flex: 1; text-align: center; }
.hdr-school-name { font-size: 19px; font-weight: 900; color: #8b1a1a; text-transform: uppercase; font-style: italic; display: block; line-height: 1.1; }
.hdr-motto   { font-size: 8.5px; color: #555; display: block; font-style: italic; }
.hdr-address { font-size: 8.5px; color: #333; display: block; line-height: 1.4; }
.hdr-tag     { font-size: 10px; font-weight: bold; color: #444; text-transform: uppercase; display: block; margin-top: 2px; }

.stu-photo { width: 60px; height: 72px; border-radius:30px; background: #f5f0e8; flex-shrink: 0; overflow: hidden; }
.stu-photo img { width: 100%; height: 100%; object-fit: cover; }

.divider { border-top: 1.5px solid #222; margin: 3px 0; }
.divider-thin { border-top: 1px solid #aaa; margin: 1px 0; }

.info-sec { margin-top: 3px; flex-shrink: 0; }
.info-row { display: flex; align-items: baseline; margin-bottom: 4px; gap: 3px; }
.info-row .lbl { font-weight: bold; font-size: 12px; white-space: nowrap; }
.info-row .val { border-bottom: 1px solid #333; flex: 1; height: 15px; font-size: 11.5px; padding-left: 3px; overflow: hidden; white-space: nowrap; }
.info-row .grp { display: flex; align-items: baseline; gap: 3px; flex: 1; margin-left: 8px; }

.sec-title { text-align: center; font-weight: bold; font-size: 13px; text-transform: uppercase; text-decoration: underline; margin: 4px 0 3px; }

.gt { width: 100%; border-collapse: collapse; font-size: 11px; }
.gt th { border: 1px solid #333; text-align: center; padding: 4px 2px; background: #f8f4ee; }
.gt th.subj-hd { color: #8b1a1a; text-align: left; padding-left: 4px; }
.gt td { border: 1px solid #333; padding: 1px 2px; text-align: center; height: 18px; }
.gt td.subj-td { text-align: left; padding-left: 4px; font-weight: bold; }

.grade-note { margin-top: 2px; font-size: 8.5px; display: flex; justify-content: space-between; }
.grade-note strong { color: #c0392b; }

.brow { display: flex; gap: 6px; margin-top: 5px; flex: 1; overflow: hidden; }
.trait-tbl { border-collapse: collapse; font-size: 10.5px; width: 100%; }
.trait-tbl th { border: 1px solid #555; text-align: center; padding: 3px 2px; background: #f8f4ee; }
.trait-tbl td { border: 1px solid #555; padding: 2px 4px; }
.trait-tbl td.rc { width: 38px; text-align: center; font-weight: 700; }

.scale-box { font-size: 10px; min-width: 148px; max-width: 148px; }
.scale-title { color: #8b1a1a; font-weight: bold; font-size: 12px; text-align: center; margin-bottom: 4px; }
.scale-box p { margin-bottom: 3px; }

.pg-footer { flex-shrink: 0; padding-top: 5px; margin-top: auto; margin-bottom: 40px; }
.foot-line { display: flex; align-items: baseline; margin-bottom: 10px; }
.foot-lbl { font-weight: bold; font-size: 11px; white-space: nowrap; text-transform: uppercase; }
.foot-ul { flex: 1; border-bottom: 1px solid #333; margin-left: 5px; height: 12px; }

@media(max-width: 600px) {
  .control-bar h4 { font-size: .75rem; }
  .ctrl-btn span { display: none; }
}
</style>
</head>
<body>

<div class="control-bar">
  <h4>📄 <?= e(get_label('Report Card')) ?> — <?= e($student_data['full_name']) ?></h4>
  <button onclick="window.print()" class="ctrl-btn ctrl-btn-print">
    <i class="fa-solid fa-print"></i><span>Print / PDF</span>
  </button>
  <a href="reports.php" class="ctrl-btn ctrl-btn-back">
    <i class="fa-solid fa-arrow-left"></i><span>Back</span>
  </a>
</div>

<div class="page-scaler">
  <div class="page-wrap">
    <div class="page" id="reportPage">
      <div class="watermark"><div class="watermark-inner" id="wmInner"></div></div>
      
      <div class="pg-content">
        <div class="hdr">
          <div class="logo-circle">
            <?php if ($logoSrc): ?>
              <img src="<?= $logoSrc ?>" alt="Logo"/>
            <?php else: ?>
              <div class="logo-inner" style="color:#8b1a1a; font-size:10px; font-weight:900;">LOGO</div>
            <?php endif; ?>
          </div>
          <div class="hdr-text">
            <span class="hdr-school-name"><?= e($cls['school_name']) ?></span>
            <?php if ($cls['motto']): ?><span class="hdr-motto">"<?= e($cls['motto']) ?>"</span><?php endif; ?>
            <span class="hdr-address"><?= nl2br(e($cls['school_address'])) ?></span>
            <div class="divider-thin"></div>
            <span class="hdr-tag"><?= strtoupper(get_label('Report Card')) ?></span>
          </div>
          <div class="stu-photo">
            <img src="<?= $student_data['image_path'] ? '../'.e($student_data['image_path']) : '../img/default_picture.png' ?>" alt="photo" onerror="this.src='../img/default_picture.png'"/>
          </div>
        </div>

        <div class="divider"></div>

        <div class="info-sec">
          <div class="info-row"><span class="lbl">Name:</span><div class="val">&nbsp;<?= e($student_data['full_name']) ?></div></div>
          <div class="info-row">
            <span class="lbl">Gender:</span><div class="val" style="max-width:100px;">&nbsp;<?= e($student_data['gender']) ?></div>
            <div class="grp"><span class="lbl">Total&nbsp;Score:</span><div class="val" style="max-width:70px;">&nbsp;<?= $summary['total'] ?></div></div>
            <div class="grp"><span class="lbl">Final&nbsp;Grade:</span><div class="val">&nbsp;<?= $finalGrade ?></div></div>
          </div>
          <div class="info-row">
            <span class="lbl"><?= get_label('Term') ?>:</span><div class="val" style="max-width:100px;">&nbsp;<?= e(get_label($cls['term'])) ?></div>
            <div class="grp"><span class="lbl">Class&nbsp;Average:</span><div class="val" style="max-width:70px;">&nbsp;<?= $classAvg ?></div></div>
            <div class="grp"><span class="lbl">Final&nbsp;Average:</span><div class="val">&nbsp;<?= $summary['avg'] ?></div></div>
          </div>
          <div class="info-row">
            <span class="lbl">Highest:</span><div class="val" style="max-width:90px;">&nbsp;<?= $highest ?></div>
            <div class="grp"><span class="lbl">Lowest:</span><div class="val" style="max-width:70px;">&nbsp;<?= $lowest ?></div></div>
            <div class="grp"><span class="lbl">No.&nbsp;In&nbsp;Class:</span><div class="val">&nbsp;<?= count($allStudents) ?></div></div>
          </div>
          <div class="info-row">
            <span class="lbl"><?= get_label('Class') ?>:</span><div class="val" style="max-width:120px;">&nbsp;<?= e($cls['name']) ?></div>
            <div class="grp"><span class="lbl">Position:</span><div class="val" style="max-width:80px;">&nbsp;<strong><?= $posText ?></strong></div></div>
            <div class="grp"><span class="lbl">Next <?= get_label('Term') ?>:</span><div class="val">&nbsp;—</div></div>
          </div>
        </div>

        <div class="sec-title"><?= e($cls['section_name']) ?></div>

        <table class="gt">
          <thead>
            <tr>
              <th class="subj-hd" style="width:25%;"><?= strtoupper(get_label('Subject')) ?></th>
              <th style="width:10%;">CA1 (20)</th>
              <th style="width:10%;">CA2 (20)</th>
              <th style="width:10%;">EXAM (60)</th>
              <th style="width:10%;">TOTAL</th>
              <th style="width:8%;">POS</th>
              <th style="width:8%;">GRADE</th>
              <th>REMARK</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subjects as $sub):
              $sc = $scores[$sub['id']] ?? null;
              $tot = $sc ? $sc['total'] : null;
              $grade = ($tot !== null) ? calcGrade($tot, $is_higher_ed) : '';
              $spos = $subjectPositions[$student_id][$sub['id']] ?? null;
            ?>
            <tr>
              <td class="subj-td"><?= e($sub['name']) ?></td>
              <td><?= $sc ? $sc['ca1'] : '—' ?></td>
              <td><?= $sc ? $sc['ca2'] : '—' ?></td>
              <td><?= $sc ? $sc['exam'] : '—' ?></td>
              <td><?= $tot !== null ? $tot : '—' ?></td>
              <td><?= $tot !== null ? ordinal($spos) : '—' ?></td>
              <td><?= $grade ?: '—' ?></td>
              <td style="font-size: 9px;"><?= $grade ? calcRemark($grade) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="grade-note">
          <span><strong>Scale:</strong> A1=75-100 · B2=70-74 · B3=65-69 · C4=60-64 · C5=55-59 · C6=50-54 · D7=45-49 · E8=40-44 · F9=0-39</span>
          <span><strong><?= get_label('Subjects') ?>: <?= count($subjects) ?></strong></span>
        </div>

        <div class="brow">
          <div style="flex:1.1;">
            <table class="trait-tbl">
              <thead><tr><th colspan="2">AFFECTIVE TRAITS</th></tr><tr><th>TRAIT</th><th>RATING</th></tr></thead>
              <tbody>
                <?php foreach ($affectiveTraits as $trait): $r = $skills['affective'][$trait] ?? null; ?>
                <tr><td><?= e($trait) ?></td><td class="rc"><?= $r !== null ? $r : 'N/S' ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div style="flex:0.9;">
            <table class="trait-tbl">
              <thead><tr><th colspan="2">PSYCHOMOTOR</th></tr><tr><th>SKILL</th><th>RATING</th></tr></thead>
              <tbody>
                <?php foreach ($psychomotorTraits as $trait): $r = $skills['psychomotor'][$trait] ?? null; ?>
                <tr><td><?= e($trait) ?></td><td class="rc"><?= $r !== null ? $r : 'N/S' ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="scale-box">
            <div class="scale-title">RATING SCALE</div>
            <p>5 — Excellent Level</p><p>4 — Good Level</p><p>3 — Fair/Acceptable</p>
            <p>2 — Poor Level</p><p>1 — No Observable Trait</p>
            <p style="margin-top:6px;font-style:italic;color:#555;">N/S = Not Scored</p>
          </div>
        </div>

        <div class="pg-footer">
          <div class="foot-line"><span class="foot-lbl"><?= get_label('Form Teacher') ?>'s Name</span><div class="foot-ul"></div></div>
          <div class="foot-line"><span class="foot-lbl"><?= get_label('Form Teacher') ?>'s Comment</span><div class="foot-ul"></div></div>
          <div class="foot-line"><span class="foot-lbl"><?= get_label('Head Teacher') ?>'s Comment</span><div class="foot-ul"></div></div>
          <div class="foot-line" style="margin-bottom:0;"><span class="foot-lbl"><?= get_label('Head Teacher') ?>'s Signature & Stamp</span><div class="foot-ul"></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Watermark build
(function() {
  const wm = document.getElementById('wmInner');
  const txt = '<?= addslashes($wmText) ?>';
  for (let i = 0; i < 150; i++) {
    const s = document.createElement('span');
    s.className = 'watermark-text';
    s.textContent = txt + ' ';
    wm.appendChild(s);
  }
})();

// Scaler build
(function() {
  const page = document.getElementById('reportPage');
  const wrap = page.parentElement;
  function scale() {
    const vw = window.innerWidth;
    const pageW = 794; // approx A4 in px at 96dpi
    const scale = Math.min(1, (vw - 20) / pageW);
    if (scale < 1) {
      page.style.transform = `scale(${scale})`;
      wrap.style.height = (1123 * scale) + 'px';
    } else {
      page.style.transform = 'none';
      wrap.style.height = '1123px';
    }
  }
  scale();
  window.addEventListener('resize', scale);
})();
</script>

</body>
</html>
