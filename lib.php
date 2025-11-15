<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Retorna os 10 alunos mais ativos com base na contagem de acessos.
 */
function local_studentanalytics_get_access_counts() {
    global $DB;

    $sql = "SELECT u.id, u.firstname, u.lastname, COUNT(l.id) AS accesscount
            FROM {user} u
            JOIN {logstore_standard_log} l ON l.userid = u.id
            WHERE l.action = 'viewed'
            GROUP BY u.id, u.firstname, u.lastname
            ORDER BY accesscount DESC
            LIMIT 10";

    return $DB->get_records_sql($sql);
}

/**
 * Calcula o tempo médio de acesso por aluno na última semana (em minutos).
 */
function local_studentanalytics_get_average_access_time() {
    global $DB;

    // Obtém logs da última semana
    $oneweekago = time() - (7 * 24 * 60 * 60);
    $logs = $DB->get_records_select('logstore_standard_log', 'timecreated > ?', [$oneweekago]);

    if (!$logs) {
        return 0;
    }

    // Agrupa os tempos por usuário
    $times = [];
    foreach ($logs as $log) {
        $userid = $log->userid;
        if (!isset($times[$userid])) {
            $times[$userid] = ['last' => $log->timecreated, 'total' => 0];
        } else {
            $diff = $log->timecreated - $times[$userid]['last'];
            // Ignora intervalos maiores que 30 minutos (usuário provavelmente ausente)
            if ($diff < 1800) {
                $times[$userid]['total'] += $diff;
            }
            $times[$userid]['last'] = $log->timecreated;
        }
    }

    // Calcula média geral em minutos
    $totaltime = 0;
    $count = 0;
    foreach ($times as $data) {
        if ($data['total'] > 0) {
            $totaltime += $data['total'];
            $count++;
        }
    }

    return $count > 0 ? round(($totaltime / $count) / 60, 1) : 0; // Retorna em minutos
}
/**
 * Retorna a participação dos alunos em fóruns (número de posts) na última semana.
 */
function local_studentanalytics_get_forum_participation() {
    global $DB;

    $oneweekago = time() - (7 * 24 * 60 * 60);

    $sql = "SELECT u.id, u.firstname, u.lastname, COUNT(p.id) AS posts
            FROM {user} u
            JOIN {forum_posts} p ON p.userid = u.id
            WHERE p.created > ?
            GROUP BY u.id, u.firstname, u.lastname
            ORDER BY posts DESC
            LIMIT 10";

    return $DB->get_records_sql($sql, [$oneweekago]);
}

/**
 * Retorna a quantidade de atividades entregues por aluno na última semana.
 */
function local_studentanalytics_get_submission_counts() {
    global $DB;

    $oneweekago = time() - (7 * 24 * 60 * 60);

    $sql = "SELECT u.id, u.firstname, u.lastname, COUNT(s.id) AS submissions
            FROM {user} u
            JOIN {assign_submission} s ON s.userid = u.id
            WHERE s.timecreated > ?
            GROUP BY u.id, u.firstname, u.lastname
            ORDER BY submissions DESC
            LIMIT 10";

    return $DB->get_records_sql($sql, [$oneweekago]);
}

function local_studentanalytics_low_engagement_alerts($threshold = 5, $grade_limit = 50) {
    global $DB;
    $alerts = [];

    $topstudents = local_studentanalytics_get_access_counts();
    $grades = local_studentanalytics_get_grades();

    foreach ($topstudents as $student) {
        $avggrade = $grades[$student->id]->avggrade ?? 0;
        if ($student->accesscount < $threshold || $avggrade < $grade_limit) {
            $alerts[] = $student->firstname . ' ' . $student->lastname;
        }
    }
    return $alerts;
}