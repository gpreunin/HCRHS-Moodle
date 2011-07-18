<?php  
/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * @version 1.03 - may  2008
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

    require_once("../../config.php");
    require_once("lib/inwicast.lib.php");

    $mediaref = required_param('mediaRef', PARAM_TEXT); // media ref
    $id = required_param('id', PARAM_INT);   		// course id

    if (! $course = get_record("course", "id", $id)) {
        error("Course ID is incorrect");
    }

    if (! $media = getMediaInfo($mediaref)) {
        error("Media Ref is incorrect");
    }

    require_login($course->id);

    add_to_log($course->id, "inwicast", "view all", "index.php?id=$course->id", "");


   // $navigation = "<a href=\"". $CFG->wwwroot."/course/view.php?id=$course->id\">$course->shortname</a> ->";
   //	$navigation = "<a href=\"". $CFG->wwwroot."/course/view.php?id=$course->id\">$course->shortname</a> ->
   //                               <a href=\"index.php?id=$course->id\">" . get_string("mediacenter","block_inwicast") . "</a>->$media->title";
    $navlinks = array(); 
    $navlinks[]=array('name' => get_string("mediacenter","block_inwicast"),'link' => 'index.php?id='.$course->id, 'type' => 'title');
    $navlinks[]=array('name' => $media->title, 'link' => '', 'type'=> 'title');
    $navigation=build_navigation($navlinks);
    

    print_header("$course->shortname", "$course->fullname", $navigation, "", "", true, "", navmenu($course));

	echo "<p>$media->description</p>"; 


	$currenttab = "showmedia";

    	require_once("tabs.php");


if ( isset($_REQUEST['cmd']) ) $cmd = $_REQUEST['cmd'];

switch ($cmd) {
	case 'show_media':
		echo "<script type='text/javascript' src='" . getInwicastWeb() . "mediaplayer/swfobject.js'></script>\n";

		if (mediaIsLive($media->status))
		{
			showLiveCourse($media);
		}
		else
		{
			showMediaPlayer($media);
		}

		break;
	case 'delete_media':
		if (isteacher($course->id))
		{
			if (deleteMedia($media->media_ref))
				echo showMessageBox(get_string('media_deleted','block_inwicast'));
			else
				echo showMessageBox(get_string('media_delete_error','block_inwicast'));
		}
		break;

	case 'delete_media_from_course':
		if (isteacher($course->id))
		{
			if (deleteMediaFromCourse($course->id, $media->media_ref))
				echo showMessageBox(get_string('media_deleted_from_course','block_inwicast'));
			else
				echo showMessageBox(get_string('media_delete_from_course_error','block_inwicast'));
		}
		break;

	case 'share_media':

		shareMedia($media);
		break;

	case 'publish_media':
		if (isteacher($course->id))
		{
			$mymedia = $_REQUEST['mediaRef'];
			$mycourse = $_REQUEST['courseCode'];
			addMediaToCourse($mycourse, $mymedia);
			editMediaCourses($mymedia);
		}
		break;

	case 'publish_media_on_map':

		if (isteacher($course->id))
		{
			$mymedia = $_REQUEST['mediaRef'];
			showPublishMediaOnMapForm($mymedia);
		}
		break;

	case 'unpublish_media':
		if (isteacher($course->id))
		{
			$mymedia = $_REQUEST['mediaRef'];
			$mycourse = $_REQUEST['courseCode'];
			deleteMediaFromCourse($mycourse, $mymedia);
			editMediaCourses($mymedia);
		}
		break;
	case 'edit_media':
		if (isteacher($course->id))
		{
			$mediaInfo = getMediaInfo($mediaref);
			editMediaInfo($mediaInfo);
		}
		break;
	case 'download_media':
		downloadMedia($media);
		break;
	case 'update_media':

		if (isteacher($course->id))
		{
			
			if (isset($_REQUEST['mediaTitle']) && isset($_REQUEST['mediaDesc']))
			{
				if (updateMedia($media))
				{
					echo showMessageBox(get_string('media_updated','block_inwicast'));
				}
				else
				{
					echo showMessageBox(get_string('media_update_error','block_iniwcast'));
					$mediaInfo = getMediaInfo($mediaref);
					editMediaInfo($mediaInfo);
				}				
			}
			else
			{
				echo showMessageBox(get_string('media_update_incomplete_form'));
				$mediaInfo = getMediaInfo($mediaref);
				editMediaInfo($mediaInfo);
			}
		}
		break;

	case 'media_courses':

		if (isteacher($course->id))
		{
			$mymedia = $_REQUEST['mediaRef'];
			editMediaCourses($mymedia);
		}
		break;
	case 'media_visibility':

		if (isteacher($course->id))
		{
			$mymedia = $_REQUEST['mediaRef'];
			$mycourse = $_REQUEST['courseCode'];
			$vis = $_REQUEST['mediaVis'];
			setMediaVisibility($mycourse, $mymedia, $vis);
			editMediaCourses($mymedia);
		}
		break;
	case 'inwicast_stats':
		if ($isPlatformAdmin)
			showInwicastStats();
		break;
	case 'show_medias':
		showMedias();
		break;
	default:
		echo "<script type='text/javascript' src='" . getInwicastWeb() . "mediaplayer/swfobject.js'></script>\n";
		showMediaPlayer($media);
		break;
}

    print_footer($course);

/**
 *
 * This function shows the right player according to the media type
 *
 */

function showMediaPlayer( $mediaInfo ) {

	if (!isteacher()) mediaViewed($mediaInfo); // Increased the "times viewed" counter

	echo "<div align='center'>";
	echo getEmbeddedPlayer($mediaInfo);
	echo "</div>";
}

/**
 *
 * This function shows the edit media form
 *
 */

function editMediaInfo($mediaInfo)
{
	global $USER, $course;


	echo "<script type=\"text/javascript\">\n"
		. "function submitEditInfosForm(){\n"
		. "if (document.formEditInfos.mediaTitle.value == '')\n"
		. "alert('" . get_string("Please enter a title for this video") . "');\n"
		. "else if (document.formEditInfos.mediaDesc.value == '')\n"
		. "alert('" . get_string("Please enter a description for this video") . "');\n";

	if ($mediaInfo->media_type == "extvideo")
	{
	echo "else if (document.formEditInfos.embeddedPlayer.value == '')\n"
		. "alert('" . get_string("The text of the embedded player is missing") . "');\n";
	}
	echo "else document.formEditInfos.submit();\n"
		. "}\n";

	echo "function submitDeleteMediaForm(){\n"
		. "if (confirm('" . get_string("confirm_delete_media","block_inwicast") . "'))"
		. "{\n"
		. "document.formEditInfos.cmd.value = 'delete_media';" 
		. "document.formEditInfos.submit();\n"
		. "}\n"
		. "}\n";

	echo "function submitDeleteMediaFromCourseForm(){\n"
		. "if (confirm('" . get_string("confirm_delete_media_from_course","block_inwicast") . "'))"
		. "{\n"
		. "document.formEditInfos.cmd.value = 'delete_media_from_course';" 
		. "document.formEditInfos.submit();\n"
		. "}\n"
		. "}\n";

	echo "</script>\n";


	echo "<form name='formEditInfos' enctype='multipart/form-data' action='" . $_SERVER['PHP_SELF']."' method='post'>";
	echo "<table width='60%' align='center' >";
	echo "<tr><td style='color:red;'>" . get_string("title","block_inwicast") . "* </td><td align='left'> <textarea  name='mediaTitle' rows='1' cols='40' >" . stripslashes($mediaInfo->title) ."</textarea></td></tr>";
	echo "<tr><td style='color:red;'>" . get_string("description","block_inwicast") . "* </td><td align='left'> <textarea  name='mediaDesc' rows='2' cols='40' >". stripslashes($mediaInfo->description) . "</textarea></td></tr>";

	echo "<tr><td>" . get_string("author","block_inwicast") . " </td><td align='left'> <textarea  name='mediaAuthor' rows='2' cols='40'>".$mediaInfo->author."</textarea></td></tr>\n";

	if ($mediaInfo->media_type == "extvideo")
	{
		echo "<tr><td >" . get_string("embedded_player_html","block_inwicast") . "* </td><td align='left'> "
			."<textarea  name='embeddedPlayer' rows='4' cols='40'>" 
			. stripslashes($mediaInfo->embedded_player) ."</textarea></td></tr>";
			
		echo "<tr><td>" . get_string("link_to_preview","block_inwicast") 
			. " </td><td align='left'> "
			. "<input type='text' name='previewUrl' size='40' maxlength='100' value='" 
			. $mediaInfo->preview_url ."' /></td></tr>";
	}
	else
	{
		echo "<tr><td>" . get_string("upload_preview_image","block_inwicast") . "</td><td align='left'><img src='" . $mediaInfo->preview_url ."' width='120' height='90' /><br /> "
				. "</td></tr>";
		echo "<tr><td></td><td align='left'>"
				. "<input class='button' name='uploadimagefile' type='file' />"
				. "</td></tr><input type='hidden' name='MAX_FILE_SIZE' value='200000' />";

		echo "<tr><td>" . get_string("width","block_inwicast") 
				. " </td><td align='left'> <input type='text' name='mediaWidth' size='10' maxlength='3' value='"
				. $mediaInfo->width . "'/></td></tr>";

		echo "<tr><td>" . get_string("height","block_inwicast") 
				. " </td><td align='left'> <input type='text' name='mediaHeight' size='10' maxlength='3' value='"
				. $mediaInfo->height . "'/></td></tr>";
	}
	
	echo "<tr><td>" . get_string( "media_downloadable", "block_inwicast") . "</td>";
	
	if ($mediaInfo->is_downloadable)
		echo "<td><input type='checkbox' name='mediaDownloadable' checked='yes' /></td></tr>";
	else
		echo "<td><input type='checkbox' name='mediaDownloadable'  /></td></tr>";

	
	echo "<tr><td>" . get_string("keywords","block_inwicast") 
			. " </td><td align='left'> <input type='text' name='mediaTags' size='40' maxlength='100' value='"
			. $mediaInfo->tags . "'/></td></tr>";

	echo "<tr><td colspan='2' align='center'><p>"
		."<input type='button' onclick='javascript:submitEditInfosForm();' value='" . get_string("update_media","block_inwicast") ."' /> "
		."<input type='button' onclick='javascript:submitDeleteMediaFromCourseForm();' value='" . get_string("delete_media_from_course","block_inwicast") ."' /> "
		."<input type='button' onclick='javascript:submitDeleteMediaForm();' value='" . get_string("delete_media","block_inwicast") ."' />"
		."</p></td></tr>";
	echo "<input type='hidden' name='mediaRef' value='" . $mediaInfo->media_ref ."'/>";
	echo "<input type='hidden' name='id' value='" . $course->id ."'/>";
	echo "<input type='hidden' name='cmd' value='update_media'/>";
	echo "<tr><td colspan='2'><p><span class='required'>*</span><small> " . get_string('required') . "</small></p></td></tr>\n";

	echo "</table>";
	echo "</form>";
		

}

/**
 *
 * This function shows the web page where users can get the permanent link and embedded player of the media
 *
 */

function shareMedia($mediaInfo)
{
	echo "<table width=\"60%\" align='center'>\n";
	echo "<tr>\n"
		."<td align='center'>\n"
		. "<div id='downloadinwicast' class='box generalbox' >\n"
		.get_string("media_link_msg","block_inwicast") . "<br/>"
		. "<input type='text' name='mediaTags' size='60' value='". getMediaLink($mediaInfo->media_ref) . "'/>"
		."</div>\n"
		."</td>\n"
		."</tr>\n";
	echo "<tr><td align='center'><div id='downloadinwicast' class='box generalbox' >\n"
		.get_string("embedded_player_msg","block_inwicast") . "<br/>"
		."<textarea  name='embeddedPlayer' rows='6' cols='60'>"
		. getEmbeddedPlayer($mediaInfo)
		."</textarea>"
		."</div></td></tr>";
	echo "</table>";

}

/**
 *
 * This function shows the web page where mp3 and mp4 versions can be downloaded
 *
 */

function downloadMedia($mediaInfo)
{

	$mediaref = $mediaInfo->media_ref;


	$hasdownload = false;


		$mymessage =  "<a href='".getMediasWebDir(). "?task=downloadvideo&videoid=$mediaref&format=" . getUploadedVideoFormat($mediaref) ."' target='_blank'>"
	        ." ".get_string("download_media", "block_inwicast")
		."</a>";
		showMessageBox($mymessage);
		$hasdownload = true;
	
	
	if (hasMP3FullVideo($mediaref))
	{
		$mymessage =  "<a href='".getMediasWebDir(). "?task=downloadvideo&videoid=$mediaref&format=mp3' target='_blank'>"
	        ." ".get_string("download_mp3", "block_inwicast")
		."</a>";
		showMessageBox($mymessage);
		$hasdownload = true;
	}

	if (hasMP4FullVideo($mediaref))
	{
		$mymessage = "<a href='".getMediasWebDir(). "?task=downloadvideo&videoid=$mediaref&format=mp3' target='_blank'>"
	        ." ".get_string("download_mp4", "block_inwicast")
		."</a>";
		showMessageBox($mymessage);
		$hasdownload = true;
	}

	if (hasMP4FullVideoWithSlides($mediaref))
	{
		$mymessage = "<a href='".getMediasWebDir(). "?task=downloadvideo&videoid=$mediaref&format=mp3' target='_blank'>"
	        ." ".get_string("download_mp4_slides", "block_inwicast")
		."</a>";
		showMessageBox($mymessage);
		$hasdownload = true;
	}
	
	

	if (!$hasdownload)
	{
		showMessageBox(get_string("no_download","block_inwicast"));
	}

}

/**
 * This function shows the form to publish medias on map
 *
 */ 

function showPublishMediaOnMapForm($mediaref) 
{
	showMessageBox(get_string("publish_media_on_map_desc","block_inwicast"));
	echo "<div align='center'>";
	echo getMapPublisher($mediaref);
	echo "</div>";
}

/*-------------------------------------------------------------------------------------------------------------------------------------------------------
									LIVE ZONE
-----------------------------------------------------------------------------------------------------------------------------------------------------------*/

function showLiveCourse($mediaInfo)
{
	global $USER;
	
	$currentUserName = $USER->username;
	
	$mediaref = $mediaInfo->media_ref;
	$liveslide = $mediaInfo->preview_url;
	$streamingserver = $mediaInfo->media_url;
	$streamid = $mediaref;
	$mediatitle = $mediaInfo->title;
	$mediatype = $mediaInfo->media_type;
		
	$playerwidth = 320;
	$playerheight = 240;
	$displayheight = $playerheight;

	if ($mediatype == "podscreen")
	{
		$playerwidth = 640;
		$playerheight = 480;
		$displayheight = $playerheight;
	}
	elseif ($mediatype == "powerpodaudio")
	{
		$playerwidth = 640;
		$playerheight = 20;
		$displayheight = 0;
	}
	echo "<div id=\"mainlivescreen\" >";
		
	echo "<table width=\"100%\"  >";
	echo "<tr><td align=\"center\">\n";

	if ($mediatype == "powerpodaudio")
	{
		echo "<table >";
		echo "<tr><td>";
		echo "<div style=\"border: 1px solid rgb(204, 204, 204);\">\n";
		showLiveMediaSlide($liveslide);
		echo "<div id=\"liveplayer\">"
			. getEmbeddedPlayer($mediaInfo)
			."</div>";
		echo "</div>\n";	
							
		echo "</td><td width=\"324px\" valign=\"top\" >";
							
		echo "<div id=\"accordion\">";
		showLiveChat($currentUserName, $mediaref);
		echo "</div>";
						
		echo "</td></tr></table>";
		
	}
	elseif ($mediatype == "podaudio")
	{
		echo "<table >";
		echo "<tr><td>";
		showLiveChat($currentUserName, $mediaref);
	
							
		echo "</td></tr><tr><td>";

		echo "<div id=\"liveplayer\">"
			. getEmbeddedPlayer($mediaInfo)
			."</div>";
						
		echo "</td></tr></table>";
		
	}
	elseif ($mediatype == "powerpodvideo")
	{
					
		echo "<table>";

		echo "<tr><td align=\"center\" width=\"324px\" valign=\"top\" >\n";
		
		echo "<div id=\"liveplayer\">"
				. getEmbeddedPlayer($mediaInfo)
				."</div>";
		
		echo "<div id=\"accordion\">";
			showLiveChat($currentUserName, $mediaref);
		echo "</div>";
		echo "</td><td valign=\"top\" >";
		showLiveMediaSlide($liveslide);
		echo "</td></tr></table>";
	}
	else
	{
		echo "<table >";
		echo "<tr><td>";
		echo "<div style=\"border: 1px solid rgb(204, 204, 204);\" id=\"liveplayer\">"
				. getEmbeddedPlayer($mediaInfo)
				."</div>";
							
		echo "</td><td width=\"324px\" valign=\"top\" >";							
		echo "<div id=\"accordion\">";
		showLiveChat($currentUserName, $mediaref);
		echo "</div>";						
		echo "</td></tr></table>";					
	}

	echo "</td></tr></table>";				
	
	
	showLiveJavascript($mediaref, $mediatype);
	
	echo "</div>";
}

function showLiveInfo()
{
	echo "<table width='60%' align='center'>\n";
	echo "<tr>\n"
					."<td align='left'>\n"
					. "<div id='downloadinwicast' class='box generalbox' width='80%'>\n";
					
	echo get_string("live_course_intro_teacher","block_inwicast");
	
	echo "</div>"
					."</td>\n"
					."</tr>\n";
	echo "</table>";
}

function showLiveJavascript($mediaref, $mediatype)
{
			////////////////////////////JAVASCRIPT////////////////////////////////
			?>
			
			<link href="css/inwicastlive.css" rel="stylesheet" type="text/css">
			<script type="text/javascript" src="js/mootools-release-1.11.js"></script>
			<script type="text/javascript">

			var currentslideurl = "";

			jsRefreshLive();

			function jsRefreshLiveSlide(mediaref)
			{
						
						var mediaurl = "../../blocks/inwicast/medias/index.php?task=getlivecourseslide&mediaref=" + mediaref;
						var myAjax = new Ajax(mediaurl, {method: 'get', onSuccess: function() {

							var myresponse = this.response.text;
							
							if (myresponse != currentslideurl)
							{
							
								if (myresponse == "LIVE_STOPPED")
								{
									document.getElementById("mainlivescreen").innerHTML = "<?php echo get_string("live_session_ended","block_inwicast"); ?>";
								}
								else
								{
									document.getElementById("slideimage").innerHTML = "<img src=\"" + myresponse + "\" ></img>";
								}
								currentslideurl = myresponse;
							}
							
						}}).request();
						
			}
			
			function jsRefreshLiveScreen(mediaref)
			{
						
				var mediaurl = "../../blocks/inwicast/medias/index.php?task=getlivecourseslide&mediaref=" + mediaref;
				var myAjax = new Ajax(mediaurl, {method: 'get', onSuccess: function() {
				
					var myresponse = this.response.text;
								
					if (myresponse == "LIVE_STOPPED")
					{
						document.getElementById("mainlivescreen").innerHTML = "<?php echo get_string("live_session_ended","block_inwicast"); ?>";
					}
			
				}}).request();
						
			}
			
			function jsSendChatMessage()
			{
						var mediaurl = "../../blocks/inwicast/medias/index.php?task=addlivechatmessage";
						var myAjax = new Ajax(mediaurl, {method: 'post', data: document.formLiveChat, onSuccess: function() {
						jsRefreshLiveChat(<?php echo "\"$mediaref\""; ?>);					
						}}).request();

						document.formLiveChat.chatmsg.value = "";
						
			}
			
			function jsRefreshLiveChat(mediaref)
			{
						var mediaurl = "../../blocks/inwicast/medias/index.php?task=getlivechatmessages&mediaref=" + mediaref;
						var myAjax = new Ajax(mediaurl, {method: 'get', onSuccess: function() {
						
						var myresponse = this.response.text;
						
						var p = 0;
						var pstart = 0;
						var pend = 0;
						var messages="";
						while ((pstart=myresponse.indexOf("<tr>", p)) >= 0)
						{
							pend = myresponse.indexOf("</tr>", pstart);
							if (pend > pstart && pend > 0)
							{
								messages = myresponse.substring(pstart, pend+5) + messages;
								p = pend;
							}
							else
								p = pstart + 4;
						}
						document.getElementById("livechat").innerHTML = "<table width=\"95%\" >" + messages + "</table>";
				
						}}).request();
			}

			function jsRefreshLive()
			{
<?php
			if (mediaHasSlide($mediatype))
				echo "jsRefreshLiveSlide(\"$mediaref\");\n";
			else
				echo "jsRefreshLiveScreen(\"$mediaref\");\n";
				
			echo "jsRefreshLiveChat(\"$mediaref\");\n";

?>
			setTimeout("jsRefreshLive()", 2500);

			}

			</script>
	<?php
}
	
function showLiveMediaSlide($slideurl)
{
		echo "<div style=\"width: 640px; height: 480px;\" >";
		
		echo "<div id=\"slideimage\">";
		
		echo "<img src=\"". $slideurl . "\" />";
		
		echo "</div>";
		
		echo "</div>";
		
}	

function showLiveChat($username, $mediaref)
{

	echo "<h3 class=\"toggler atStart\">" . get_string("live_chat", "block_inwicast") ."</h3>
			<div class=\"element atStart\" >";

	echo "<form name=\"formLiveChat\" method=\"post\" >
		<div id=\"livechat\" class=\"chatzone\" ></div>";
		
	if ($username != "")
	{	
		echo "<p align=\"center\"><textarea id=name=\"chatmsg\" name=\"chatmsg\" /></textarea><br />
		<input type=\"hidden\" name=\"mediaref\" value=\"$mediaref\" />
		<input type=\"hidden\" name=\"username\" value=\"$username\" />
		<input type=\"button\" onclick=\"javascript:jsSendChatMessage();\" value=\"". get_string("live_chat_post_msg", "block_inwicast") . "\" />
		</p>";
	}

	echo "</form>";
	echo "</div>";
}

function showLiveTalk($username, $mediaref)
{

	echo "<h3 class=\"toggler atStart\">" . get_string("live_talk", "block_inwicast") ."</h3>
			<div class=\"element atStart\"></div>";
}

function showLiveParticipants($courseid)
{
	global $CFG;
	
	echo "<h3 class=\"toggler atStart\">" . get_string("live_participants", "block_inwicast") . "</h3>
			<div class=\"element atStart\">";
	
	$users = array();    
	$participants = liveGetParticipants($courseid);
	
	echo "<table width=\"95%\" >";
	
    foreach ($participants as $participant) {
 
		echo "<tr><td>" . print_user_picture($participant->id, $courseid, $participant->picture, 16, true, false, '', false);
		echo "</td><td align=\"left\" >" . $participant->firstname . " " . $participant->lastname . "</td></tr>";

    }
	
	echo "</table>";
	
	echo "</div>";
}
	
function showLiveMediaPlayer($mediaref, $mediatype, $playerwidth, $playerheight, $streamingserver, $streamid, $displayheight)
{
	echo "<script type=\"text/javascript\" src=\"../../blocks/inwicast/mediaplayer/swfobject.js\"></script>\n";
	echo "<script type=\"text/javascript\">\n";
	echo "var s1 = new SWFObject(\"../../blocks/inwicast/mediaplayer/flvplayer.swf\",\"mediaplayer\",\"$playerwidth\",\"$playerheight\",\"7\");\n";
	echo "s1.addParam(\"allowfullscreen\",\"true\");\n";
	echo "s1.addVariable(\"width\",\"$playerwidth\");\n";
	echo "s1.addVariable(\"height\",\"$playerheight\");\n";
	echo "s1.addVariable(\"displayheight\",\"$displayheight\");\n";
	echo "s1.addVariable(\"enablejs\",\"true\");\n";
	echo "s1.addVariable(\"bufferlength\",\"3\");\n";
	echo "s1.addVariable(\"autostart\",\"true\");\n";
	echo "s1.addVariable(\"file\",\"". $streamingserver ."\");\n";
	echo "s1.addVariable(\"id\",\"". $streamid ."\");\n";

	echo "s1.write(\"liveplayer\");\n";
	echo "</script>";
}	

?>
