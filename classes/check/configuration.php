<?php

namespace tool_objectfs\check;

use core\check\check;
use core\check\result;

class configuration extends check {
    public function get_result(): result
    {
        // Load objectfs and run a test.
        $config = \tool_objectfs\local\manager::get_objectfs_config();
        $client = \tool_objectfs\local\manager::get_client($config);

        if (empty($client)) {
            return new result(result::WARNING, "TODO lang Client was empty");
        }

        $configstatus = $client->get_configuration_check_status();
        $status = $configstatus['ok'] ? result::OK : result::ERROR;
        $summary = $configstatus['ok'] ? "OK" : "ERRORS"; // TODO lang.
        $details = nl2br($configstatus['details']);

        return new result($status, $summary, $details);
    }
}
