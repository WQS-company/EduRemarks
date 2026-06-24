<?php
require 'config/db.php';
print_r($pdo->query("DESCRIBE cbt_exams")->fetchAll(PDO::FETCH_ASSOC));
print_r($pdo->query("DESCRIBE cbt_student_attempts")->fetchAll(PDO::FETCH_ASSOC));
