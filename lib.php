<?php
defined('MOODLE_INTERNAL') || die();

/* ============================================================
   FUNÇÕES ORIGINAIS (MANTIDAS)
   ============================================================ */

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

    $oneweekago = time() - (7 * 24 * 60 * 60);
    $logs = $DB->get_records_select('logstore_standard_log', 'timecreated > ?', [$oneweekago]);

    if (!$logs) {
        return 0;
    }

    $times = [];
    foreach ($logs as $log) {
        $userid = $log->userid;
        if (!isset($times[$userid])) {
            $times[$userid] = ['last' => $log->timecreated, 'total' => 0];
        } else {
            $diff = $log->timecreated - $times[$userid]['last'];
            if ($diff < 1800) {
                $times[$userid]['total'] += $diff;
            }
            $times[$userid]['last'] = $log->timecreated;
        }
    }

    $totaltime = 0;
    $count = 0;
    foreach ($times as $data) {
        if ($data['total'] > 0) {
            $totaltime += $data['total'];
            $count++;
        }
    }

    return $count > 0 ? round(($totaltime / $count) / 60, 1) : 0;
}

/**
 * Retorna a participação dos alunos em fóruns.
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
 * Retorna submissões da última semana.
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

/**
 * Alerta de baixo engajamento.
 */
function local_studentanalytics_low_engagement_alerts($threshold = 5, $grade_limit = 50) {
    $alerts = [];
    $students = local_studentanalytics_get_access_counts();

    foreach ($students as $student) {
        if ($student->accesscount < $threshold) {
            $alerts[] = $student->firstname . ' ' . $student->lastname;
        }
    }

    return $alerts;
}



/* ============================================================
   FUNÇÕES DA TABELA PRÓPRIA (OPÇÃO B)
   ============================================================ */

/**
 * Salva métricas na tabela studentanalytics.
 */
function local_studentanalytics_save_metrics($userid, $access, $posts, $subs, $avggrade) {
    global $DB;

    $record = new stdClass();
    $record->userid = $userid;
    $record->total_access = $access;
    $record->forum_posts = $posts;
    $record->assignments_submitted = $subs;
    $record->average_grade = $avggrade;
    $record->timemodified = time();

    if ($existing = $DB->get_record('studentanalytics', ['userid' => $userid])) {
        $record->id = $existing->id;
        $DB->update_record('studentanalytics', $record);
    } else {
        $DB->insert_record('studentanalytics', $record);
    }
}

/**
 * Retorna dados reais da tabela studentanalytics.
 */
function local_studentanalytics_get_all_metrics() {
    global $DB;

    $sql = "
        SELECT sa.*, u.firstname, u.lastname
        FROM {studentanalytics} sa
        JOIN {user} u ON u.id = sa.userid
        ORDER BY sa.total_access DESC
    ";

    return $DB->get_records_sql($sql);
}

/**
 * Coleta dados reais do Moodle.
 */
function local_studentanalytics_collect_data_for_user($userid) {
    global $DB;

    $access = $DB->count_records('logstore_standard_log', ['userid' => $userid]);
    $posts = $DB->count_records('forum_posts', ['userid' => $userid]);
    $subs = $DB->count_records('assign_submission', ['userid' => $userid]);

    $avg = $DB->get_field_sql("
        SELECT AVG(finalgrade)
        FROM {grade_grades}
        WHERE userid = ?
    ", [$userid]);

    return [$access, $posts, $subs, $avg ?? 0];
}

/**
 * Processa todos os alunos reais.
 */
function local_studentanalytics_process_all() {
    global $DB;

    $users = $DB->get_records('user', ['deleted' => 0]);

    foreach ($users as $u) {
        if ($u->id <= 2) continue; // ignora admin/guest

        list($access, $posts, $subs, $avg) =
            local_studentanalytics_collect_data_for_user($u->id);

        local_studentanalytics_save_metrics($u->id, $access, $posts, $subs, $avg);
    }
}

/**
 * Lê CSV como fallback (opção C).
 */
function local_studentanalytics_read_csv_fallback() {
    $csvDir = __DIR__ . '/upload/';
    $csvFiles = glob($csvDir . "*.csv");
    $latestCsv = !empty($csvFiles) ? end($csvFiles) : null;

    if (!$latestCsv) { return []; }

    $students = [];

    if (($handle = fopen($latestCsv, "r")) !== false) {

        $firstLine = fgets($handle);
        $sep = strpos($firstLine, ";") !== false ? ";" : ",";
        rewind($handle);

        $header = fgetcsv($handle, 0, $sep);

        while (($row = fgetcsv($handle, 0, $sep)) !== false) {
            if (count($row) !== count($header)) continue;

            $a = array_combine($header, $row);

            $obj = (object)[
                'userid' => intval($a['student_id'] ?? 0),
                'firstname' => $a['firstname'] ?? '',
                'lastname' => $a['lastname'] ?? '',
                'total_access' => intval($a['total_access'] ?? 0),
                'forum_posts' => intval($a['forum_posts'] ?? 0),
                'assignments_submitted' => intval($a['assignments_submitted'] ?? 0),
                'average_grade' => floatval($a['average_grade'] ?? 0),
            ];

            $students[] = $obj;
        }

        fclose($handle);
    }

    return $students;
}

function local_studentanalytics_before_http_headers() {
    global $PAGE;

    // Garante que o CSS carregue em todas as páginas do plugin
    if (strpos($PAGE->url, '/local/studentanalytics') !== false) {
        $PAGE->requires->css('/local/studentanalytics/style.css');
    }
}
