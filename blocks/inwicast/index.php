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

    require_once("../../config.php");
    require_once("lib/inwicast.lib.php");
    require_once("lib/nusoap.php");


    $id = required_param('id', PARAM_INT);   // course

	$viewmode = optional_param('viewmode','', PARAM_TEXT);

    if (! $course = get_record("course", "id", $id)) {
        error("Course ID is incorrect");
    }

    require_login($course->id);

    add_to_log($course->id, "block_inwicast", "view all", "index.php?id=$course->id", "");

    $strinwicast  = get_string("mediacenter", "block_inwicast");


	/// Print the header
    
       $navlinks = array(); 
       $navlinks[]=array('name' => $strinwicast, 'link' => '', 'type'=> 'title');
 

	/**if ($course->category) {
	       $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> 
	}
	else
	{
	       $navigation = '';
	}**/

        $navigation=build_navigation($navlinks);
    	//print_header("$course->shortname: $strinwicast", "$course->fullname", "$navigation $strinwicast", "", "", true, "", navmenu($course));
        print_header("$course->shortname: $strinwicast", "$course->fullname", $navigation, "", "", true, "", navmenu($course));
        
	if ( isset($_REQUEST['cmd']) ) 
		$cmd = $_REQUEST['cmd'];
	else
		$cmd = "";
		
	$currenttab = getInwicastCurrentTab($cmd);

	print_heading(get_string("mediacenter", "block_inwicast")); 

    require_once("tabs.php");

	//getVideoServerUserMediaList();

// Check cmd

if ( isset($_REQUEST['cmd']) ) $cmd = $_REQUEST['cmd'];

switch ($cmd) {
	case 'delete_media':
		if (isteacher($course->id))
		{
			$mymedia = $_REQUEST['mediaRef'];
			deleteMedia($mymedia);
			showMedias();
		}
		break;

	case 'publish_media':
		if (isteacher($course->id))
		{
			$mymedia = $_REQUEST['mediaid'];
			$mycourse = $course->id;
			addMediaToCourse($mycourse, $mymedia);
			showMedias();
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

	case 'download_inwicast_form':
		if (isteacher($course->id))
			showDownloadInwicastForm();
		break;

	case 'download_inwicast':

		if (isteacher($course->id))
		{
			addInwicastDownload($currentUserName);
			showUploadInwicastForm();
		}
		break;

	case 'upload_form':
		if (isteacher($course->id))
		{
			showUploadMediaForm();
		}
		break;
	case 'link_media_form':
		if (isteacher($course->id))
		{
			showLinkMediaForm();
		}
		break;
	case 'create_mediamap_form':
		if (isteacher($course->id))
		{
			showCreateMediamapForm();
		}
		break;
	case 'do_link_media':
		if (isteacher($course->id))
		{
			linkMedia();
			showMedias();
		}
		break;

	case 'do_create_media_map':
		if (isteacher($course->id))
		{
			doCreateMediaMap();
			showMedias();
		}
		break;

	case 'do_upload':
		if (isteacher($course->id))
		{
			uploadMedia();
			showMedias();
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
	case 'show_unpublished_medias':
		if (isteacher($course->id))
		{
			showUnpublishedMedias();
		}
		break;
	case 'show_medias':
		showMedias();
		break;
	case 'show_live_course':
		showLiveCourse($course->id);
		break;
	default:
		showMedias();
		break;
}

    print_footer($course);

/**
 *
 * This function shows the course medias - teacher and student views
 *
 */ 

function showMedias()
{
	global $USER, $CFG, $course, $viewmode;

	$mycourses = get_my_courses($USER->id);

	echo "<table width='90%' align='center'><tr><td width='80%' align='left'>";
	if ($mycourses && isteacher($course->id)) 
	{
		echo '<table class="controls" cellspacing="0"><tr>';
        	echo '<td class="left">';
        	$courselist = array();
        	foreach ($mycourses as $mycourse) {
            		$courselist[$mycourse->id] = format_string($mycourse->shortname) . " - " . format_string($mycourse->fullname);
        	}
        	popup_form($CFG->wwwroot.'/blocks/inwicast/index.php?id=',
                   $courselist, 'courseform', $course->id, '', '', '', false, 'self', get_string('mycourses', 'block_inwicast'));
        	echo '</td>';
		echo "</tr></table>";
    	}

	echo "<td align='right' width='5%'><a href='" . $CFG->wwwroot."/blocks/inwicast/index.php?id=" . $course->id . "&viewmode=medialist' title='" 
		. get_string('show_medias_as_list','block_inwicast') . "' >"
		. "<img src='"  . $CFG->wwwroot."/blocks/inwicast/images/view_detailed.png' /></a></td>";	
	echo "<td align='right' width='5%'><a href='" . $CFG->wwwroot."/blocks/inwicast/index.php?id=" . $course->id . "&viewmode=thumbnails' >"
		. "<img src='"  . $CFG->wwwroot."/blocks/inwicast/images/view_icon.png' alt='"
		. get_string('show_medias_as_thumbnails','block_inwicast') . "' /></a></td>";	

	if (isset($CFG->block_inwicast_rss_enabled))
	{
		if ($CFG->block_inwicast_rss_enabled)
			echo "<td align='right' width='5%'><a href='" . $CFG->wwwroot."/blocks/inwicast/rss.php?rssFeed=" . $USER->id . "&id=" . $course->id . "' >"
			. "<img src='"  . $CFG->wwwroot."/blocks/inwicast/images/rssfeed32x32.png' alt='"
			. get_string('show_course_rss_feed','block_inwicast') . "' /></a></td>";	
	}
	else
		echo "<td align='right' width='5%'><a href='" . $CFG->wwwroot."/blocks/inwicast/rss.php?rssFeed=" . $USER->id . "&id=" . $course->id . "' >"
			. "<img src='"  . $CFG->wwwroot."/blocks/inwicast/images/rssfeed32x32.png' alt='"
			. get_string('show_course_rss_feed','block_inwicast') . "' /></a></td>";	
			
	echo "</tr></table>";
    	echo "<br />";

	if ($viewmode != "thumbnails")	
	{

		$vpreview = get_string("preview","block_inwicast");
		$vtitle = get_string("title","block_inwicast");
		$vdesc = get_string("description","block_inwicast");
		$vview = get_string("viewed","block_inwicast");
		$vdate = get_string("date","block_inwicast");

	        $table->head  = array ($vpreview, $vtitle, $vdesc, $vview, $vdate);
	        $table->align = array ("center", "left");

		$mediaList = getCourseMedias($course->id, "all");

		
		
		if ($mediaList)
		{
			foreach ($mediaList as $mymedia )
			{
			
				if (mediaIsLive($mymedia->status))
					$previewurl = getInwicastWeb() . "images/live.jpg";
				else
					$previewurl = $mymedia->preview_url;
			
				$linktomedia = $CFG->wwwroot."/blocks/inwicast/view.php?id=". $course->id 
						. "&cmd=show_media&mediaRef=".$mymedia->media_ref;

				$table->data[] = array("<a href='$linktomedia' ><img src='" 
							. $previewurl."' width='120' height='90' /></a>", 
					"<a href='$linktomedia' >".stripslashes($mymedia->title)
				."</a>", stripslashes($mymedia->description), $mymedia->viewed, $mymedia->media_date);

			}
		}
		print_table($table);

	}
	else
	{


		$mediaList = getCourseMedias($course->id, "all");

		$col = 0;

		if ($mediaList)
		{
			echo "<table width='80%' align='center'>";
			if ($col == 0) echo "<tr>";

			foreach ($mediaList as $mymedia )
			{			
				if (mediaIsLive($mymedia->status))
					$previewurl = getInwicastWeb() . "images/live.jpg";
				else
					$previewurl = $mymedia->preview_url;
					
				echo "<td width='25%' align='center' valign='top'><a href='".$CFG->wwwroot."/blocks/inwicast/view.php?id="
					. $course->id . "&cmd=show_media&mediaRef=" . $mymedia->media_ref ."' >"
					. "<img src='" . $previewurl ."' width='120' height='90' /></a><br/>"
					. "<small>" 
					. "<a href='".$CFG->wwwroot."/blocks/inwicast/view.php?id="
					. $course->id . "&cmd=show_media&mediaRef=" . $mymedia->media_ref ."' >"
					. $mymedia->title
					. "</a><br/>" 
					. get_string("date_published","block_inwicast") . " " . $mymedia->media_date
					. "</small>";
				echo "</td>";
				$col++;
				if ($col == 4)
				{
					echo "</tr><tr>";
				 	$col = 0;
				}				
			}

			echo "</tr></table>";
		}
	}


}

/**
 *
 * This function displays unpublished medias : medias which are not yet published in a course
 * those medias were generally published with Inwicast Publisher
 *
 */ 

function showUnpublishedMedias()
{
	global $USER, $CFG, $course, $viewmode;

	showMessageBox(get_string('unpublished_videos_intro','block_inwicast'));

	if ($viewmode != "thumbnails")	
	{

		$vpreview = get_string("preview","block_inwicast");
		$vtitle = get_string("title","block_inwicast");
		$vdesc = get_string("description","block_inwicast");
		$vview = get_string("viewed","block_inwicast");
		$vdate = get_string("date","block_inwicast");
		$vselect = get_string("publish","block_inwicast");

	        $table->head  = array ($vpreview, $vtitle, $vdesc, $vview, $vdate,$vselect);
	        $table->align = array ("center", "left");

		$mediaList = getUnpublishedMedias();

		if ($mediaList)
		{
			foreach ($mediaList as $mymedia )
			{

			$table->data[] = array("<a href='".$CFG->wwwroot."/blocks/inwicast/view.php?id="
					. $course->id . "&cmd=show_media&mediaRef="
					.$mymedia->media_ref ."' ><img src='" . $mymedia->preview_url."' width='120' height='90' /></a>", 
					stripslashes($mymedia->title), stripslashes($mymedia->description), $mymedia->viewed, $mymedia->media_date, 
					"<a href='".$CFG->wwwroot."/blocks/inwicast/index.php?id=" . $course->id . "&cmd=publish_media&mediaid="
					.$mymedia->media_ref ."' >" . get_string("publish","block_inwicast"));

			}
		}
		print_table($table);

	}
	else
	{


		$mediaList = getCourseMedias($course->id, "all");

		$col = 0;

		if ($mediaList)
		{
			echo "<table width='80%' align='center'>";
			if ($col == 0) echo "<tr>";

			foreach ($mediaList as $mymedia )
			{
				echo "<td width='25%' align='center' valign='top'><a href='".$CFG->wwwroot."/blocks/inwicast/view.php?id="
					. $course->id . "&cmd=show_media&mediaRef=" . $mymedia->media_ref ."' >"
					. "<img src='" . $mymedia->preview_url."' width='120' height='90' /></a><br/>"
					. "<small>" 
					. "<a href='".$CFG->wwwroot."/blocks/inwicast/view.php?id="
					. $course->id . "&cmd=show_media&mediaRef=" . $mymedia->media_ref ."' >"
					. $mymedia->title
					. "</a><br/>" 
					. get_string("date_published","block_inwicast") . " " . $mymedia->media_date
					. "</small>";
				echo "</td>";
				$col++;
				if ($col == 4)
				{
					echo "</tr><tr>";
				 	$col = 0;
				}				
			}

			echo "</tr></table>";
		}
	}


}

/**
 *
 * This function displays the upload media form
 *
 */ 

function showUploadMediaForm() 
{
	global $USER, $course;

	// JAVASCRIPT ZONE

	echo "<script type=\"text/javascript\">\n";
	echo "function submitUploadForm(){\n";
	echo "myfile = document.formUpload.uploadfile.value;\n";
	echo "if (document.formUpload.mediaTitle.value == '')\n";
	echo "alert('" . get_string("no_video_title","block_inwicast") . "');\n";
	echo "else if (document.formUpload.mediaDesc.value == '')\n";
	echo "alert('" . get_string("no_video_description","block_inwicast") . "');\n";
	echo "else if (document.formUpload.uploadfile.value == '')\n";
	echo "alert('" . get_string("no_file_to_upload","block_inwicast") . "');\n";
	echo "else if ((myfile.indexOf('.flv',0) <= 0)"
		. " && (myfile.indexOf('.wmv',0) <= 0)" 
		. " && (myfile.indexOf('.mov',0) <= 0)"
		. " && (myfile.indexOf('.mpg',0) <= 0)"
		. " && (myfile.indexOf('.mpeg',0) <= 0)"
		. " && (myfile.indexOf('.wma',0) <= 0)"
		. " && (myfile.indexOf('.avi',0) <= 0)"
		. " && (myfile.indexOf('.swf',0) <= 0)"
		. " && (myfile.indexOf('.mp3',0) <= 0)"
		. " && (myfile.indexOf('.mp4',0) <= 0))\n";
	echo "alert('" . get_string("wrong_video_format","block_inwicast") . "');\n";
	echo "else document.formUpload.submit();\n";
	echo "}\n";
	echo "</script>\n";

	echo "<table width=\"60%\" align='center'>\n";
	echo "<tr>\n"
		."<td align=\"left\">\n"
		. "<div id='downloadinwicast' class='box generalbox' >\n"
		.get_string("upload_intro_message","block_inwicast") . " " . get_string("upload_warning","block_inwicast")
		."</div>\n"
		."</td>\n"
		."</tr>\n";
	echo "</table>";

	echo "<form name='formUpload' enctype='multipart/form-data' action='" . $_SERVER['PHP_SELF']."?cmd=do_upload' method='post'>";
	echo "<table width='60%' align='center'>";
	echo "<tr><td style='color:red;'>" . get_string("title","block_inwicast") . "*</td>"
		."<td width='65%'><input type='text' name='mediaTitle' size='40' maxlength='100'/></td></tr>";
	echo "<tr><td style='color:red;'>" . get_string("description","block_inwicast") . "*</td><td><textarea  name='mediaDesc' rows='2' cols='35'></textarea></td></tr>\n";


	echo "<tr><td>" . get_string("author","block_inwicast") . "</td><td><textarea  name='mediaAuthor' rows='2' cols='35'></textarea></td></tr>\n";

	echo "<tr><td>" . get_string("keywords","block_inwicast") . "</td><td><input type='text' name='mediaTags' size='40' maxlength='100'/></td></tr>\n";

	echo "<tr><td style='color:red;'>" . get_string("file_to_upload","block_inwicast") ."*</td>"
		."<td><input name='uploadfile' type='file' /></td></tr>";

	echo "<input type='hidden' name='id' value='".$course->id."' />";
	echo "<input type='hidden' name='courseid' value='".$course->id."' />";

	echo "<tr><td colspan='2' align='center' ><p>"
		. "<input type='button' onclick='javascript:submitUploadForm();' value='". get_string("upload")."' />\n"
		. "</p></td></tr>";
	echo "<tr><td colspan='2'><p><span class='required'>*</span><small> " . get_string('required') . "</small></p></td></tr>\n";

	echo "</table>\n";
	echo "</form>\n";


}

/**
 *
 * This function displays the download inwicast form
 *
 */

function showDownloadInwicastForm() 
{

	echo "<script type=\"text/javascript\">\n";
	echo "function countDownload(){\n";
	echo "document.formInwicastDownload.submit();\n";

	echo "}\n";
	echo "</script>\n";

	echo "<form name='formInwicastDownload' enctype='multipart/form-data' action='" . $_SERVER['PHP_SELF']."?cmd=download_inwicast' method='post'>";
	echo "<table width='60%' align='center'>";
	echo "<tr><td>"
		. "<div id='downloadinwicast' class='box generalbox' >\n"
		. get_string("download_inwicast_message","block_inwicast")
		. "<div align='center'><img src='" . getInwicastWeb() . "images/inwicast_publisher_moodle.png" . "' /></div>"
		. "</div>"
		. "</td></tr>";
	echo "<tr><td align='center'>"
		. "<input type='button' onclick='window.open(\"".getInwicastWeb()."publisher/InwicastPublisherMoodle.exe\");' value='". get_string("download_inwicast_button","block_inwicast")."' />\n"
		. "</td>";
	echo "</tr>";
	echo "<tr><td align='center'><p><small>" . get_string("download_inwicast_alert","block_inwicast") . "</small></p></td></tr>";
	echo "</table>\n";
	echo "</form>\n";

}

/**
 *
 * This function shows the form to link external medias (youtube, google videos, ...)
 *
 */

function showLinkMediaForm() 
{
	global $USER, $course;


	echo "<table width='60%' align='center'>\n";
	echo "<tr>\n"
		."<td align='left'>\n"
		. "<div id='downloadinwicast' class='box generalbox' width='80%'>\n"
		.get_string("link_video_intro_message","block_inwicast")
		. "</div>"
		."</td>\n"
		."</tr>\n";
	echo "</table>";

	echo "<script type=\"text/javascript\">\n";
	echo "function submitVideoLinkForm(){\n";
	echo "if (document.formVideoLink.mediaTitle.value == '')\n";
	echo "alert('" . get_string("no_video_title","block_inwicast") . "');\n";
	echo "else if (document.formVideoLink.mediaDesc.value == '')\n";
	echo "alert('" . get_string("no_video_description","block_inwicast") . "');\n";
	echo "else if (document.formVideoLink.embeddedPlayer.value == '')\n";
	echo "alert('" . get_string("no_embedded_player","block_inwicast") . "');\n";
	echo "else document.formVideoLink.submit();\n";
	echo "}\n";
	echo "</script>\n";

	echo "<form name='formVideoLink' enctype='multipart/form-data' action='" . $_SERVER['PHP_SELF']."?cmd=do_link_media' method='post'>";
	echo "<table width='60%' align='center'>";
	echo "<tr><td style='color:red;'>" . get_string("title","block_inwicast") . "*</td>"
		."<td width='65%'><input type='text' name='mediaTitle' size='40' maxlength='100'/></td></tr>\n";
	echo "<tr><td style='color:red;'>" . get_string("description","block_inwicast") . "*</td><td><textarea  name='mediaDesc' rows='2' cols='40'></textarea></td></tr>\n";

	echo "<tr><td>" . get_string("author","block_inwicast") . "</td><td><textarea  name='mediaAuthor' rows='2' cols='40'></textarea></td></tr>\n";

	echo "<tr><td style='color:red;'>" . get_string("embedded_player_html","block_inwicast") . "*</td><td><textarea  name='embeddedPlayer' rows='4' cols='40'></textarea></td></tr>";

	echo "<tr><td>" . get_string("link_to_preview","block_inwicast") . "</td><td><input type='text' name='previewUrl' size='40' maxlength='100'/></td></tr>\n";

	echo "<tr><td>" . get_string("keywords","block_inwicast") . "</td><td><input type='text' name='mediaTags' size='40' maxlength='100'/></td></tr>\n";

	echo "<input type='hidden' name='id' value='".$course->id."' />\n";
	echo "<input type='hidden' name='courseid' value='".$course->id."' />\n";

	echo "<tr><td colspan='2' align='center'><p>"
		."<input type='button' onclick='javascript:submitVideoLinkForm();' value='". get_string("save", "block_inwicast")."' />"
		."</p></td></tr>";
	echo "<tr><td colspan='2'><p><span class='required'>*</span><small> " . get_string('required') . "</small></p></td></tr>\n";

	echo "</table>";
	echo "</form>";
}

/**
 *
 * This function shows the add mediamap form
 *
 */

function showCreateMediamapForm() 
{
	global $USER, $course;


	echo "<table width='60%' align='center'>\n";
	echo "<tr>\n"
		."<td align='left'>\n"
		. "<div id='downloadinwicast' class='box generalbox' width='80%'>\n"
		.get_string("mediamap_intro_message","block_inwicast")
		. "</div>"
		."</td>\n"
		."</tr>\n";
	echo "</table>";

	echo "<script type=\"text/javascript\">\n";
	echo "function submitMapForm(){\n"
	. "if (document.formMediaMap.mediaTitle.value == '')\n"
	. "alert('" . get_string("mediamap_no_title","block_inwicast") . "');\n"
	. "else if (document.formMediaMap.mediaDesc.value == '')\n"
	. "alert('" . get_string("mediamap_no_desc","block_inwicast") . "');\n"
	. "else {\n"
	. "var iframes = document.getElementsByTagName('IFRAME');"
	. "if (iframes.length > 0){\n"
	. "document.formMediaMap.lat.value = frames[0].document.iform.ilat.value;"
	. "document.formMediaMap.lng.value = frames[0].document.iform.ilng.value;"
	. "document.formMediaMap.zoom.value = frames[0].document.iform.izoom.value;"	
	. "document.formMediaMap.maptype.value = frames[0].document.iform.imaptype.value;"	
	. "document.formMediaMap.submit();}}\n";
	echo "}\n";
	echo "</script>\n";

	echo "<form name='formMediaMap' enctype='multipart/form-data' action='" . $_SERVER['PHP_SELF']."?cmd=do_create_media_map' method='post'>";
	echo "<table width='60%' align='center'>";
	echo "<tr><td style='color:red;'>" 
		. get_string("title","block_inwicast") . "*</td>"
		."<td width='65%'><input type='text' name='mediaTitle' size='40' maxlength='100'/></td></tr>\n";
	echo "<tr><td style='color:red;'>" 
		. get_string("description","block_inwicast") 
		. "*</td><td><textarea  name='mediaDesc' rows='2' cols='40'></textarea></td></tr>\n";

	echo "<tr><td>" . get_string("author","block_inwicast") 
		. "</td><td><textarea  name='mediaAuthor' rows='2' cols='40'></textarea></td></tr>\n";
	echo "<tr><td>" . get_string("keywords","block_inwicast") . "</td><td><input type='text' name='mediaTags' size='40' maxlength='100'/></td></tr>\n";

	echo "<input type='hidden' name='id' value='".$course->id."' />\n";
	echo "<input type='hidden' name='courseid' value='".$course->id."' />\n";
	echo "<input type='hidden' name='lat' value='0' />";
	echo "<input type='hidden' name='lng' value='0' />";
	echo "<input type='hidden' name='zoom' value='0' />";
	echo "<input type='hidden' name='maptype' value='G_NORMAL_MAP' />";
	echo "<input type='hidden' name='mapmode' value='createmap' />";

	echo "<tr><td colspan='2' align='center'>"
			."<iframe src='". getInwicastWeb() ."maps/?mapuser=" . $USER->username
			."&mapmode=createmap&mapwidth=640&mapheight=480' "
			."width='640' height='480' frameborder='0' scrolling='no'"
			." marginwidth='0' marginheight='0'></iframe>"
			."</td></tr>";

	echo "<tr><td colspan='2' align='center'><p>"
		."<input type='button' onclick='javascript:submitMapForm();' value='". get_string("save", "block_inwicast")."' />"
		."</p></td></tr>";
	echo "<tr><td colspan='2'><p><span class='required'>*</span><small> " . get_string('required') . "</small></p></td></tr>\n";

	echo "</table>";
	echo "</form>";
}

/**
 *
 * This function adds an external media to the mediacenter
 *
 */

function linkMedia()
{
	global $USER;

	if (isset($_REQUEST['mediaTitle']) && isset($_REQUEST['embeddedPlayer'])) 
	{	

		$mediaRef = "MEDIA".date("ymdHis") . rand(100, 999);

		$mediatitle = $_REQUEST['mediaTitle'];
		if (isset($_REQUEST['mediaDesc'])) 
			$mediadesc = $_REQUEST['mediaDesc'];
		else 
			$mediadesc=get_string("No description");

		$mediasdir = getMediasSysDir();
		
		$mediatype="extvideo";

		if (isset($_REQUEST['mediaTags']))
			$mediatags = $_REQUEST['mediaTags'];
		else
			$mediatags = "";
			

		$mediapublic = "false";

		$mediaurl = getMediaWebDir($mediaRef );

		if (isset($_REQUEST["previewUrl"])) 
			$previewurl = $_REQUEST["previewUrl"];
		else
			$previewurl = getDefaultMediaPreviewUrl();

		if (isset($_REQUEST['mediaAuthor']))
			$mediaauthor = $_REQUEST['mediaAuthor'];
		else
			$mediaauthor = "";

		if (isset($_REQUEST['mediaCopyright']))
			$mediacopyright = $_REQUEST['mediaCopyright'];
		else
			$mediacopyright = "";

		if (isset( $_REQUEST['embeddedPlayer']))
			$embeddedPlayer = $_REQUEST['embeddedPlayer'];
		else
			$embeddedPlayer = "";

		createMedia($mediaRef, $mediatitle, $mediadesc, $USER->username, $mediatags, $mediatype, $mediaurl, $previewurl, $mediaauthor, $mediacopyright, $embeddedPlayer);

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


/**
 *
 * This function adds an google map to the mediacenter
 *
 */

function doCreateMediamap()
{
	global $USER;

	if (isset($_REQUEST['mediaTitle']) 
		&& isset($_REQUEST['lat'])
		&& isset($_REQUEST['lng'])
		&& isset($_REQUEST['zoom'])
		&& isset($_REQUEST['maptype'])
	) 
	{	

		$mediaRef = "MEDIA".date("ymdHis") . rand(100, 999);

		$mediatitle = $_REQUEST['mediaTitle'];
		if (isset($_REQUEST['mediaDesc'])) 
			$mediadesc = $_REQUEST['mediaDesc'];
		else 
			$mediadesc=get_string("No description");


		$mediasdir = getMediasSysDir();
		
		$mediatype="mediamap";

		if (isset($_REQUEST['mediaTags']))
			$mediatags = $_REQUEST['mediaTags'];
		else
			$mediatags = "";
			
		$mediauser = $_REQUEST['userid'];
		$mediapublic = "false";

		$mediaurl = $_REQUEST["mediaUrl"];


		$previewurl = getInwicastWeb() . "images/mediamap.jpg";

		if (isset($_REQUEST['mediaAuthor']))
			$mediaauthor = $_REQUEST['mediaAuthor'];
		else
			$mediaauthor = "";

		if (isset($_REQUEST['mediaCopyright']))
			$mediacopyright = $_REQUEST['mediaCopyright'];
		else
			$mediacopyright = "";


		$maplat = $_REQUEST['lat'];
		$maplng = $_REQUEST['lng'];
		$mapzoom = $_REQUEST['zoom'];
		$maptype = $_REQUEST['maptype'];

		createMediaMap($mediaRef, $mediatitle, $mediadesc, $USER->username, $mediatags, $mediatype, $mediaurl, $previewurl, $mediaauthor, $mediacopyright, $maplng, $maplat, $mapzoom, $maptype);

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


function getVideoServerUserMediaList()
{
	global $USER;

	$currentUserName = $USER->username;

$password = "123456";
$filtername = "test";
$filtervalue = "test";

$paramList = array('user'   => $currentUserName,
                       'password' => $password,  
                       'filtername'    => $filtername, 
                       'filtervalue'    => $filtervalue    );

					   
$urlDuService = "http://www.Webcastor.tv/ws/index.php";

$client = new SoapClient(null, array('location' => $urlDuService,
                                     'uri'      => "http://test-uri/"));
/*
echo $urlDuService;

$client = new nuSoapclient($urlDuService );

$mediaList = $client->call('get_user_media_list', $paramList);

echo var_dump($paramList);

    if ( $client->getError() ) // SERVER CONNECTION FAILURE
    {
        echo '<center>'
            .'<p><b>Soap error</b></p>'
            .'<p>'
            .$mediaList['faultcode'  ].'<br />'
            .$mediaList['faultstring']
            .'</p>';
    }
    elseif ( is_array($mediaList) )
    {
		
		foreach ($mediaList as $media )
		{
			// Import vid�o list
			echo $media['title'];
			//if (!createMedia($media['media_ref'], $media['title'], $media['description'], $currentUserName, "", "extvideo", $media['media_url'], $media['preview_url'],"", "", ""))
			//	break;


		}

    }
*/

}

/**
 *
 * This function returns the tab to display
 *
 */

function getInwicastCurrentTab($cmd)
{

	$currenttab = "videos";

	switch ($cmd) {
	case 'show_media':
		break;
	case 'delete_media':
		break;

	case 'publish_media':
		break;

	case 'unpublish_media':
		break;
	case 'edit_media':
		break;
	case 'update_media':

		break;

	case 'download_inwicast_form':
		$currenttab = "downloadinwicast";

		break;

	case 'download_inwicast':

		break;

	case 'upload_form':
		$currenttab = "videoupload";
		break;
	case 'link_media_form':
		$currenttab = "videolink";
		break;
	case 'create_mediamap_form':
		$currenttab = "mediamap";
		break;
	case 'do_link_media':
		break;
	case 'do_upload':
		break;
	case 'media_courses':
		break;
	case 'media_visibility':
		break;
	case 'inwicast_stats':
		break;
	case 'show_unpublished_medias':
		$currenttab = "unpublished_videos";
		break;
	case 'show_live_course':
		$currenttab = "live_course";
		break;
	case 'show_medias':
		break;
	default:
		break;
	}
	return $currenttab;
}


?>
