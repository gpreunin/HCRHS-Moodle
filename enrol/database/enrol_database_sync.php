<?php // $Id$

    if(!empty($_SERVER['GATEWAY_INTERFACE'])){
        error_log("should not be called from apache!");
        exit;
    }
    error_reporting(E_ALL);
    
    require_once(dirname(dirname(dirname(__FILE__))).'/config.php'); // global moodle config file.
    require_once($CFG->dirroot.'/enrol/database/enrol.php');

    // ensure errors are well explained
    $CFG->debug=E_ALL;

    if (!is_enabled_enrol('database')) {
         error_log("Database enrol plugin not enabled!");
         die;
    }
    set_time_limit(0);
    @raise_memory_limit('128M');

    // update enrolments -- these handlers should autocreate courses if required
    $enrol = new enrolment_plugin_database();
    $enrol->cron();

?>
