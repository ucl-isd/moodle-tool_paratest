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
 * Version information.
 *
 * @package   tool_paratest
 * @copyright 2025 Andrew Hancox <andrew@opensourcelearning.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_paratest\local;

class lib {
    public static function confighook(): void {
        global $CFG;

        $testtoken = getenv('TEST_TOKEN');
        if (empty($testtoken)) {
            return;
        }

        if (!is_numeric($testtoken)) {
            throw new \Exception('Invalid test token: ' . $testtoken);
        }

        if ($testtoken < 0 || $testtoken > $CFG->phpunit_paraunit_processes) {
            throw new \Exception('Paratest has not been initialised for the right number of processes: ' . $testtoken);
        }

        $CFG->phpunit_prefix = "phpu{$testtoken}_";
        $CFG->phpunit_dataroot .= $testtoken;
    }

    public static function init(): void {
        global $CFG;

        if (!is_numeric($CFG->phpunit_paraunit_processes) || $CFG->phpunit_paraunit_processes < 2) {
            throw new \Exception('Invalid phpunit_paraunit_processes setting: ' . $CFG->phpunit_paraunit_processes);
        }

        $procs = [];
        for ($i = 0; $i < $CFG->phpunit_paraunit_processes; $i++) {
            $phpexec = empty($CFG->pathtophp) ? 'php' : $CFG->pathtophp;
            $pathtoinitscript = dirname(__FILE__) . "/../../../phpunit/cli/init.php";
            $procs[] = proc_open("export TEST_TOKEN=$i && $phpexec $pathtoinitscript", [STDIN, STDOUT, STDOUT], $unused);
        }

        echo $CFG->phpunit_paraunit_processes . " Threads started\n";

        $lastvalue = $CFG->phpunit_paraunit_processes;
        while (true) {
            $newvalue = self::checkallprocs($procs);

            if ($lastvalue === null || $newvalue < $lastvalue) {
                echo $newvalue . " Threads pending\n";
                $lastvalue = $newvalue;
            }

            if ($newvalue == 0) {
                break;
            }
        }

        echo "All threads completed\n";
    }

    private static function checkallprocs($procs) {
        $running = 0;
        foreach ($procs as $proc) {
            $status = proc_get_status($proc);

            if (!empty($status['running'])) {
                $running += 1;
            }
        }
        return $running;
    }
}
