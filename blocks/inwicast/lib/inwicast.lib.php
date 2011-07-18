<?php

/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * This script is the main lib of the mediacenter module
 * It was not originally designed for Moodle
 *
 * @version 1.03- may 2008
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

$inwicastpermissions = 0755;



/**
 * This function adds a media in database
 *
 */ 

function createMedia($mediaref, $mediatitle, $mediadesc, $mediauser, $mediatags, $mediatype, $mediaurl, $previewurl,$mediaauthor, $mediacopyright, $embeddedPlayer)
{

	if (!mediaAlreadyExists($mediaref))
	{
		$record->media_ref = $mediaref;
		$record->title = $mediatitle;
		$record->user_name = $mediauser;
		$record->media_type = $mediatype;
		$record->description = $mediadesc;
		$record->media_url = $mediaurl;
		$record->preview_url = $previewurl;
		$record->tags = $mediatags;
		$record->date_created = date("Y/m/d");
		$record->author = $mediaauthor;
		$record->copyright = $mediacopyright;
		$record->embedded_player = $embeddedPlayer;
		$record->width = getDefaultMediaWidth($mediatype, $mediaref);
		$record->height = getDefaultMediaHeight($mediatype, $mediaref);

		$newrecord = insert_record("inwi_medias", addslashes_object($record));

		if ($newrecord)
			return true;
		else
			return false;
	}
	else
		return false;
}

/**
 * This function adds a mediamap (GoogleMap) in database
 *
 */ 

function createMediaMap($mediaref, $mediatitle, $mediadesc, $mediauser, $mediatags, $mediatype, $mediaurl, $previewurl,$mediaauthor, $mediacopyright, $maplng, $maplat, $mapzoom, $maptype)
{

	if (!mediaAlreadyExists($mediaref))
	{
		$record->media_ref = $mediaref;
		$record->title = $mediatitle;
		$record->user_name = $mediauser;
		$record->media_type = $mediatype;
		$record->description = $mediadesc;
		$record->media_url = $mediaurl;
		$record->preview_url = $previewurl;
		$record->tags = $mediatags;
		$record->date_created = date("Y/m/d");
		$record->author = $mediaauthor;
		$record->copyright = $mediacopyright;
		$record->width = 640;
		$record->height = 480;
		$record->map_lat = $maplat;
		$record->map_lng = $maplng;
		$record->map_zoom = $mapzoom;
		$record->map_type = $maptype;

		$newrecord = insert_record("inwi_medias", addslashes_object($record));

		if ($newrecord)
			return true;
		else
			return false;
	}
	else
		return false;
}

/**
 * This function deletes a media
 *
 * @param string $mediaRef	media ID
 *
 * @return boolean TRUE		if success
 * 		   FALSE	otherwise
 *
 */ 


function deleteMedia($mediaref)
{
	
	if(delete_records("inwi_medias", "media_ref", $mediaref)) 
	{
		if(delete_records("inwi_cours_medias", "media_ref", $mediaref)) 
			return true;
		else
			return false;
	} 
	else
		return false;

}

/**
 * This function unpublishs a media from a course
 *
 * @param string courseCode	course id
 * @param string mediaRef	media code
 *
 * @return boolean TRUE		if success
 * 		   FALSE	otherwise
 */ 

function deleteMediaFromCourse($courseCode, $mediaRef)
{

	if(delete_records("inwi_cours_medias", "media_ref", $mediaRef, "code_cours", $courseCode)) 
	{
		checkIfMediaPublished($mediaRef);
		return true;
	}
	else
		return false;

}

/**
 * This function adds (publish) a media to a course
 *
 * @param string courseCode
 * @param string mediaRef
 *
 * @return boolean TRUE		if success
 * 		   FALSE	otherwise
 */ 

function addMediaToCourse($courseCode, $mediaRef)
{

	if (!mediaAlreadyExistsInCourse($courseCode, $mediaRef))
	{
		$record->media_ref = $mediaRef;
		$record->code_cours = $courseCode;

		$newrecord = insert_record("inwi_cours_medias", addslashes_object($record));

		if ($newrecord)
		{
			setMediaPublished($mediaRef, 1);
			return true;
		}
		else
			return false;
	}
	else
		return false;

}

/**
 * This function checks if media already exists in the course
 *
 * @param string $courseCode
* @param string $mediaRef
 *
 * @return boolean TRUE		if already exists
 *		   FALSE	otherwise	
 *
 */ 

function mediaAlreadyExistsInCourse($courseCode, $mediaRef)
{
	global $CFG;
	
	$tblCoursMedias = $CFG->prefix."inwi_cours_medias";

	$mysql_query = "SELECT id "
		. "FROM $tblCoursMedias "
		. "WHERE media_ref = '$mediaRef' "
		. "AND code_cours='$courseCode'";
	
    $medialist = get_records_sql($mysql_query);		
	
	if ($medialist)
		return true;
	else
		return false;
		
}

/**
 * This function updates media info
 *
 * @param array $mediaInfo
 *
 * @return boolean TRUE		if success
 * 		   FALSE	otherwise
 */ 

function updateMedia($mediaInfo)
{

	$mediatitle = $_REQUEST['mediaTitle'];
	$mediadesc = $_REQUEST['mediaDesc'];
	$mediaRef = $mediaInfo->media_ref;

	$record->id = $mediaInfo->id;
	$record->title = $mediatitle;
	$record->description = $mediadesc;

	if (isset( $_REQUEST['mediaTags']))
		$record->tags = $_REQUEST['mediaTags'];

	if (isset( $_REQUEST['mediaAuthor']))
		$record->author = $_REQUEST['mediaAuthor'];

	if (isset( $_REQUEST['embeddedPlayer']))
		$record->embedded_player = $_REQUEST['embeddedPlayer'];

	if (isset( $_REQUEST['previewUrl'])) 
		$record->preview_url = $_REQUEST['previewUrl'];

	if (isset( $_REQUEST['mediaWidth'])) 
		$record->width = $_REQUEST['mediaWidth'];

	if (isset( $_REQUEST['mediaHeight'])) 
		$record->height = $_REQUEST['mediaHeight'];
		
	if (isset( $_REQUEST['mediaDownloadable'])) 
		$record->is_downloadable = true;
	else
		$record->is_downloadable = false;
		
	// Changing the preview image

	if (isset($_FILES['uploadimagefile']['tmp_name']))
	{

		$tmp_file = $_FILES['uploadimagefile']['tmp_name'];

	    	if( is_uploaded_file($tmp_file) )
		{

			$file_format = strtolower($_FILES['uploadimagefile']['name']);
			$is_format_ok = true;

			if (strstr($file_format, '.png'))
				$extension = ".png";
			elseif (strstr($file_format, '.jpg'))
				$extension = ".jpg";
			elseif (strstr($file_format, '.jpeg'))
				$extension = ".jpeg";
			else
				$is_format_ok = false;

			if ($is_format_ok)
			{
				$mediapath = getMediaSysDir($mediaRef); 
				$previewname = $mediaRef . $extension;

				if( !move_uploaded_file($tmp_file, $mediapath . $previewname) )
					echo get_string("UNABLE_TO_MOVE_FILE","block_inwicast");
				else
					$record->preview_url = getMediaContentUrl($mediaRef, "/$previewname");
			}
			else
				echo get_string("FILE_FORMAT_NOT_ALLOWED","block_inwicast");
		}
	}

	// Save in database

	if (update_record("inwi_medias", addslashes_object($record)))
		return true;
	else
		return false;

}


function updateMediaPreview($mediaref, $previewurl)
{
	$mediaInfo = getMediaInfo($mediaref);

	if ($mediaInfo)
	{
		$record->id = $mediaInfo->id;
		$record->preview_url = $previewurl;

		if (update_record("inwi_medias", addslashes_object($record)))
			return true;
		else
			return false;
	}

}

function setMediaStatus($mediaref, $status)
{
	$mediaInfo = getMediaInfo($mediaref);

	if ($mediaInfo)
	{
		$record->id = $mediaInfo->id;
		$record->status = $status;

		if (update_record("inwi_medias", addslashes_object($record)))
			return true;
		else
			return false;
	}

}

/**
 * This function increases the number of times the media has been viewed
 *
 * @param array $mediaInfo
 *
 * @return boolean TRUE		if success
 * 		   FALSE	otherwise
 */ 

function mediaViewed($mediaInfo)
{

	$record->id = $mediaInfo->id;
	$record->viewed = $mediaInfo->viewed + 1;

	if (update_record("inwi_medias", addslashes_object($record)))
		return true;
	else
		return false;

}

function mediaIsLive($status)
{

	if ($status == 2)
	{
		return true;
	}
	else
		return false;
}

/**
 * This function retrieves media info from the database
 *
 * @param string $mediaRef
 *
 * @return array $mediaInfo
 *
 */ 

function getMediaInfo($mediaRef) {


	$mediaInfo = get_record("inwi_medias", "media_ref", $mediaRef);
	return $mediaInfo;
}

/**
 * This function checks if the media already exists in the database
 *
 * @param string $mediaRef
 *
 * @return boolean TRUE		if already exists
 *		   FALSE	otherwise	
 *
 */ 

function mediaAlreadyExists($mediaRef)
{
	$mediaInfo = getMediaInfo($mediaRef);

	if ($mediaInfo)
		return true;
	else
		return false;

}

/**
 * This function gets course medias from the database
 *
 * @param string $courseid	course code
 * @param string $filter	search filter
 *
 * @return array $mediaList
 *
 */ 

function getCourseMedias($courseid, $filter) {

	global $CFG;

	$tblMedias = $CFG->prefix."inwi_medias";
	$tblCoursMedias = $CFG->prefix."inwi_cours_medias";
	$tblUser = $CFG->prefix."user";

	if ($filter == "visible")
	{
		$mysql_query = "SELECT a.id, a.media_ref, a.title, a.media_url, a.preview_url, "
		. "a.user_name, a.viewed, a.status, c.visible, "
		. "a.votes, Date_Format(a.date_created, '%d.%m.%Y') media_date, "
		. "a.description, b.lastname, b.firstname, b.email "
		. "FROM $tblMedias a, $tblUser b, $tblCoursMedias c "
		. "WHERE a.media_ref = c.media_ref "
		. "AND c.code_cours='" . $courseid . "' "
		. "AND c.visible='1' "
		. "AND a.user_name = b.username ORDER BY a.id DESC"; 
	}
	else
		$mysql_query = "SELECT a.id, a.media_ref, a.title, a.media_url, a.preview_url, "
		. "a.user_name, a.viewed, a.status, c.visible, a.votes, Date_Format(a.date_created, '%d.%m.%Y') media_date, "
		. "a.description, b.lastname, b.firstname, b.email "
		. "FROM $tblMedias a, $tblUser b, $tblCoursMedias c "
		. "WHERE a.media_ref = c.media_ref "
		. "AND c.code_cours='" . $courseid . "' "
		. "AND a.user_name = b.username ORDER BY a.id DESC";
		
    	$medialist = get_records_sql($mysql_query);

	return $medialist;

}

/**
 * This function gets user medias from the database
 *
 * @param string $username	user name
 * @param string $filter	search filter
 *
 * @return array $mediaList
 *
 */ 

function getUserMedias($username, $filter) {

	global $CFG;

	$tblMedias = $CFG->prefix."inwi_medias";

	$query = "SELECT id, media_ref, title, media_url, preview_url, user_name "
	. "FROM $tblMedias WHERE user_name = '$username' ";
    	$medialist = get_records_sql($query);

	return $medialist;

}

/**
 * This function unpublished videos
 *
 * @return array $medialist
 *
 */ 

function getUnpublishedMedias() {

	global $CFG, $USER;

	$tblUser = $CFG->prefix."user";

	$tblMedias = $CFG->prefix."inwi_medias";

	$mysql_query = "SELECT a.id, a.media_ref, a.title, a.media_url, a.preview_url, "
		. "a.user_name, a.viewed, a.votes, Date_Format(a.date_created, '%d.%m.%Y') media_date, "
		. "a.description, b.lastname, b.firstname, b.email "
		. "FROM $tblMedias a, $tblUser b "
		. "WHERE a.user_name = '$USER->username' "
		. "AND a.status = '0' "
		. "AND a.user_name = b.username ORDER BY a.id DESC";

    	$medialist = get_records_sql($mysql_query);

	return $medialist;

}

/**
 * This function returns the last published media of a course
 *
 * @param string $courseid	course code
 *
 * @return array $mediaInfo
 *
 */ 

function getCourseLastMedia($courseid) {

	global $CFG;

	$tblCoursMedias = $CFG->prefix."inwi_cours_medias";

	$mysql_query = "SELECT MAX(media_ref) media_ref "
		. "FROM $tblCoursMedias "
		. "WHERE code_cours = '$courseid'";

    	$result = get_record_sql($mysql_query);

	if ($result)
		return getMediaInfo($result->media_ref);
	else
		return $result;
}

/**
 * This function checks if a media is already published in a course and sets flag if needed
 *
 * @param string $mediaref	media code
 *
 *
 */ 
function checkIfMediaPublished($mediaref) {

    	$result = count_records("inwi_cours_medias", "media_ref", $mediaref);

	if ($result == 0)
		setMediaPublished($mediaref, 0);
}

/**
 * This function marks if a video is published in at least one course
 *
 * @param string $mediaref	media code
 * @param int $status		0 if not published , 1 otherwise
 *
 * @return array $mediaInfo
 *
 */ 

function setMediaPublished($mediaref, $status)
{

	$mediaInfo = getMediaInfo($mediaref);

	if ($mediaInfo)
	{
		$record->id = $mediaInfo->id;
		$record->status = $status;

		if (update_record("inwi_medias", addslashes_object($record)))
			return true;
		else
			return false;
	}
}


function getUserCourses($myuserid, $iscoursemanager)
{

	$courseList = get_my_courses($myuserid);
	return $courseList;
}

function getMediaCourses($mediaref)
{
	$tblCours = get_conf("mainTblPrefix")."cours";
	$tblCoursMedias = get_conf("mainTblPrefix")."inwi_cours_medias";

	$mysql_query = "SELECT a.code code, a.intitule, b.visible FROM $tblCours a, "
			."$tblCoursMedias b WHERE a.code = b.code_cours "
			."AND b.media_ref = '" . $mediaref . "'";

	$courseList = claro_sql_query_fetch_all_cols($mysql_query);
	return $courseList;
}

function getCourseName($courseCode) {

	$tblCours = get_conf("mainTblPrefix")."cours";

	$result = mysql_query("SELECT intitule FROM $tblCours WHERE code='" . $courseCode . "'");
	if ($result) 
   		return mysql_result($result, 0);
}


function getUserId($username)
{

	$userInfo = get_record("user", "username", $username);

	if ($userInfo)
		return $userInfo->id;
	else
		return 0;

}

/**
 * This function checks user ident for INWICAST Publisher
 *
 * @param string $username	username to check
 * @param string $password	password to check md5 format
 *
 * @return string result
 *
 */ 

function checkUserIdent($username, $password) {


	$userInfo = get_record("user", "username", $username);

	if ($userInfo)
	{

		if ($userInfo->password != $password)         	
			return "WRONG_PASSWORD";
		else 
			return "USER_OK";
    	}
	else
		return "USER_NOT_FOUND";

}

function getUploadedVideoFormat($mediaRef)
{
	

	$fullpath = getMediaSysDir($mediaRef) . "multimedia/";

	if (file_exists($fullpath . $mediaRef . ".flv"))
		$format = "flv";
	elseif (file_exists($fullpath . $mediaRef . ".mp4"))
		$format = "mp4";
	elseif (file_exists($fullpath . $mediaRef . ".mov"))
		$format = "mov";
	elseif (file_exists($fullpath . $mediaRef . ".wmv"))
		$format = "wmv";
	elseif (file_exists($fullpath . $mediaRef . ".mp3"))
		$format = "mp3";
	elseif (file_exists($fullpath . $mediaRef . ".swf"))
		$format = "swf";
	elseif (file_exists($fullpath . $mediaRef . ".avi"))
		$format = "avi";
	elseif (file_exists($fullpath . $mediaRef . ".mpg"))
		$format = "mpg";
	elseif (file_exists($fullpath . $mediaRef . ".mpeg"))
		$format = "mpeg";
	elseif (file_exists($fullpath . $mediaRef . ".wma"))
		$format = "wma";
	else
		$format = "inconnu";


	return $format;

}

function getEmbeddedPlayer($mediaInfo)
{
	$mediaRef = $mediaInfo->media_ref;


	$playlisturl = getMediaContentUrl($mediaRef, "/playlist.xml"); //$mediaurl . "playlist.xml";


	$podtype = $mediaInfo->media_type;

	$player = "";

	if (mediaIsLive($mediaInfo->status))
	{
		$player = getInwicastLivePlayer($mediaInfo);
	}
	else
	{
		if ($podtype == "powerpodvideo")
			$player = getInwicastSlidePlayer($playlisturl, 960, 480, 240);
		elseif ($podtype == "podaudio")
			$player = getInwicastMP3Player($playlisturl,  $mediaInfo->width,  $mediaInfo->height);
		elseif ($podtype == "extvideo")
			$player =  stripslashes($mediaInfo->embedded_player);
		elseif ($podtype == "podvideo")
			$player = getInwicastPlayer($playlisturl,  $mediaInfo->width,  $mediaInfo->height);
		elseif ($podtype == "uploadedvideo")
		{
			$format = getUploadedVideoFormat($mediaRef);

			if ($format == "wmv" || $format == "wma")
				$player = getWindowsMediaPlayer($mediaRef, $format,  $mediaInfo->width,  $mediaInfo->height);
			elseif ($format == "mp4" || $format == "mov" || $format == "mpg" || $format == "mpeg")
				$player = getQuickTimePlayer($mediaRef, $format,  $mediaInfo->width,  $mediaInfo->height);
			elseif ($format == "flv")
				$player = getSimpleFlvPlayer($mediaRef, $mediaInfo->width,$mediaInfo->height);
			elseif ($format == "mp3")
				$player = getSimpleMp3Player($mediaRef, $mediaInfo->width, $mediaInfo->height);
			elseif ($format == "swf")
				$player = getSimpleSwfPlayer($mediaRef, $mediaInfo->width, $mediaInfo->height);
			elseif ($format == "avi")
				$player = getDivxPlayer($mediaRef, $format, $mediaInfo->width, $mediaInfo->height);
		}	
		elseif ($podtype == "mediamap")
		{
			$player = "<iframe src='". getInwicastWeb() 
				."maps/?mapmedia="
				.  $mediaInfo->media_ref
				. "&lat=" .  $mediaInfo->map_lat
				. "&lng=" .  $mediaInfo->map_lng
				. "&zoom=" .  $mediaInfo->map_zoom
				. "&mapwidth=" .  $mediaInfo->width
				. "&mapheight=" .  $mediaInfo->height
				. "&maptype=" .  $mediaInfo->map_type	. "'"
				. " width='" . $mediaInfo->width . "'" 
				. " height='" . $mediaInfo->height 
				. "' frameborder='0' scrolling='no'"
				." marginwidth='0' marginheight='0'></iframe>";
		}
		else
			$player = getInwicastPlayer($playlisturl, $mediaInfo->width, $mediaInfo->height);
	}

	return $player;

}


function getInwicastSlidePlayer($playlist, $playerwidth, $playerheight)
{

	$playerurl = getFlashSlidePlayerUrl();

	$videowidth = 320;
	$videoheight = 240;
	$slidewidth = 640;
	$slideheight = 480;

	if (isset($_REQUEST['playlistpos'])) 
	{
		$playlistpos = $_REQUEST['playlistpos'];

		if ($playlistpos == "right")
			$playerurl = getFlashSlideVerticalPlayerUrl();	

		$playerwidth = 480;
		$playerheight = 600;
		$videowidth = 320;
		$videoheight = 240;
		$slidewidth = 480;
		$slideheight = 360;
	}

	if (isset($_REQUEST['videoheight'])) $videoheight = $_REQUEST['videoheight'];
	if (isset($_REQUEST['videowidth'])) $videowidth = $_REQUEST['videowidth'];

	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	if (isset($_REQUEST['slideheight'])) $slideheight = $_REQUEST['slideheight'];
	if (isset($_REQUEST['slidewidth'])) $slidewidth = $_REQUEST['slidewidth'];


	$player =  "<object width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" >"
		. "<param name=\"movie\" value=\"" . $playerurl . "\" />"
		. "<param name=\"FlashVars\" value=\"file=" . $playlist
		. "&autostart=true&repeat=list&shuffle=false&bufferlength=3&enablejs=true"
		. "&displayheight=" . $videoheight
		. "&displaywidth=" . $videowidth
		. "&slideheight=" . $slideheight
		. "&slidewidth=" . $slidewidth
		. "&thumbsinplaylist=true\" />"
		. "<embed src=\"" . $playerurl . "\" type=\"application/x-shockwave-flash\" "
		. "FlashVars=\"file=$playlist&autostart=true&repeat=list&shuffle=false&bufferlength=3"
		. "&displayheight=" . $videoheight
		. "&displaywidth=" . $videowidth
		. "&slideheight=" . $slideheight
		. "&slidewidth=" . $slidewidth		. "&thumbsinplaylist=true\" "
		. "width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" allowfullscreen=\"false\" >"
		. "</embed>"
		. "</object>";

	return $player;
}

function getInwicastPlayer($playlist, $playerwidth, $displayheight)
{
	$playerheight = $displayheight;

	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	
	$player =  "<object width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" >"
		. "<param name=\"movie\" value=\"" . getFlashPlayerUrl() . "\" />"
		. "<param name=\"FlashVars\" value=\"file=" . $playlist
		. "&autostart=true&repeat=list&shuffle=false&bufferlength=2&enablejs=false"
		. "&displayheight=" . $displayheight . "&thumbsinplaylist=true\" />"
		. "<embed src=\"" . getFlashPlayerUrl() . "\" type=\"application/x-shockwave-flash\" "
		. "FlashVars=\"file=".$playlist."&autostart=true&repeat=list&shuffle=false&bufferlength=2"
		. "&displayheight=" . $displayheight . "&thumbsinplaylist=true\" "
		. "width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" allowfullscreen=\"true\" >"
		. "</embed>"
		. "</object>";

	return $player;
}

function getInwicastLivePlayer($mediaInfo)
{

	$mediatype = $mediaInfo->media_type;
	$streamingserver = $mediaInfo->media_url;
	$streamid = $mediaInfo->media_ref;


	$playerwidth = 320;
	$playerheight = 240;
	$displayheight = 240;
	$fullscreen = "true";

	if ($mediatype == "podscreen")
	{
		$playerwidth = 640;
		$playerheight = 480;
		$displayheight = $playerheight;
	}
	elseif ($mediatype == "powerpodaudio" )
	{
		$playerwidth = 640;
		$playerheight = 20;
		$displayheight = 0;
		$fullscreen = "false";
	}
	elseif ($mediatype == "podaudio" )
	{
		$playerwidth = 320;
		$playerheight = 20;
		$displayheight = 0;
		$fullscreen = "false";
	}

	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];
	
	$player =  "<object width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" >"
		. "<param name=\"movie\" value=\"" . getFlashPlayerUrl() . "\" />"
		. "<param name=\"FlashVars\" value=\"file=" . $streamingserver . "&id=" . $streamid
		. "&autostart=true&showdigits=false&bufferlength=2&usefullscreen=$fullscreen&enablejs=false"
		. "&displayheight=" . $displayheight . "\" />"
		. "<embed src=\"" . getFlashPlayerUrl() . "\" type=\"application/x-shockwave-flash\" "
		. "FlashVars=\"file=".$streamingserver
		. "&id=" .$streamid
		. "&autostart=true&showdigits=false&bufferlength=2&usefullscreen=$fullscreen"
		. "&displayheight=" . $displayheight . "\" "
		. "width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" allowfullscreen=\"true\" >"
		. "</embed>"
		. "</object>";

	return $player;

	
}

function getInwicastMP3Player($playlist, $playerwidth, $playerheight)
{

	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	$player =  "<object>"
		. "<param name=\"movie\" value=\"" . getFlashPlayerUrl() . "\" />"
		. "<param name=\"FlashVars\" value=\"file=" . $playlist
		. "&autostart=true&repeat=list&shuffle=false&bufferlength=3&enablejs=true\" />"
		. "<embed src=\"" . getFlashPlayerUrl() . "\" type=\"application/x-shockwave-flash\" "
		. "width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" allowfullscreen=\"false\""
		. "FlashVars=\"file=".$playlist
		. "&autostart=true&repeat=list&shuffle=false&bufferlength=3&enablejs=true\" >"
		. "</embed>"
		. "</object>";

	return $player;
}



function getWindowsMediaPlayer($mediaRef, $format, $playerwidth, $playerheight)
{

	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	$mediaurl =  getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef .  "." . $format);

	$player = "<OBJECT id=\"mediaPlayer\" width=\"$playerwidth\" height=\"$playerheight\""
	."classid=\"CLSID:22d6f312-b0f6-11d0-94ab-0080c74c7e95\""
	."codebase=\"http://activex.microsoft.com/activex/controls/ mplayer/en/nsmp2inf.cab#Version=5,1,52,701\""
	."standby=\"Loading Microsoft Windows Media Player components...\" type=\"application/x-oleobject\">"
	."<param name=\"fileName\" value=\"".$mediaurl."\">"
	."<param name=\"animationatStart\" value=\"1\">"
	."<param name=\"transparentatStart\" value=\"1\">"
	."<param name=\"autoStart\" value=\"1\">"
	."<param name=\"ShowControls\" value=\"1\">"
	."<param name=\"ShowDisplay\" value=\"0\">"
	."<param name=\"ShowStatusBar\" value=\"1\">"
	."<param name=\"loop\" value=\"0\">"
	."<EMBED type=\"application/x-mplayer2\""
	." pluginspage=\"http://microsoft.com/windows/mediaplayer/ en/download/\""
	." id=\"mediaPlayer\" name=\"mediaPlayer\" displaysize=\"4\" autosize=\"1\""
	." bgcolor=\"darkblue\" showcontrols=\"1\" showtracker=\"1\""
	." showdisplay=\"0\" showstatusbar=\"1\" videoborder3d=\"0\" width=\"$playerwidth\" height=\"$playerheight\""
	." src=\"".$mediaurl."\" autostart=\"1\" designtimesp=\"5311\" loop=\"0\">"
	."</EMBED>"
	."</OBJECT>";

	return $player;
}

function getQuickTimePlayer($mediaRef, $format, $playerwidth, $playerheight)
{
	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	$mediaurl = getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef . "." . $format); 

	$player = "<OBJECT classid=\"clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B\" width=\"$playerwidth\" height=\"$playerheight\" codebase=\"http://www.apple.com/qtactivex/qtplugin.cab\">"
		."<param name=\"src\" value=\"".$mediaurl."\">"
		."<param name=\"autoplay\" value=\"true\">"
		."<param name=\"controller\" value=\"true\">"
		."<param name=\"loop\" value=\"false\">"
		."<EMBED src=\"".$mediaurl ."\" width=\"$playerwidth\" height=\"$playerheight\" autoplay=\"true\" "
		. "controller=\"true\" loop=\"false\" bgcolor=\"#000000\" pluginspage=\"http://www.apple.com/quicktime/download/\">"
		. "</EMBED>"
		. "</OBJECT>";
	return $player;

}

function getSimpleMP3Player($mediaRef, $playerwidth, $playerheight)
{

	$playerwidth = 200;
	$playerheight = 20;
	
	$mediaurl =  getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef . ".mp3"); 

	$player = "<object type=\"application/x-shockwave-flash\" " 
			. " width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" " 
			. " data=\"" . getMp3PlayerUrl() ."?son=" . $mediaurl ."\" >"
			. " <param name=\"movie\" value=\"" . getMp3PlayerUrl() ."?son=" . $mediaurl ."\" /></object>";
	
	return $player;

}

function getSimpleFlvPlayer($mediaRef, $playerwidth, $displayheight)
{
	$playerheight = $displayheight + 20;

	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	$mediaurl =  getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef . ".flv"); 

	$player = "<object width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" >"
		. "<param name=\"movie\" value=\"" . getFlashPlayerUrl() . "\" />"
		. "<param name=\"FlashVars\" value=\"file=" . $mediaurl
		. "&autostart=true&shuffle=false&bufferlength=3&displayheight=" . $displayheight . "\" />"
		. "<embed src=\"" . getFlashPlayerUrl() . "\" type=\"application/x-shockwave-flash\" "
		. "FlashVars=\"file=".$mediaurl."&autostart=true&repeat=list&shuffle=false&bufferlength=3&displayheight=" . $displayheight . "\" "
		. "width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" allowfullscreen=\"true\" >"
		. "</embed>"
		. "</object>";

	return $player;
}


function getSimpleSwfPlayer($mediaRef, $playerwidth, $playerheight)
{

	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	$mediaurl = getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef . ".swf"); 

	$player = "<object width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" >"
		. "<param name=\"movie\" value=\"" . $mediaurl . "\" />"
		. "<embed src=\"" . $mediaurl . "\" type=\"application/x-shockwave-flash\" "
		. "width=\"" . $playerwidth ."\" height=\"" . $playerheight ."\" allowfullscreen=\"true\" >"
		. "</embed>"
		. "</object>";

	return $player;
}


function getDivxPlayer($mediaRef, $format, $playerwidth, $playerheight)
{
	if (isset($_REQUEST['playerheight'])) $playerheight = $_REQUEST['playerheight'];
	if (isset($_REQUEST['playerwidth'])) $playerwidth = $_REQUEST['playerwidth'];

	$mediaurl = getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef . "." . $format); 

	$player = "<object classid='clsid:67DABFBF-D0AB-41fa-9C46-CC0F21721616' width='$playerwidth' height='$playerheight' "
		. "codebase='http://go.divx.com/plugin/DivXBrowserPlugin ?.cab'>"
		. "<param name='src' value='$mediaurl' />"
		. "<embed type='video/divx' src='$mediaurl' width='$playerwidth' height='$playerheight' "
		. "pluginspage='http://go.divx.com/plugin/download/'></embed></object>";

	return $player;

}


function showYoutubePlayer($extmediaref)
{
echo "<object height='350' width='425'>"
	."<param name='movie' value='" . $extmediaref."'>"
	."<embed src='".$extmediaref."' type='application/x-shockwave-flash' height='350' width='425'>"
	."</object>";
}

function showDailymotionPlayer($extmedialink)
{
echo "<object width='425' height='335'>"
	."<param name='movie' value='".$extmedialink."'>"
	."</param><param name='allowfullscreen' value='true'></param>"
	."<embed src='".$extmedialink."' type='application/x-shockwave-flash' width='425' height='335' allowfullscreen='true'>"
	."</embed></object>";
}

function getEmbeddedVideo($videourl, $playerwidth, $playerheight)
{
	echo "<iframe src='" . $videourl . "' "
		."width='$playerwidth' height = '$playerheight' "
		. "Marginwidth='0' Marginheight='0' frameborder='0' scrolling='no' />\n";

}



function getMediaLink($mediaRef)
{

	return getSiteUrl() . "blocks/inwicast/medias?video=" . $mediaRef;
}


function getSiteUrl()
{
	global $CFG;
	return $CFG->wwwroot . "/";
}

function getSiteRootDir()
{
	global $CFG;
	return $CFG->dirroot . "/";
}

function getInwicastWeb()
{
	return getSiteUrl() . "blocks/inwicast/";
}

function getMediaContentUrl($mediaRef, $filename)
{
	return getMediasWebDir() . "index.php?file=/" . $mediaRef . $filename;
}


function getMediaWebDir($mediaRef)
{
	
	return getMediasWebDir() . "index.php?file=/". $mediaRef . "/";
	
}

function getMediasWebDir()
{
	return getSiteUrl() . "blocks/inwicast/medias/";
}

function getDataSysDir()
{
	global $CFG;
	return  $CFG->dataroot;
}

function getMediasSysDir()
{

	return getDataSysDir() . "/medias/";

}

function getInwicastSysDir()
{

	return getSiteRootDir() . "blocks/inwicast/";

}

function getMediaSysDir($mediaRef)
{
	return getDataSysDir() . "/medias/" . $mediaRef . "/";

}

function getFlashPlayerUrl()
{
	return getSiteUrl()  . "blocks/inwicast/mediaplayer/flvplayer.swf";
}

function getFlashSlidePlayerUrl()
{
	return getSiteUrl() . "blocks/inwicast/mediaplayer/flvslideplayer.swf";
}

function getFlashSlideVerticalPlayerUrl()
{
	return getSiteUrl() . "blocks/inwicast/mediaplayer/flvslideplayerv.swf";
}

function getMp3PlayerUrl()
{
	return getSiteUrl()  . "blocks/inwicast/mediaplayer/dewplayer.swf";
}

function showExternalMediaPlayer($extmediaurl)
{

	if (strstr($extmediaurl, "youtube.com/"))
		showYoutubePlayer($extmediaurl);
	else if (strstr($extmediaurl, "dailymotion.com/"))
		showDailymotionPlayer($extmediaurl);
	else
		echo get_lang("No suitable player for this video");


}

function getMediaPreviewUrl($mediaRef)
{

	$mediadir = getMediaSysDir($mediaRef);

	if (file_exists($mediadir . "preview.jpg"))
		$previewUrl = getMediaWebDir($mediaRef) . "preview.jpg";
	elseif (file_exists($mediadir . "preview.png"))
		$previewUrl = getMediaContentUrl($mediaRef, "/preview.png");
	else if (file_exists( $mediadir . "slides/preview.png"))
		$previewUrl = getMediaContentUrl($mediaRef, "/slides/preview.png");
	else if (file_exists($mediadir . "multimedia/0.png"))
		$previewUrl = getMediaContentUrl($mediaRef, "/multimedia/0.png");
	else
		$previewUrl = getDefaultMediaPreviewUrl();

	return $previewUrl;
}

function getDefaultMediaPreviewUrl()
{

	$previewUrl = getInwicastWeb() . "images/video.gif";

	return $previewUrl;
}

function getVideoMediaPreviewUrl()
{

	$previewUrl = getInwicastWeb() . "images/video.gif";

	return $previewUrl;
}

function getAudioMediaPreviewUrl()
{

	$previewUrl = getInwicastWeb() . "images/audio.gif";

	return $previewUrl;
}

function uploadMediasFromClient()
{

if (isset($_GET["userid"])) 
{	

	$myusername = $_GET["userid"];

	if (isset($_GET["task"])) $mytask = $_GET["task"];
	if (isset($_GET["podcastid"])) $podcastid = $_GET["podcastid"];
	if (isset($_GET["moduleid"])) $courseid = $_GET["moduleid"];

	// Upload files into course directory

	$mediapath = "../../courses/" . $courseid . "/document/" . $podcastid . "/";

	if (!file_exists($mediapath))
	{
		mkdir($mediapath, 0755);
		chmod($mediapath, 0755);
	}

	
	if ( isset($_GET['filetype']) ) $filetype = $_GET['filetype'];

	if ( $filetype == "slide" )
	{
		$content_dir = $mediapath . 'slides/'; 
	}
	elseif ($filetype == "media")
	{
		$content_dir = $mediapath . "multimedia/";
	}	
	elseif ($filetype == "subtitles")
	{
		$content_dir = $mediapath . "subtitles/";
	}
	else
		$content_dir = $mediapath;

	if (!file_exists($content_dir))
	{
		mkdir($content_dir, 0755);
		chmod($content_dir, 0755);
	}

	$tmp_file = $_FILES['uploadfile']['tmp_name'];
    	if( !is_uploaded_file($tmp_file) )
	{
		exit("FILE_NOT_FOUND");
	}

    	// check file extension
	$type_file = $_FILES['uploadfile']['type'];

	if ( !strstr($type_file, 'jpg') 
		&& !strstr($type_file, 'jpeg') 
		&& !strstr($type_file, 'png') 
		&& !strstr($type_file, 'bmp') 
		&& !strstr($type_file, 'gif')
		&& !strstr($type_file, 'wmv')
		&& !strstr($type_file, 'avi')
		&& !strstr($type_file, 'flv')
		&& !strstr($type_file, 'mp4')
		&& !strstr($type_file, 'mp3')
		&& !strstr($type_file, 'xml')
		&& !strstr($type_file, 'htm')
		&& !strstr($type_file, 'html'))
		exit("FILE_FORMAT_NOT_ALLOWED");

	 // move file in target directory
	 $name_file = $_FILES['uploadfile']['name'];


	    if( !move_uploaded_file($tmp_file, $content_dir . $name_file) )
	    {
	        exit("UNABLE_TO_MOVE_FILE");
	    }



    echo "UPLOAD_COMPLETED";
}
else
	return "NO_USER_ID";

}

function hasMP4FullVideo($mediaRef)
{
	$filename = getMediaSysDir($mediaRef) . "multimedia/" . $mediaRef . ".mp4";

	if (file_exists($filename))
		return true;
	else
		return false;
}

function hasMP3FullVideo($mediaRef)
{
	$filename = getMediaSysDir($mediaRef) . "multimedia/" .  $mediaRef . ".mp3";

	if (file_exists($filename))
		return true;
	else
		return false;

}

function hasMP4FullVideoWithSlides($mediaRef)
{
	$filename = getMediaSysDir($mediaRef) . "multimedia/" .  $mediaRef . "slides.mp4";

	if (file_exists($filename))
		return true;
	else
		return false;

}

function getDefaultMediaWidth($mediatype, $mediaref)
{
	global $CFG;
	
	if (isset($CFG->block_inwicast_default_video_width))
		$mediawidth = $CFG->block_inwicast_default_video_width;
	else
		$mediawidth = 640;

	if ($mediatype == "powerpodvideo")	// Slidecast video + slides from Inwicast Publisher
		$mediawidth = 960;
	elseif ($mediatype == "powerpodaudio") // Slidecast video + audio from Inwicast Publisher
		$mediawidth = 640;		
	elseif ($mediatype == "podaudio")
		$mediawidth = 320;	
	elseif ($mediatype == "mediamap")
		$mediawidth = 640;
	
	return $mediawidth;
	
}

function getDefaultMediaHeight($mediatype, $mediaref)
{
	global $CFG;

	if (isset($CFG->block_inwicast_default_video_height))
		$mediaheight = $CFG->block_inwicast_default_video_height;
	else
		$mediaheight = 480;
		
	if ($mediatype == "powerpodvideo")
		$mediaheight = 480;
	elseif ($mediatype == "powerpodaudio")
		$mediaheight = 480;	
	elseif ($mediatype == "podaudio")
		$mediaheight = 20;	
	elseif ($mediatype == "mediamap")
		$mediaheight = 480;
	elseif ($mediatype == "uploadedvideo")
	{
		if ( getUploadedVideoFormat($mediaref) == "mp3")
			$mediaheight = 20;		
	}

	return $mediaheight;

}


function downloadVideo($f_location,$f_name){
     	header ("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    	header('Content-Description: File Transfer');
    	header('Content-Type: application/octet-stream');
    	header('Content-Length: ' . filesize($f_location));
    	header('Content-Disposition: attachment; filename=' . basename($f_name));
    	readfile($f_location);
}

function showMessageBox($messagebox)
{
	echo "<table width=\"60%\" align='center'>\n";

	echo "<tr>\n"
		."<td align='center'>\n"
		. "<div id='messagebox' class='box generalbox' >\n"
		. $messagebox
		."</div>\n"
		."</td>\n"
		."</tr>\n";

	echo "</table>";

}

/**
 *
 * This function adds an uploaded media to the mediacenter
 *
 */


function uploadMedia()
{
	global $USER;

	if (isset($_REQUEST['mediaTitle'])) 
	{	

		$mediaRef = "MEDIA".date("ymdHis") . rand(100, 999);
		$mediatitle = $_REQUEST['mediaTitle'];
		if (isset($_REQUEST['mediaDesc'])) 
			$mediadesc = $_REQUEST['mediaDesc'];
		else 
			$mediadesc=get_string("No description");

		// Upload files into course directory

		$mediasdir = getMediasSysDir();
		
		if (!file_exists($mediasdir))
		{
			mkdir($mediasdir, 0755);
			chmod($mediasdir, 0755);
		}

		$mediapath = getMediaSysDir($mediaRef); 

		if (!file_exists($mediapath))
		{
			mkdir($mediapath, 0755);
			chmod($mediapath, 0755);
		}


		$content_dir = $mediapath . "multimedia/";

		if (!file_exists($content_dir))
		{
			mkdir($content_dir, 0755);
			chmod($content_dir, 0755);
		}

		$tmp_file = $_FILES['uploadfile']['tmp_name'];

	    	if( !is_uploaded_file($tmp_file) )
		{
			exit(get_string("FILE_NOT_FOUND","block_inwicast"));
		}

		$file_format = strtolower($_FILES['uploadfile']['name']);

		if (strstr($file_format, '.flv'))
			$extension = ".flv";
		elseif (strstr($file_format, '.mp4'))
			$extension = ".mp4";
		elseif (strstr($file_format, '.wmv'))
			$extension = ".wmv";
		elseif (strstr($file_format, '.mov'))
			$extension = ".mov";
		elseif (strstr($file_format, '.mpg'))
			$extension = ".mpg";
		elseif (strstr($file_format, '.avi'))
			$extension = ".avi";
		elseif (strstr($file_format, '.wma'))
			$extension = ".wma";
		elseif (strstr($file_format, '.swf'))
			$extension = ".swf";
		elseif (strstr($file_format, '.mpeg'))
			$extension = ".mpeg";
		elseif (strstr($file_format, '.mp3'))
			$extension = ".mp3";
		else
			exit(get_string("FILE_FORMAT_NOT_ALLOWED","block_inwicast"));

		$mediatype="uploadedvideo";
		$name_file = $mediaRef . $extension;

		if( !move_uploaded_file($tmp_file, $content_dir . $name_file) )
		{
		        exit(get_string("UNABLE_TO_MOVE_FILE","block_inwicast"));
		}

		if (isset($_REQUEST['mediaTags']))
			$mediatags = $_REQUEST['mediaTags'];
		else
			$mediatags = "";
			
		$mediapublic = "false";

		$mediaurl = getMediaWebDir($mediaRef);

		if ($extension == ".mp3")
			$previewurl = getAudioMediaPreviewUrl($mediaRef);
		else
			$previewurl = getVideoMediaPreviewUrl($mediaRef);
		
		if (isset($_REQUEST['mediaAuthor']))
			$mediaauthor = $_REQUEST['mediaAuthor'];
		else
			$mediaauthor = "";

		if (isset($_REQUEST['mediaCopyright']))
			$mediacopyright = $_REQUEST['mediaCopyright'];
		else
			$mediacopyright = "";

		// adds the media to DB

		createMedia($mediaRef, $mediatitle, $mediadesc, $USER->username, $mediatags, $mediatype, $mediaurl, $previewurl, $mediaauthor, $mediacopyright,"");

		if (isset($_REQUEST['courseid']))
		{
			$courseCode = $_REQUEST['courseid'];
			addMediaToCourse($courseCode, $mediaRef);
		}

	}
	else
	{
		return get_string("Missing information");
	}


}

///////////////////////////////////////////////////////////////////////////////

function getUserMaps($username)
{

	global $CFG;

	$tblMedias = $CFG->prefix."inwi_medias";

	$query = "SELECT id, media_ref, title, preview_url, description FROM $tblMedias WHERE user_name='$username' AND media_type='mediamap' ORDER BY title ASC";

    	return get_records_sql($query);

}

function getMapMedias($mediamapRef)
{
	global $CFG;

	$tblMedias = $CFG->prefix."inwi_medias";
	$tblMapMedias = $CFG->prefix."inwi_map_medias";

	$query = "SELECT a.id, a.media_ref, a.lat, a.lng, a.html, b.title FROM $tblMapMedias a, $tblMedias b "
			."WHERE a.media_ref = b.media_ref AND a.mediamap_ref='$mediamapRef'";
	
	return get_records_sql($query);

}

function getMapMarkerInfo($markerid)
{

	$markerInfo = get_record("inwi_map_medias", "id", $markerid);
	return $markerInfo;

}

function deleteMapMarker($markerid)
{

	if(delete_records("inwi_map_medias", "id", $markerid )) 
		return true;
	else
		return false;

}

function updateMapMarkerPos($markerid, $markerlng, $markerlat)
{

	$record->id = $markerid;
	$record->lng = $markerlng;
	$record->lat = $markerlat;

	if (update_record("inwi_map_medias", addslashes_object($record)))
		return true;
	else
		return false;

}

/**
 * This function addsa media to an exiting gmap
 *
 */ 

function addMediaToMap($mediamapref, $mediaref, $maplabel, $maphtml, $maplat, $maplng)
{

	$mediaInfo = getMediaInfo($mediaref);

	if ($mediaInfo)
	{

		$record->mediamap_ref = $mediamapref;
		$record->media_ref = $mediaref;
		$record->label = $maplabel;
		$record->html = $maphtml;
		$record->lat = $maplat;
		$record->lng = $maplng;
		$record->icon = "default";
		$record->visible = "1";
		$record->date_created = date("Y/m/d");

		return insert_record("inwi_map_medias", addslashes_object($record));
	}
	else
		return "0";
}

function addMediamap()
{

	global $currentUserName;

	if (isset($_REQUEST["mediaRef"]) && isset($_REQUEST['mediaTitle'])) 
	{	

		$mediaRef = $_REQUEST["mediaRef"];
		$mediatitle = $_REQUEST['mediaTitle'];
		if (isset($_REQUEST['mediaDesc'])) 
			$mediadesc = $_REQUEST['mediaDesc'];
		else 
			$mediadesc= "No description";

		// Upload files into course directory

		$mediasdir = getMediasSysDir();

		echo $mediasdir;

		// Create media directory		

		if (!file_exists($mediasdir))
		{
			mkdir($mediasdir, 0755);
			chmod($mediasdir, 0755);
		}

		$mediapath = getMediaSysDir($mediaRef); 

		if (!file_exists($mediapath))
		{
			mkdir($mediapath, 0755);
			chmod($mediapath, 0755);
		}

		$mediatype="mediamap";

		if (isset($_REQUEST['mediaTags']))
			$mediatags = $_REQUEST['mediaTags'];
		else
			$mediatags = "";
			
		$mediauser = $_REQUEST['userid'];
		$mediapublic = "false";

		$mediaurl = $_REQUEST["mediaUrl"];

		$maplat = $_REQUEST["lat"];
		$maplng = $_REQUEST["lng"];
		$mapzoom = $_REQUEST["zoom"];
		$maptype = $_REQUEST["maptype"];

		if (isset($_REQUEST["previewUrl"])) 
			$previewurl = $_REQUEST["previewUrl"];
		else
			$previewurl = getImagesWebDir() . "mediamap.png";

		if (isset($_REQUEST['mediaAuthor']))
			$mediaauthor = $_REQUEST['mediaAuthor'];
		else
			$mediaauthor = "";

		if (isset($_REQUEST['mediaCopyright']))
			$mediacopyright = $_REQUEST['mediaCopyright'];
		else
			$mediacopyright = "";

		if (isset($_REQUEST['mediaCat']))
			$mediacat = $_REQUEST['mediaCat'];
		else
			$mediacat = 0;

		createMediamap($mediaRef, utf8_encode($mediatitle), utf8_encode($mediadesc), $currentUserName,$mediatags, $mediatype,  $mediaurl, $previewurl, $mediaauthor, $mediacopyright, $embeddedPlayer, $maplat, $maplng, $mapzoom, $maptype);
	    	
	}
	else
	{
		return "Missing information";
	}
}

function getMapPublisher($mediaref)
{
	$mediaInfo = getMediaInfo($mediaref);

	$map = "<iframe src='". getInwicastWeb() 
		."maps/?mapmedia="
		.  $mediaInfo->media_ref
		. "&lat=" .  $mediaInfo->map_lat
		. "&lng=" .  $mediaInfo->map_lng
		. "&zoom=" .  $mediaInfo->map_zoom
		."&mapuser=" . $mediaInfo->user_name
		. "&mapmode=publishmedia"
		. "&mapwidth=" .  $mediaInfo->width
		. "&mapheight=" .  $mediaInfo->height
		. "&maptype=" .  $mediaInfo->map_type	. "'"
		. " width='" . $mediaInfo->width . "'" 
		. " height='" . $mediaInfo->height 
		. "' frameborder='0' scrolling='no'"
		." marginwidth='0' marginheight='0'></iframe>";

	return $map;
}

function getGoogleMapKey()
{
	global $CFG;

	if(isset($CFG->block_inwicast_gmap_key))
	return $CFG->block_inwicast_gmap_key;
	//"ABQIAAAA2Tkyk09rrGAmlCG0NGqJGBQYC0F0zp4Sl5HY4m778C-ap4RXaRQjKGIz0kYvl2wENCdQJYyYZmY-wQ";

}

function getWsImportUserVideos()
{

	global $currentUserName, $CFG;
	echo $CFG->block_inwicast_video_soap_enabled;
	
	if ($CFG->block_inwicast_video_soap_enabled)
	{
		$password = "123456";
		$filtername = "";
		$filtervalue = "";

		$paramList = array('user'   => $currentUserName,
	                       'password' => $password,  
	                       'filtername'    => $filtername, 
	                       'filtervalue'    => $filtervalue    );

		$urlDuService = $CFG->block_inwicast_video_import_servername . "/ws/index.php";
		
		echo $urlDuService;
		/*
		$client = new nuSoapclient($urlDuService );

		$mediaList = $client->call('get_user_media_list', $paramList);

	    if ( $client->getError() ) // SERVER CONNECTION FAILURE
	    {
	        echo '<center>'
	            .'<p><b>Soap error</b></p>'
	            .'<p>'
	            .$result['faultcode'  ].'<br />'
	            .$result['faultstring']
	            .'</p>';
	    }
	    elseif ( is_array($mediaList) )
	    {
				foreach ($mediaList as $media )
				{
					echo $media['title'];
					//if (!createMedia($media['media_ref'], $media['title'], $media['description'], $currentUserName, "", "extvideo", $media['media_url'], $media['preview_url'],"", "", ""))
						break;

				}

		    }
		*/
		}
	
}

function getWsMediaPlayer($mediaref)
{

	global $currentUserName, $CFG;

	if ($CFG->block_inwicast_video_soap_enabled)
	{
		$password = "123456";
		$filtername = "";
		$filtervalue = "";

		$paramList = array('user'   => $currentUserName,
	                       'password' => $password,  
	                       'mediaref'    => $mediaref );

		$urlDuService = get_conf("inwicast_server_address") . "/ws/wsgetmediaplayer.php";

		$client = new nuSoapclient($urlDuService );

		$result = $client->call('get_media_embedded_player', $paramList);

	    if ( $client->getError() ) // SERVER CONNECTION FAILURE
	    {
		echo '<center>'
		    .'<p><b>Soap error</b></p>'
		    .'<p>'
		    .$result['faultcode'  ].'<br />'
		    .$result['faultstring']
		    .'</p>';
	    }
	    else
	    {
			echo $result;

	    }
	}

}

/*------------------------------------------------------------------------------------------------------------------------------------------------------
							LIVECAST  FUNCTIONS
---------------------------------------------------------------------------------------------------------------------------------------------------------*/

function getMediaSlideDir($mediaref)
{
	return getMediaSysDir($mediaref) . "slides/";
}

function getMediaSlideWebDir($mediaref)
{
	return getMediaContentUrl($mediaref, "/slides/"); 
}

/*
	Get the current live media of a course (if any)
*/

function getLiveMedia($courseid)
{

	global $CFG;

	$tblMedias = $CFG->prefix."inwi_medias";
	$tblCoursMedias = $CFG->prefix."inwi_cours_medias";

	$mysql_query = "SELECT a.id, a.media_ref, a.title, a.media_url, a.preview_url, "
		. "a.user_name, a.viewed, a.votes, Date_Format(a.date_created, '%d.%m.%Y') media_date, "
		. "a.description "
		. "FROM $tblMedias a, $tblCoursMedias b "
		. "WHERE a.media_ref = b.media_ref "
		. "AND b.code_cours='" . $courseid . "' "
		. "AND a.status =  '2' ORDER BY a.id DESC";

	$medialist = get_records_sql($mysql_query);

	return $medialist;

	$media = null;
	
	if ($mediaList)
	{
		foreach ($mediaList as $media )
		{
			$media = getMediaInfo($media->media_ref);
			break;
		}
	}
	return $media;

}

function createMediaLiveDir($mediaref)
{
	global $inwicastpermissions;
	
	$mediasdir = getMediasSysDir();
			
	if (!file_exists($mediasdir))
	{
		mkdir($mediasdir, $inwicastpermissions);
		chmod($mediasdir, $inwicastpermissions);
	}

	$mediapath = $mediasdir . $mediaref . "/";

	if (!file_exists($mediapath))
	{
		mkdir($mediapath, $inwicastpermissions);
		chmod($mediapath, $inwicastpermissions);
	}
			
	$slidepath = $mediapath . "slides/";
			
	if (!file_exists($slidepath))
	{
		mkdir($slidepath, $inwicastpermissions);
		chmod($slidepath, $inwicastpermissions);
	}
}

function createMediaDir($mediaref)
{
	global $inwicastpermissions;
	
	$mediasdir = getMediasSysDir();
			
	if (!file_exists($mediasdir))
	{
		mkdir($mediasdir, $inwicastpermissions);
		chmod($mediasdir, $inwicastpermissions);
	}

	$mediapath = $mediasdir . $mediaref . "/";

	if (!file_exists($mediapath))
	{
		mkdir($mediapath, $inwicastpermissions);
		chmod($mediapath, $inwicastpermissions);
	}
			
}

function mediaHasSlide($mediatype)
{
	if ($mediatype == "powerpodvideo" || $mediatype == "powerpodaudio")
		return true;
	else
		return false;
}

function liveChatAddMessage($mediaref, $username, $message)
{
		global $inwicastpermissions;
		
		$filename = getMediaSysDir($mediaref) . "livechat.txt";
		
		$fh = fopen($filename, 'a');
		if ($fh > 0)
		{
			$stringdata = "<tr><td><b>" . $username . ": </b>" . $message . "</td></tr>"; 
			fwrite($fh, $stringdata);
			fclose($fh);
		}
}

function liveChatGetMessages($mediaref)
{
	$filename = getMediaSysDir($mediaref) . "livechat.txt";
	
	if (file_exists($filename))
		$livechat = file_get_contents($filename);
	else
		$livechat = "";

	return $livechat;
	
}

function liveGetParticipants($courseid)
{
	global $CFG;

	$timetoshowusers = 300; //Seconds default
    if (isset($CFG->block_online_users_timetosee)) {
        $timetoshowusers = $CFG->block_online_users_timetosee * 60;
    }
    $timefrom = 100 * floor((time()-$timetoshowusers) / 100); // Round to nearest 100 seconds for better query cache
	
	$select = "SELECT u.id, u.username, u.firstname, u.lastname, u.picture, max(ul.timeaccess) as lastaccess ";
    $from = "FROM {$CFG->prefix}user_lastaccess ul,
                          {$CFG->prefix}user u ";
    $where =  "WHERE ul.timeaccess > $timefrom
                       AND u.id = ul.userid
                       AND ul.courseid = $courseid ";
    $order = "ORDER BY lastaccess DESC ";
        
    $groupby = "GROUP BY u.id, u.username, u.firstname, u.lastname, u.picture ";
        
    $SQL = $select . $from . $where . $groupby . $order;
	
    if ($pusers = get_records_sql($SQL, 0, 100))
			return $pusers;

}

function getStreamingServerLive()
{
	global $CFG;

	if(isset($CFG->block_inwicast_streaming_server_live))
	return $CFG->block_inwicast_streaming_server_live;
}

function getStreamingServerVod()
{
	global $CFG;

	if(isset($CFG->block_inwicast_streaming_server_vod))
	return $CFG->block_inwicast_streaming_server_vod;
}

function getStreamingServerFtp()
{
	global $CFG;

	if(isset($CFG->block_inwicast_streaming_server_ftp))
	return $CFG->block_inwicast_streaming_server_ftp;
}

function getStreamingServerFtpLogin()
{
	global $CFG;

	if(isset($CFG->block_inwicast_streaming_server_ftp_login))
	return $CFG->block_inwicast_streaming_server_ftp_login;
}

function getStreamingServerFtpPassword()
{
	global $CFG;

	if(isset($CFG->block_inwicast_streaming_server_ftp_password))
	return $CFG->block_inwicast_streaming_server_ftp_password;
}

?>
