<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=studentanalytics.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Aluno', 'Acessos', 'Posts em Fórum', 'Entregas', 'Nota Média']);

$topstudents = local_studentanalytics_get_access_counts();
$forumpart = local_studentanalytics_get_forum_participation();
$submissions = local_studentanalytics_get_submission_counts();
$grades = local_studentanalytics_get_grades();

foreach ($topstudents as $student) {
    $id = $student->id;
    $fposts = $forumpart[$id]->posts ?? 0;
    $subs = $submissions[$id]->submissions ?? 0;
    $avggrade = $grades[$id]->avggrade ?? 0;
    
    fputcsv($output, [
        $student->firstname . ' ' . $student->lastname,
        $student->accesscount,
        $fposts,
        $subs,
        $avggrade
    ]);
}
fclose($output);

<a href="export.php" class="btn-export">📥 Exportar CSV</a>