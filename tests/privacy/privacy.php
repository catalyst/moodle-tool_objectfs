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

/**
 * @package   tool_objectfs
 * @copyright Moodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');

if (!class_exists('\core_privacy\manager')) {
    echo "Only checking privacy for Moodle 3.3+ (missing \core_privacy\manager class).\n";
    exit(0);
}

// Set this if you want to run the script for one component only. Otherwise leave empty.
$checkcomponent = '';

$rc = new \ReflectionClass(\core_privacy\manager::class);
$rcm = $rc->getMethod('get_component_list');
$rcm->setAccessible(true);

$manager = new \core_privacy\manager();
$components = $rcm->invoke($manager);

$list = (object)[
    'good' => [],
    'bad'  => [],
];

foreach ($components as $component) {
    if ($checkcomponent && $component !== $checkcomponent) {
        continue;
    }
    $compliant = $manager->component_is_compliant($component);
    if ($compliant) {
        $list->good[] = $component;
    } else {
        $list->bad[] = $component;
    }
}

echo "The following plugins are not compliant:\n";
echo "=> " . implode("\n=> ", array_values($list->bad)) . "\n";

if (count($list->bad) > 0) {
    exit(1);
}
echo "\n";
echo "Testing the compliant plugins:\n";
foreach ($list->good as $component) {
    $classname = \core_privacy\manager::get_provider_classname_for_component($component);
    echo "== {$component} ($classname) ==\n";
    if (check_implements($component, \core_privacy\local\metadata\provider::class)) {
        $collection = new \core_privacy\local\metadata\collection($component);
        $classname::get_metadata($collection);
        $count = count($collection);
        if (empty($count)) {
            echo "!!! No metadata found!!! This an error.\n";
            exit(1);
        }

        if (check_implements($component, \core_privacy\local\request\user_preference_provider::class)) {
            $userprefdescribed = false;
            foreach ($collection->get_collection() as $item) {
                if ($item instanceof \core_privacy\local\metadata\types\user_preference) {
                    $userprefdescribed = true;
                    echo "     " . $item->get_name() . " : " . get_string($item->get_summary(), $component) . "\n";
                }
            }
            if (!$userprefdescribed) {
                echo "!!! User preference found, but was not described in metadata\n";
                exit(1);
            }
        }
    }
}

echo "\n\n== Done ==\n";

function check_implements($component, $interface) {
    $manager = new \core_privacy\manager();
    $rc = new \ReflectionClass(\core_privacy\manager::class);
    $rcm = $rc->getMethod('component_implements');
    $rcm->setAccessible(true);
    return $rcm->invoke($manager, $component, $interface);
}
