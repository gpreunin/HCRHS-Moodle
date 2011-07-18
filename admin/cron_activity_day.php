<?php // $Id$

/// This script looks through all the module directories for cron.php files
/// and runs them.  These files can contain cleanup functions, email functions
/// or anything that needs to be run on a regular basis.
///
/// This file is best run from cron on the host system (ie outside PHP).
/// The script can either be invoked via the web server or via a standalone
/// version of PHP compiled for CGI.
///
/// eg   wget -q -O /dev/null 'http://moodle.somewhere.edu/admin/cron.php'
/// or   php /web/moodle/admin/cron.php 
    set_time_limit(0);
    $starttime = microtime();

/// The following is a hack necessary to allow this script to work well 
/// from the command line.

    define('FULLME', 'cron');


/// Do not set moodle cookie because we do not need it here, it is better to emulate session
    $nomoodlecookie = true;

/// The current directory in PHP version 4.3.0 and above isn't necessarily the
/// directory of the script when run from the command line. The require_once()
/// would fail, so we'll have to chdir()

    if (!isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['argv'][0])) {
        chdir(dirname($_SERVER['argv'][0]));
    }

    require_once(dirname(__FILE__) . '/../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/gradelib.php');

/// Extra debugging (set in config.php)
    if (!empty($CFG->showcronsql)) {
        $db->debug = true;
    }
    if (!empty($CFG->showcrondebugging)) {
        $CFG->debug = DEBUG_DEVELOPER;
        $CFG->debugdisplay = true;
    }

/// extra safety
    @session_write_close();

/// check if execution allowed
    if (isset($_SERVER['REMOTE_ADDR'])) { // if the script is accessed via the web.
        if (!empty($CFG->cronclionly)) { 
            // This script can only be run via the cli.
            print_error('cronerrorclionly', 'admin');
            exit;
        }
        // This script is being called via the web, so check the password if there is one.
        if (!empty($CFG->cronremotepassword)) {
            $pass = optional_param('password', '', PARAM_RAW);
            if($pass != $CFG->cronremotepassword) {
                // wrong password.
                print_error('cronerrorpassword', 'admin'); 
                exit;
            }
        }
    }


/// emulate normal session
    $SESSION = new object();
    $USER = get_admin();      /// Temporarily, to provide environment for this script

/// ignore admins timezone, language and locale - use site deafult instead!
    $USER->timezone = $CFG->timezone;
    $USER->lang = '';
    $USER->theme = '';
    course_setup(SITEID);

/// send mime type and encoding
    if (check_browser_version('MSIE')) {
        //ugly IE hack to work around downloading instead of viewing
        @header('Content-Type: text/html; charset=utf-8');
        echo "<xmp>"; //<pre> is not good enough for us here
    } else {
        //send proper plaintext header
        @header('Content-Type: text/plain; charset=utf-8');
    }

/// no more headers and buffers
    while(@ob_end_flush());

/// increase memory limit (PHP 5.2 does different calculation, we need more memory now)
    @raise_memory_limit('128M');

/// Start output log

    $timenow  = time();

    mtrace("Server Time: ".date('r',$timenow)."\n\n");

/// Run all cron jobs for each module

    mtrace("Starting activity modules");
    get_mailer('buffer');
    if ($mods = get_records_select("modules", "cron > 0 AND (($timenow - lastcron) > cron) AND visible = 1 ")) {
        foreach ($mods as $mod) {
            $libfile = "$CFG->dirroot/mod/$mod->name/lib.php";
            if (file_exists($libfile)) {
                include_once($libfile);
                $cron_function = $mod->name."_cron";
                if (function_exists($cron_function)) {
                    mtrace("Processing module function $cron_function ...", '');
                    $pre_dbqueries = null;
                    if (!empty($PERF->dbqueries)) {
                        $pre_dbqueries = $PERF->dbqueries;
                        $pre_time      = microtime(1);
                    }
                    if ($cron_function()) {
                        if (! set_field("modules", "lastcron", $timenow, "id", $mod->id)) {
                            mtrace("Error: could not update timestamp for $mod->fullname");
                        }
                    }
                    if (isset($pre_dbqueries)) {
                        mtrace("... used " . ($PERF->dbqueries - $pre_dbqueries) . " dbqueries");
                        mtrace("... used " . (microtime(1) - $pre_time) . " seconds");
                    }
                /// Reset possible changes by modules to time_limit. MDL-11597
                    @set_time_limit(0);
                    mtrace("done.");
                }
            }
        }
    }
    get_mailer('close');
    mtrace("Finished activity modules");

    mtrace('Starting main gradebook job ...');
    grade_cron();
    mtrace('done.');

    mtrace('Starting processing the event queue...');
    events_cron();
    mtrace('done.');

    //Unset session variables and destroy it
    @session_unset();
    @session_destroy();

    mtrace("Cron script completed correctly");

    $difftime = microtime_diff($starttime, microtime());
    mtrace("Execution took ".$difftime." seconds"); 

/// finish the IE hack
    if (check_browser_version('MSIE')) {
        echo "</xmp>";
    }

?>
