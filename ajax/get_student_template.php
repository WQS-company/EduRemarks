<?php
// ajax/get_student_template.php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="student_upload_template.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['FullName', 'AdmissionNo', 'Gender', 'DOB (YYYY-MM-DD)', 'GuardianName', 'GuardianPhone', 'Address']);
fclose($output);
exit();
?>
