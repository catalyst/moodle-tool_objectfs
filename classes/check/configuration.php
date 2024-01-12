<?php

namespace tool_objectfs\check;

use core\check\check;
use core\check\result;

class configuration extends check {
    public function get_result(): result {
        // Load objectfs and run a test.
        $config = \tool_objectfs\local\manager::get_objectfs_config();
        $client = \tool_objectfs\local\manager::get_client($config);

        if (empty($client)) {
            return new result(result::UNKNOWN, get_string('check:configuration:empty', 'tool_objectfs'));
        }

        $configstatus = $client->test_configuration();
        $status = $configstatus->success ? result::OK : result::ERROR;
        $summary = $configstatus->success ? get_string('check:configuration:ok', 'tool_objectfs')
            : get_string('check:configuration:error', 'tool_objectfs');
        $details = nl2br($configstatus->details);

        return new result($status, $summary, $details);
    }

    // TODO action link
}
