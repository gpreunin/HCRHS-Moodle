<?php  

/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * @version 1.03 - january 2008
 *
 * @copyright (c) 2008 INWICAST
 *
 * @license http://www.gnu.org/copyleft/gpl.html (GPL) GENERAL PUBLIC LICENSE
 *
 * @see http://www.inwicast.com
 *
 * @package INWICAST
 *
 * @author INWICAST Team <dev@inwicast.com>
 *
 */
	if ( isset($_REQUEST['cmd']) ) $cmd = $_REQUEST['cmd'];
	
	$mediaref = NULL;
	
	if ( isset($_REQUEST['mediaRef']) ) $mediaref = $_REQUEST['mediaRef'];

	if (empty($course)) {
	        error('You cannot call this script in that way');
	}

	$inactive = NULL;
	$activetwo = NULL;
	$toprow = array();

	$userisateacher = isteacher($course->id);

	if ($mediaref)
	{

		$currenttab = $cmd;
		$mediaInfo = getMediaInfo($mediaref);

		
		if (!mediaIsLive($mediaInfo->status))
		{
			if ($cmd == "update_media") $currenttab = "edit_media";

		    	$toprow[] = new tabobject('show_media', $CFG->wwwroot.'/blocks/inwicast/view.php?id='
				.$course->id.'&cmd=show_media&mediaRef='.$mediaref, get_string('view','block_inwicast'));
			if ($userisateacher)
			{
				$toprow[] = new tabobject('edit_media', $CFG->wwwroot.'/blocks/inwicast/view.php?id='
					.$course->id.'&cmd=edit_media&mediaRef='.$mediaref, get_string('edit', 'block_inwicast'));

				if ($mediaInfo->media_type == "mediamap")
					$toprow[] = new tabobject('publish_media_on_map', $CFG->wwwroot
						.'/blocks/inwicast/view.php?id='.$course->id.'&cmd=publish_media_on_map&mediaRef='
						.$mediaref, get_string('publish_media_on_map', 'block_inwicast'));
			}
			
			if (isset($CFG->block_inwicast_media_sharing) || $userisateacher)
			{
				if ($CFG->block_inwicast_media_sharing || $userisateacher)
				{
				    $toprow[] = new tabobject('share_media', $CFG->wwwroot.'/blocks/inwicast/view.php?id='
						.$course->id.'&cmd=share_media&mediaRef='.$mediaref, get_string('share','block_inwicast'));
				    
				}
			}

			if ($mediaInfo->is_downloadable || $userisateacher)
			{
				$toprow[] = new tabobject('download_media', $CFG->wwwroot.'/blocks/inwicast/view.php?id='
					.$course->id.'&cmd=download_media&mediaRef='.$mediaref, get_string('download', 'block_inwicast'));
			}
		}
	}
	else
	{
	    $toprow[] = new tabobject('videos', $CFG->wwwroot.'/blocks/inwicast/index.php?id='.$course->id.'&cmd=show_medias', get_string('course_videos','block_inwicast'));


		if ($userisateacher)
		{

			$toprow[] = new tabobject('videoupload', $CFG->wwwroot
				.'/blocks/inwicast/index.php?id='.$course->id
				.'&cmd=upload_form', get_string('uploadavideo', 'block_inwicast'));
		    $toprow[] = new tabobject('videolink', $CFG->wwwroot
				.'/blocks/inwicast/index.php?id='.$course->id
				.'&cmd=link_media_form', get_string('linkvideo','block_inwicast'));
			
			if (isset($CFG->block_inwicast_gmap_enabled))
			{
				if ($CFG->block_inwicast_gmap_enabled)
				$toprow[] = new tabobject('mediamap', $CFG->wwwroot
				.'/blocks/inwicast/index.php?id='.$course->id
				.'&cmd=create_mediamap_form', get_string('mediamap_add','block_inwicast'));
			}
			
	    	$toprow[] = new tabobject('unpublished_videos', $CFG->wwwroot
				.'/blocks/inwicast/index.php?id='.$course->id
				.'&cmd=show_unpublished_medias', get_string('unpublished_videos','block_inwicast'));

		}
	}

	$tabs = array($toprow);

    print_tabs($tabs, $currenttab);

?>
