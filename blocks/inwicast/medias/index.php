<?php 
/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * @version 1.03 - may 2008
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
require_once("../../../config.php");
require_once("../lib/inwicast.lib.php");

if (isset($_REQUEST["task"])) 
{
	$mytask = $_REQUEST["task"];

	if ($mytask == "getmodules")
	{
		if (isset($_REQUEST["userid"])) 
		{	
			$myusername = $_REQUEST["userid"];

			// Get user id from username

			$myuserid = getUserId($myusername);


			// Returns an xml file with user modules (courses for claroline)

			if ($myuserid)
			{
				$courseList = getUserCourses($myuserid, 1);

			   	$mymodules = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
			   	$mymodules .= "<modules>\n";

			        foreach ($courseList as $mycourse) {
					$mymodules .= "<module id=\"" . $mycourse->id . "\" name=\"" . $mycourse->fullname . "\" />\n"; 
			        }

			   	$mymodules .= "</modules>\n";
				echo $mymodules;
			}

		}
	}
	elseif ($mytask == "getstreaminginfo")
	{
		if (isset($_REQUEST["userid"])) 
		{	
			$myusername = $_REQUEST["userid"];
			
			$streamingserverlive = getStreamingServerLive();
			$streamingservervod = getStreamingServerVod();
			$streamingserverftp = getStreamingServerFtp();
			$streamingserverftplogin = getStreamingServerFtpLogin();
			$streamingserverftppassword =  getStreamingServerFtpPassword();

			$sinfo = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
			$sinfo .= "<streaminginfo>\n";
			$sinfo .= "<liveserver>$streamingserverlive<liveserver/>\n"; 
			$sinfo .= "<vodserver>$streamingservervod<vodserver/>\n"; 
			$sinfo .= "<ftpserver>$streamingserverftp<ftpserver/>\n"; 
			$sinfo .= "<ftplogin>$streamingserverftplogin<ftplogin/>\n"; 
			$sinfo .= "<ftppassword>$streamingserverftppassword<ftppassword/>\n"; 
		   	$sinfo .= "</streaminginfo>\n";
			echo $sinfo;
		}
		
	}
	elseif ($mytask == "uploadpodcast")
	{

		ini_set('memory_limit', 100000000); 
		set_time_limit(1200); 

		if (isset($_REQUEST["userid"]) && isset($_REQUEST["podcastid"])) 
		{	

			$myusername = $_REQUEST["userid"];

			$mediaRef = $_REQUEST["podcastid"];

			// Upload files into course directory

			$mediasdir = getMediasSysDir();

			if (!file_exists($mediasdir))
			{
				mkdir($mediasdir, 0755);
				chmod($mediasdir, 0755);
			}

			$mediapath = $mediasdir . $mediaRef . "/";

			if (!file_exists($mediapath))
			{
				mkdir($mediapath, 0755);
				chmod($mediapath, 0755);
			}
			
			if ( isset($_REQUEST['filetype']) ) $filetype = $_REQUEST['filetype'];

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

			foreach ($_FILES as $fieldName => $file) {  
				$tmp_file = $file['tmp_name'];
				$name_file = $file['name'];
				break;  
			}
			
		    if( !is_uploaded_file($tmp_file) )
			{
				exit("FILE_NOT_FOUND");
			}

			if ( !strstr($name_file, 'flv') 
				&& !strstr($name_file, 'wmv')
				&& !strstr($name_file, 'avi')
				&& !strstr($name_file, 'mp4')
				&& !strstr($name_file, 'png')
				&& !strstr($name_file, 'jpg')
				&& !strstr($name_file, 'htm')
				&& !strstr($name_file, 'html')
				&& !strstr($name_file, 'xml')
				&& !strstr($name_file, 'mp3'))
				exit("FILE_FORMAT_NOT_ALLOWED");


		    if( !move_uploaded_file($tmp_file, $content_dir . $name_file) )
		    {
		        exit("UNABLE_TO_MOVE_FILE");
		    }


 			if (isset($_REQUEST["setpreviewurl"]))
			{
				$previewurl = getMediaWebDir($mediaRef) . "multimedia/" . $name_file;
				updateMediaPreview($mediaRef, $previewurl);
			}

		    echo "UPLOAD_COMPLETED";
		}
		else
			return "NO_USER_ID";
	}
	elseif ($mytask == "uploadcompleted")
	{
	//--------------------------------------------------
	// Insert DB entry for media
	//--------------------------------------------------

		if (isset($_REQUEST['mediaTitle']) && isset($_REQUEST['mediaRef']) 
			&& isset($_REQUEST['mediaType']) && isset($_REQUEST['userid']))
		{

			$mediaRef = $_REQUEST['mediaRef'];

			if (mediaAlreadyExists($mediaRef))
			{
				$mediatype = $_REQUEST['mediaType'];
				
				if ($mediatype == "podaudio")
					$previewurl = getAudioMediaPreviewUrl($mediaRef);
				else
					$previewurl = getMediaPreviewUrl($mediaRef);
				
				updateMediaPreview($mediaRef, $previewurl);
				echo "MEDIA_ALREADY_EXISTS";
			}
			else
			{
				$mediatitle = $_REQUEST['mediaTitle'];

				$mediadesc = $_REQUEST['mediaDesc'];

				if (isset($_REQUEST['mediaTags']))
					$mediatags = $_REQUEST['mediaTags'];
				else
					$mediatags = "";
				
				$mediauser = $_REQUEST['userid'];

				$mediapublic = "false";

				$mediatype = $_REQUEST['mediaType'];

				$mediaurl = getMediaWebDir($mediaRef);

				if ($mediatype == "podaudio")
					$previewurl = getAudioMediaPreviewUrl($mediaRef);
				else
					$previewurl = getMediaPreviewUrl($mediaRef);

				createMedia($mediaRef, $mediatitle, $mediadesc, $mediauser, $mediatags, $mediatype, $mediaurl, $previewurl,"","","");

				if (isset($_REQUEST['moduleRef']))
				{

						$courseCode = $_REQUEST['moduleRef'];
						addMediaToCourse($courseCode, $mediaRef);
				}
	
				echo "MEDIA_CREATED";

			}
		}


	}
	elseif ($mytask == "checkconnection")
	{
		if (isset($_REQUEST['userid']) && isset($_REQUEST['pwd']))
		{
			$username = $_REQUEST['userid'];
			$password = $_REQUEST['pwd'];

			echo checkUserIdent($username, $password);

		}
	}
	elseif ($mytask == "downloadvideo")
	{
		if (isset($_REQUEST['videoid']) && isset($_REQUEST['format']))
		{
			$vid = $_REQUEST['videoid'];
			$vformat = $_REQUEST['format'];

			if ($vformat == "mp4")
				$filename = $vid . ".mp4";
			elseif ($vformat == "mp4slides")
				$filename = $vid . "slides.mp4";
			elseif ($vformat == "mp3")
				$filename = $vid . ".mp3";
			else
				$filename = $vid . "." . $vformat;		

			$fullpath = getMediaSysDir($vid) . "multimedia/" . $filename;

			if (file_exists($fullpath))
			{
				downloadVideo($fullpath, $filename);
			}
			else
				echo "Le fichier multimédia est introuvable sur le serveur";

		}
	}
	elseif ($mytask == "uploadscreen")
	{
		if (isset($_REQUEST["mediaref"])) 
		{			
			$mediaref = $_REQUEST["mediaref"];
			
			foreach ($_FILES as $fieldName => $file) 
			{  
				$tmp_file = $file['tmp_name'];
				$name_file = $file['name'];
				break;  
			}
			
			createMediaLiveDir($mediaref);
						
			$livecoursedir = getMediaSlideDir($mediaref);
			
		    if( !is_uploaded_file($tmp_file) )
			{
				exit("FILE_NOT_FOUND");
			}

			if ( !strstr($name_file, 'jpg') )
				exit("FILE_FORMAT_NOT_ALLOWED");

			$destfilename = $name_file;

		    if( !move_uploaded_file($tmp_file, $livecoursedir . $destfilename) )
		    {
		        exit("UNABLE_TO_MOVE_FILE");
		    }
			
			if (file_exists($livecoursedir .  $destfilename))
			{	
				chmod($livecoursedir . $destfilename, 0755);
			}
			
			updateMediaPreview($mediaref, getMediaSlideWebDir($mediaref) . $destfilename);
		    echo "UPLOAD_COMPLETED";

		}
	}
	elseif ($mytask == "startlive")
	{
		if (isset($_REQUEST["userid"]) && isset($_REQUEST["courseid"]) && isset($_REQUEST["mediaref"]))
		{
				$mediauser = $_REQUEST["userid"];
				$mediaref = $_REQUEST["mediaref"];
				$mediatype = $_REQUEST["mediatype"];
				$courseid = $_REQUEST["courseid"];
				$mediaSlide = $_REQUEST["mediaSlide"];
				$streamingserver = $_REQUEST["streamingserver"];
				
				$mediatitle = $_REQUEST["mediaTitle"];
				$mediadesc = $_REQUEST["mediaDesc"];
				$mediatags = "";
				$mediaurl = getMediaWebDir($mediaRef);
				
				createMediaLiveDir($mediaref);

				$previewurl = getMediaSlideWebDir($mediaref) . $mediaSlide;
							
				createMedia($mediaref, $mediatitle, $mediadesc, $mediauser, $mediatags, $mediatype, $streamingserver, $previewurl,"","","");
				addMediaToCourse($courseid, $mediaref);
				setMediaStatus($mediaref, 2);
				echo "OK";				
		}
	}
	elseif ($mytask == "addlivechatmessage")
	{
		if (isset($_REQUEST["mediaref"]) && isset($_REQUEST["username"]) && isset($_REQUEST["chatmsg"]))
		{
			$mediaref = $_REQUEST["mediaref"];
			$message = strip_tags($_REQUEST["chatmsg"]);
			$username = $USER->firstname . " " . $USER->lastname;
			liveChatAddMessage($mediaref, $username, $message);
			echo $username . " : " . $message;
		}
	}
	elseif ($mytask == "getlivechatmessages")
	{
		if (isset($_REQUEST["mediaref"]))
		{
			$mediaref = $_REQUEST["mediaref"];
			$chatmessages = liveChatGetMessages($mediaref);
			echo $chatmessages;
		}
	}
	elseif ($mytask == "stoplive")
	{
		if (isset($_REQUEST["mediaref"]))
		{
				$mediaref = $_REQUEST["mediaref"];
				setMediaStatus($mediaref, 1);
				echo "OK";
		}
	}
	elseif ($mytask == "checkversion")
	{
		echo "INWICAST_MOODLE_1_0_3";
	}
	elseif ($mytask == "getlivecourseslide")
	{
		if (isset($_REQUEST["mediaref"]))
		{		
			$mediaref = $_REQUEST["mediaref"];
			$mediaInfo = getMediaInfo($mediaref);

			if (mediaIsLive($mediaInfo->status))
			{
				$myliveslide = $mediaInfo->preview_url;
				echo $myliveslide;
			}
			else
				echo "LIVE_STOPPED";				
		}
	}
}
elseif (isset($_REQUEST['video']))
{
	$mediaRef = $_REQUEST['video'];

	$mediaInfo = getMediaInfo($mediaRef);

 	if ($mediaInfo)
		echo getEmbeddedPlayer($mediaInfo);


}
elseif (isset($_REQUEST['file']))
{

    require_once('../../../lib/filelib.php');

    if (!isset($CFG->filelifetime)) {
        $lifetime = 86400;     // Seconds for files to remain in caches
    } else {
        $lifetime = $CFG->filelifetime;
    }

    // disable moodle specific debug messages
    disable_debugging();

    $relativepath = get_file_argument('index.php');
    $forcedownload = optional_param('forcedownload', 0, PARAM_BOOL);
    
    // relative path must start with '/', because of backup/restore!!!
    if (!$relativepath) {
        error('No valid arguments supplied or incorrect server configuration');
    } else if ($relativepath{0} != '/') {
        error('No valid arguments supplied, path does not start with slash!');
    }

    $pathname = $CFG->dataroot."/medias".$relativepath;

    // extract relative path components
    $args = explode('/', trim($relativepath, '/'));
    if (count($args) == 0) { // always at least courseid, may search for index.html in course root
        error('No valid arguments supplied');
    }


    // security: only editing teachers can access backups
    if ((count($args) >= 2) and (strtolower($args[1]) == 'backupdata')) {
        if (!has_capability('moodle/site:backup', get_context_instance(CONTEXT_COURSE, $course->id))) {
            error('Access not allowed');
        } else {
            $lifetime = 0; //disable browser caching for backups 
        }
    }

    if (is_dir($pathname)) {
        if (file_exists($pathname.'/index.html')) {
            $pathname = rtrim($pathname, '/').'/index.html';
            $args[] = 'index.html';
        } else if (file_exists($pathname.'/index.htm')) {
            $pathname = rtrim($pathname, '/').'/index.htm';
            $args[] = 'index.htm';
        } else if (file_exists($pathname.'/Default.htm')) {
            $pathname = rtrim($pathname, '/').'/Default.htm';
            $args[] = 'Default.htm';
        } else {
            // security: do not return directory node!
            not_found($course->id);
        }
    }

    // security: teachers can view all assignments, students only their own
    if ((count($args) >= 3)
        and (strtolower($args[1]) == 'moddata')
        and (strtolower($args[2]) == 'assignment')) {

        $lifetime = 0;  // do not cache assignments, students may reupload them
        if (!has_capability('mod/assignment:grade', get_context_instance(CONTEXT_COURSE, $course->id))
          and $args[4] != $USER->id) {
           error('Access not allowed');
        }
    }

    // security: force download of all attachments submitted by students
    if ((count($args) >= 3)
        and (strtolower($args[1]) == 'moddata')
        and ((strtolower($args[2]) == 'forum')
            or (strtolower($args[2]) == 'assignment')
            or (strtolower($args[2]) == 'data')
            or (strtolower($args[2]) == 'glossary')
            or (strtolower($args[2]) == 'wiki')
            or (strtolower($args[2]) == 'exercise')
            or (strtolower($args[2]) == 'workshop')
            )) {
        $forcedownload  = 1; // force download of all attachments
    }
    if ($args[0] == 'blog') {
        $forcedownload  = 1; // force download of all attachments
    }    

    // security: some protection of hidden resource files
    // warning: it may break backwards compatibility
    if ((!empty($CFG->preventaccesstohiddenfiles)) 
        and (count($args) >= 2)
        and (!(strtolower($args[1]) == 'moddata' and strtolower($args[2]) != 'resource')) // do not block files from other modules!
        and (!has_capability('moodle/course:viewhiddenactivities', get_context_instance(CONTEXT_COURSE, $course->id)))) {

        $rargs = $args;
        array_shift($rargs);
        $reference = implode('/', $rargs);

        $sql = "SELECT COUNT(r.id) " .
                 "FROM {$CFG->prefix}resource r, " .
                      "{$CFG->prefix}course_modules cm, " .
                      "{$CFG->prefix}modules m " .
                 "WHERE r.course    = '{$course->id}' " .
                   "AND m.name      = 'resource' " .
                   "AND cm.module   = m.id " .
                   "AND cm.instance = r.id " .
                   "AND cm.visible  = 0 " .
                   "AND r.type      = 'file' " .
                   "AND r.reference = '{$reference}'";
        if (count_records_sql($sql)) {
           error('Access not allowed');
        }
    }

    // check that file exists
    if (!file_exists($pathname)) {
        not_found($course->id);
    }

    // ========================================
    // finally send the file
    // ========================================
    session_write_close(); // unlock session during fileserving
    $filename = $args[count($args)-1];
    send_file($pathname, $filename, $lifetime, $CFG->filteruploadedfiles, false, $forcedownload);


}

    function not_found($courseid) {
        global $CFG;
        header('HTTP/1.0 404 not found');
        error(get_string('filenotfound', 'error'), $CFG->wwwroot.'/course/view.php?id='.$courseid); //this is not displayed on IIS??
    }

?>
