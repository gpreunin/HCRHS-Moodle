<?php
	/* Youtube Video Playlist Moodle Block
	 * Paul Holden, Greenhead College, 31st July 2007
	 * http://gcmoodle.greenhead.ac.uk/external/youtube/
	 * -------------------------------------------------
	 * action.php
	 * Performs add/edit/delete actions on video entries in the database
	 */

	require_once('../../config.php');

	$action = required_param('action', PARAM_TEXT);
	$cid = required_param('cid', PARAM_INTEGER);
	$vid = optional_param('vid', 0, PARAM_INTEGER);
	$instanceid = required_param('instanceid', PARAM_INTEGER);
	$sesskey = required_param('sesskey', PARAM_TEXT);

	$redirurl = sprintf('%s/course/view.php?id=%d&instanceid=%d&sesskey=%s&blockaction=config&action=', $CFG->wwwroot, $cid, $instanceid, $sesskey);

	// Check to see whether user has rights to update course, and validate session
	$context =  get_context_instance(CONTEXT_COURSE, $cid);
	if (!has_capability('moodle/course:update', $context) || !confirm_sesskey($sesskey)) {
		error(get_string('warning_not_teacher', 'block_youtube_video'));
	}

	switch ($action) {
	   case 'save' : // Add / edit video entry
		$title = required_param('title', PARAM_TEXT);
		$description = required_param('description', PARAM_TEXT);
		$url = required_param('url', PARAM_TEXT);
		$shared = required_param('shared', PARAM_INTEGER);
		if ($video = get_record('block_youtube_video', 'id', $vid, 'courseid', $cid)) {
			$video->title = $title;
			$video->description = $description;
			$video->url = $url;
			$video->shared = $shared;
			update_record('block_youtube_video', $video);
		        $redirurl .= 'configblock';                        
		} else {

                    if( empty($title) and empty($url) ){
                    $redirurl .= 'editvid' ;
                    }
                    else{
                    
			$video = new Object();
			$video->courseid = $cid;
			$video->title = $title;
			$video->description = $description;
			$video->url = $url;
			$video->shared = $shared;
			insert_record('block_youtube_video', $video);
                        $redirurl .= 'configblock';
                   }
		}
		break;
	   case 'delete' : // Remove video entry
		delete_records('block_youtube_video', 'id', $vid, 'courseid', $cid);
		$redirurl .= 'managevids';
		break;
	}

	redirect($redirurl);

?>
