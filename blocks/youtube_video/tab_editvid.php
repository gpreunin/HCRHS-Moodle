</form>
<?php
	/* Youtube Video Playlist Moodle Block
	 * Paul Holden, Greenhead College, 31st July 2007
	 * http://gcmoodle.greenhead.ac.uk/external/youtube/
	 * -------------------------------------------------
	 * tab_editvid.php
	 * Tab containing video configuration editing form
	 */

	$vid = optional_param('vid', 0, PARAM_INTEGER);

	echo ('<form method="post" action="' . $CFG->wwwroot . '/blocks/youtube_video/action.php">');

	$hidden = array('action' => 'save',
			'cid' => $this->instance->pageid,
			'vid' => $vid,
			'instanceid' => $this->instance->id,
			'sesskey' => $USER->sesskey);

	foreach ($hidden as $name => $value) {
		echo('<input type="hidden" name="' . $name . '" value="' . $value . '" />');
	}

	$video = get_record('block_youtube_video', 'id', $vid);

	$table = new Object();
	$table->head = array(get_string('config_param', 'block_youtube_video'), get_string('config_value', 'block_youtube_video'));
	$table->tablealign = 'left';
	$table->width = '*';

	$table->data[] = array(get_string('edit_title', 'block_youtube_video'), '<input type="text" name="title" size="64" value="' . (!$video ? '' : $video->title) . '" maxlength="64" />');
	$table->data[] = array(get_string('description'), '<textarea name="description" rows="6" cols="49">' . (!$video ? '' : $video->description) . '</textarea>');
	$table->data[] = array('URL', '<input type="text" name="url" size="100" value="' . (!$video ? '' : $video->url) . '" maxlength="255" />');
	$table->data[] = array(get_string('edit_shared', 'block_youtube_video'), choose_from_menu_yesno('shared', (!$video ? 0 : $video->shared), '', true));
	$table->data[] = array('&nbsp;', '<input type="submit" value="' . get_string('savechanges') . '" />');

	print_table($table);

?>
</form>
