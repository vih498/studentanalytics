<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_externalpage(
        'local_studentanalytics',
        get_string('pluginname', 'local_studentanalytics'),
        new moodle_url('/local/studentanalytics/index.php')
    );
}