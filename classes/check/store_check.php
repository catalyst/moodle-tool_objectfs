<?php

namespace tool_objectfs\check;

use coding_exception;
use core\check\check;
use core\check\result;
use Throwable;

class store_check extends check {
    private $type;

    public const TYPE_CONNECTION = 'connection';

    public const TYPE_PERMISSIONS = 'permissions';

    public const TYPE_RANGEREQUEST = 'rangerequest';

    public const TYPES = [self::TYPE_CONNECTION, self::TYPE_PERMISSIONS, self::TYPE_RANGEREQUEST];

    public function __construct(string $type) {
        if (!in_array($type, self::TYPES)) {
            throw new coding_exception("Given test type " . $type . " is not valid.");
        }

        $this->type = $type;
    }

    public function get_id(): string {
        return "store_check_" . $this->type;
    }

    public function get_result(): result {
        try {
            // Check if configured first, and report NA if not configured.
            if (!\tool_objectfs\local\manager::check_file_storage_filesystem()) {
                return new result(result::NA, "todo lang Not configured.");
            }

            // Load objectfs and run a test.
            $config = \tool_objectfs\local\manager::get_objectfs_config();
            $client = \tool_objectfs\local\manager::get_client($config);

            if (empty($client)) {
                return new result(result::UNKNOWN, "TODO lang client not configured");
            }

            $results = [];

            // TODO test delete.
            switch($this->type) {
                case self::TYPE_CONNECTION:
                    $results = $client->test_permissions(false);
                    break;

                case self::TYPE_RANGEREQUEST:
                    $results = $client->test_range_request(new $config->filesystem());
                    break;

                case self::TYPE_PERMISSIONS:
                    $results = $client->test_permissions(false);
                    break;
            }

            if (empty($results)) {
                return new result(result::UNKNOWN, "TODO lang Test type " . $this->type . " was configured, but no test was executed.");
            }

            $status = $results['success'] ? result::OK : result::ERROR;
            return new result($status, $results['details']);
        } catch (Throwable $e) {
            return new result(result::CRITICAL, "TODO lang Error while executing store check type " . $this->type . ': ' . $e->getMessage());
        }
    }
}
