<?php

namespace local_studentanalytics\task;

defined('MOODLE_INTERNAL') || die();

class process_logs extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('cronprocesslogs', 'local_studentanalytics');
    }

    public function execute() {
        global $DB;

        $since = strtotime('-1 day');

        $sql = "SELECT userid,
                       COUNT(*) AS eventos
                FROM {logstore_standard_log}
                WHERE timecreated >= :since
                GROUP BY userid";

        $params = ['since' => $since];
        $data = $DB->get_records_sql($sql, $params);

        foreach ($data as $row) {
            // Salvar métricas na tabela criada em install.xml
            $record = new \stdClass();
            $record->userid = $row->userid;
            $record->events = $row->eventos;
            $record->timemodified = time();

            // UPSERT
            if ($existing = $DB->get_record('local_studentanalytics', ['userid' => $row->userid])) {
                $record->id = $existing->id;
                $DB->update_record('local_studentanalytics', $record);
            } else {
                $DB->insert_record('local_studentanalytics', $record);
            }
        }

        mtrace("studentanalytics: métricas atualizadas.");
    }
}
