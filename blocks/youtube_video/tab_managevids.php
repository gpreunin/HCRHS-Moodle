<?php
	/* Youtube Video Playlist Moodle Block
	 * Paul Holden, Greenhead College, 31st July 2007
	 * http://gcmoodle.greenhead.ac.uk/external/youtube/
	 * -------------------------------------------------
	 * tab_managevids.php
	 * Tab containing link for viewing/editing/deleting videos of a course
	 */

	function print_action($action, $url, $target = '_self') {
		global $CFG;
		$imgalt = get_string($action);
		return "<a href=\"$url\" target=\"$target\"><img src=\"$CFG->pixpath/t/$action.gif\" alt=\"$imgalt\" title=\"$imgalt\" /></a> ";
	}

	$page = page_create_object($this->instance->pagetype, $this->instance->pageid);
	$urledit = $page->url_get_full(array('instanceid' => $this->instance->id, 'sesskey' => $USER->sesskey, 'blockaction' => 'config', 'action' => 'editvid', 'vid' => ''));
	$urldelete = sprintf('%s/blocks/youtube_video/action.php?cid=%d&instanceid=%d&sesskey=%s&action=delete&vid=', $CFG->wwwroot, $this->instance->pageid, $this->instance->id, $USER->sesskey);

	$videos = get_records('block_youtube_video', 'courseid', $this->instance->pageid, 'title ASC');

	if (!$videos) {
		print_string('warning_no_videos', 'block_youtube_video');
	} else {
		$table = new Object();
		$table->head = array(get_string('edit_title', 'block_youtube_video'), get_string('edit_shared', 'block_youtube_video'), get_string('action'));
		$table->align = array('left', 'left');
		$table->tablealign = 'left';
		$table->width = '*';

		$yesno = array(get_string('no'), get_string('yes'));

		foreach ($videos as $video) {
			$action = print_action('preview', $video->url, '_blank') .
				  print_action('edit', $urledit . $video->id) .
				  print_action('delete', $urldelete . $video->id);
			$table->data[] = array($video->title, $yesno[$video->shared], $action);
		}

		print_table($table);
	}

?>
