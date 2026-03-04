<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_objectfs\task;

use tool_objectfs\local\manager;

/**
 * Reconciles the filedir.
 *
 * @package   tool_objectfs
 * @author    Alex Damsted <alexdamsted@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_filedir extends task {
    /** @var string $stringname */
    protected $stringname = 'task:reconcilefiledir';

    /**
     * Execute task
     */
    public function execute(): void {
        global $DB, $CFG;

        if (!$this->enabled_tasks()) {
            return;
        }

        // The config deletelocal must be enabled.
        if (!get_config('tool_objectfs', 'deletelocal')) {
            mtrace('ObjectFS: deletelocal disabled — skipping filedir reconciliation.');
            return;
        }

        // Start time for runtime + graceful shutdown checks.
        $cronstart = time();

        // 1 hour max run time (configurable).
        $maxtime = (int)get_config('tool_objectfs', 'maxtaskruntime');
        if ($maxtime <= 0) {
            $maxtime = 3600;
        }

        // Get the last hash if it exists.
        $lasthash = get_config('tool_objectfs', 'reconcile_file_lasthash');
        if ($lasthash === false) {
            $lasthash = '';
        }

        // Tracking of processed, deleted, inserted and updated files.
        $processed = 0;
        $deleted   = 0;
        $inserted  = 0;
        $updated   = 0;
        $skipped   = 0;

        // Time-based checkpoint tracking.
        $lastcheckpoint = time();
        $checkpointinterval = 10;

        // Get the path to the filedir.
        if (isset($CFG->filedir)) {
            $filedir = $CFG->filedir;
        } else {
            $filedir = $CFG->dataroot . '/filedir';
        }

        // Our starting dirs.
        $startdir1 = '';
        $startdir2 = '';
        if (!empty($lasthash) && preg_match('/^[a-f0-9]{40}$/', $lasthash)) {
            $startdir1 = substr($lasthash, 0, 2);
            $startdir2 = substr($lasthash, 2, 2);
            mtrace("ObjectFS: Resuming from hash {$lasthash} (dir {$startdir1}/{$startdir2})");
        } else {
            mtrace("ObjectFS: Starting full reconciliation from beginning.");
        }

        // Get existing first-level dirs.
        $dir1list = scandir($filedir);
        if ($dir1list === false) {
            mtrace("ObjectFS: Unable to scan filedir.");
            return;
        }

        // Filter out some first-level dirs.
        $dir1list = array_filter($dir1list, function ($d) use ($filedir) {
            return is_dir($filedir . '/' . $d) &&
                   preg_match('/^[a-f0-9]{2}$/', $d);
        });

        // Sort the first-level dirs.
        sort($dir1list, SORT_STRING);

        // Traverse the Moodle filedir deterministically in hexadecimal order (00–ff).
        //
        // Doing this deterministically allows us to resume the process from where we
        // left off on each scheduled task run.
        //
        // The filedir structure is derived from the SHA1 contenthash:
        //
        // filedir/<first two chars>/<next two chars>/<full contenthash>
        //
        // E.g.,
        // contenthash = 0abbcdef...
        // path = filedir/0a/bb/0abbcdef...
        //
        // This creates a two-level directory hierarchy:
        //
        // 1. Top-level directories: 00–ff (256 possible prefixes)
        // 2. Second-level directories: 00–ff within each top-level directory
        //
        // Not all prefix combinations necessarily exist on disk and we don't care.
        //
        foreach ($dir1list as $dir1) {
            // Resume from our last hash.
            if ($startdir1 !== '' && strcmp($dir1, $startdir1) < 0) {
                continue;
            }

            $path1 = $filedir . '/' . $dir1;

            // Get existing second-level dirs.
            $dir2list = scandir($path1);
            if ($dir2list === false) {
                continue;
            }

            // Filter out some second-level dirs.
            $dir2list = array_filter($dir2list, function ($d) use ($path1) {
                return is_dir($path1 . '/' . $d) &&
                       preg_match('/^[a-f0-9]{2}$/', $d);
            });

            sort($dir2list, SORT_STRING);

            foreach ($dir2list as $dir2) {
                // Resume from our last hash.
                if (
                    $startdir1 !== '' && $dir1 === $startdir1 &&
                    $startdir2 !== '' && strcmp($dir2, $startdir2) < 0
                ) {
                    continue;
                }

                $subdir = $path1 . '/' . $dir2;

                // Scan the subdir for files.
                $files = scandir($subdir);
                if ($files === false) {
                    continue;
                }

                // Remove . and ..
                $files = array_diff($files, ['.', '..']);

                // Sort the files to guarantee lexical hash order.
                // Our resume logic and desired determinism requires some kind of order.
                sort($files, SORT_STRING);

                if (is_writable($subdir) && empty($files)) {
                    // Remove empty second-level dir.
                    @rmdir($subdir);
                    continue;
                }

                foreach ($files as $filename) {
                    // Quit after a set time.
                    if ((time() - $cronstart) >= $maxtime) {
                        set_config('reconcile_file_lasthash', $lasthash, 'tool_objectfs');
                        mtrace("ObjectFS: Time limit reached.");
                        mtrace("ObjectFS: Finishing on hash {$lasthash}");
                        $this->log_summary($processed, $deleted, $inserted, $updated, $skipped);
                        return;
                    }

                    // Full path to the file.
                    $fullpath = $subdir . '/' . $filename;

                    // Delete file if it's a .tmp file older than 24 hours.
                    $pathinfo = pathinfo($fullpath);
                    if (!empty($pathinfo['extension']) && $pathinfo['extension'] === 'tmp') {
                        $modified = filemtime($fullpath);
                        if ($modified !== false && $modified <= (time() - DAYSECS)) {
                            @unlink($fullpath);
                            $deleted++;
                            continue;
                        }
                    }

                    // Validate SHA1 format.
                    if (!preg_match('/^[a-f0-9]{40}$/', $filename)) {
                        continue;
                    }

                    // Resume from our last hash.
                    if ($lasthash !== '' && strcmp($filename, $lasthash) <= 0) {
                        continue;
                    }

                    // Check if file exists in mdl_files.
                    $fileexists = $DB->record_exists('files', ['contenthash' => $filename]);

                    // Not referenced, safe to delete.
                    if (!$fileexists && get_config('tool_objectfs', 'deletelocal')) {
                        // File younger than configured duration, skip.
                        $minage = (int)get_config('tool_objectfs', 'minorphanedage');
                        if ($minage > 0) {
                            $filemtime = filemtime($fullpath);

                            if ($filemtime !== false) {
                                $ageinseconds = time() - $filemtime;
                                if ($ageinseconds < $minage) {
                                    $skipped++;
                                    continue;
                                }
                            }
                        }

                        if (!@unlink($fullpath)) {
                            mtrace("ObjectFS: Failed to delete file {$fullpath}");
                        } else {
                            $deleted++;
                        }
                    } else if ($fileexists) {
                        // File exists in mdl_files, ensure it exists in ObjectFS.
                        $object = $DB->get_record(
                            'tool_objectfs_objects',
                            ['contenthash' => $filename]
                        );

                        if (!$object) {
                            $record = (object)[
                                'contenthash'     => $filename,
                                'timeduplicated'  => 0,
                                'filesize'        => filesize($fullpath) ?: null,
                            ];
                            manager::upsert_object($record, OBJECT_LOCATION_LOCAL);
                            $inserted++;
                        } else {
                            // If ObjectFS thinks the file is remote-only,
                            // update it so it knows the file is duplicated.
                            if ($object->location == OBJECT_LOCATION_EXTERNAL) {
                                manager::upsert_object($object, OBJECT_LOCATION_DUPLICATED);
                                $updated++;
                            }
                        }
                    }

                    // Record the last hash for the resume logic.
                    $lasthash = $filename;

                    // Persist progress frequently for safety.
                    //
                    // Every 100 files or every 10 seconds,
                    // we save our place in the traversal so the task can
                    // resume safely without reprocessing or skipping
                    // too many files.
                    //
                    // We do not want to set_config every loop, that would be slow.
                    $processed++;
                    $now = time();
                    if (
                        $processed % 100 === 0 ||
                        ($now - $lastcheckpoint) >= $checkpointinterval
                    ) {
                        set_config('reconcile_file_lasthash', $lasthash, 'tool_objectfs');

                        mtrace("ObjectFS: Checkpoint at {$processed} files. Current hash {$lasthash}");

                        // Allow graceful shutdown if cron is trying to exit.
                        if (
                            \core\local\cli\shutdown::should_gracefully_exit() ||
                            \core\task\manager::static_caches_cleared_since($cronstart)
                        ) {
                            mtrace("ObjectFS: Graceful shutdown requested.");
                            $this->log_summary($processed, $deleted, $inserted, $updated, $skipped);
                            return;
                        }
                    }
                }
            }

            // After processing all second-level dirs, check if first-level dir is empty.
            $remaining = array_diff(scandir($path1), ['.', '..']);
            if (is_writable($path1) && empty($remaining)) {
                @rmdir($path1);
            }
        }

        // Completed full sweep.
        set_config('reconcile_file_lasthash', '', 'tool_objectfs');

        mtrace("ObjectFS: Filedir reconciliation complete.");
        if ($lasthash) {
            mtrace("ObjectFS: Finished on hash {$lasthash}");
        }
        $this->log_summary($processed, $deleted, $inserted, $updated, $skipped);
    }

    /**
     * Outputs a reconciliation summary to CLI.
     *
     * Logs the total number of processed, deleted, inserted, updated,
     * and skipped files for the current execution cycle.
     *
     * @param int $processed Total number of files processed.
     * @param int $deleted   Total number of files deleted.
     * @param int $inserted  Total number of ObjectFS records inserted.
     * @param int $updated   Total number of ObjectFS records updated.
     * @param int $skipped   Total number of files skipped.
     * @return void
     */
    private function log_summary(
        int $processed,
        int $deleted,
        int $inserted,
        int $updated,
        int $skipped
    ): void {
        mtrace(
            "ObjectFS: Processed {$processed}, deleted {$deleted}, " .
            "inserted {$inserted}, updated {$updated}, skipped {$skipped}."
        );
    }
}
