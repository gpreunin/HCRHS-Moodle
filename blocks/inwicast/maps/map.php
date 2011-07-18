<?php

/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * This script includes ajax services for GoogleMap support
 *
 * @version 1.02 - january 2008
 *
 * @copyright (c) 2007-2008 INWICAST
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

if (isset($_REQUEST["task"]) )
{
	$mytask = $_REQUEST["task"];

	if ($mytask == "get_user_medias" && isset($_REQUEST["mapuser"]))
	{
		    echo "<script type='text/javascript'>"
		."function setMediaMarker(mymedia){\n"
		."parent.document.iform.imedia.value = mymedia;\n"
		."}\n"
		."</script>\n";


		$mapuser = $_REQUEST["mapuser"];
		$mediaList = getUserMedias($mapuser, "all");

		if ($mediaList)
		{
			foreach ($mediaList as $media )
			{
				$mediaRef = $media->media_ref;
				$mediaTitle = $media->title;
				$mediaDesc = $media->description;
				$mediaLink = getMediaLink($mediaRef);

				echo "<input type='radio' name='mediaselect' onclick='javascript:setMediaMarker(\"$mediaRef\");' value='$mediaRef' >";
				echo "<a href='$mediaLink' target='_blank' >" 
					. utf8_encode($mediaTitle) . "</a><br />";
				echo "</input>";
			}
		}
	}
	if ($mytask == "confirm_media_publish" && isset($_REQUEST["media"]) )
	{

		$mediaref = $_REQUEST["media"];
		$mediaInfo = getMediaInfo($mediaref);

		if ($mediaInfo)
		{
			if ($mediaInfo)
			{
				echo "<div name='mediainfo'>";
				echo utf8_encode($mediaInfo->title) . "<br /><a href='" 
					. getMediaLink($mediaref) ."' target='_blank' ><img src='" 
					. $mediaInfo->preview_url . "' width='160' height='120' /></a><br />";

				if ($mapmode == "publishmedia")
				echo "</div>";
			}
		}
	}
	elseif ($mytask == "get_media_html" && isset($_REQUEST["markerid"]))
	{

		$markerid = $_REQUEST["markerid"];
		$markerInfo = getMapMarkerInfo($markerid);

		if ($markerInfo)	
		{
			$mediaref = $markerInfo->media_ref;
			$mediaInfo = getMediaInfo($mediaref);
			$mapmode = $_REQUEST["mapmode"];

			if ($mediaInfo)
			{
				echo "<div name='mediainfo'>";
				echo utf8_encode($mediaInfo->title) . "<br /><a href='" 
					. getMediaLink($mediaref) ."' target='_blank' ><img src='" 
					. $mediaInfo->preview_url . "' /></a><br />";

				if ($mapmode == "publishmedia")
				{
				echo "<input type='button' value='" 
				. get_string("mediamap_delete_media","block_inwicast") 
				."' onclick='javascript:deleteVideoMarker($markerid);' />";
				}				
				echo "</div>";
			}
		}
	}
	elseif ($mytask == "delete_marker" && isset($_REQUEST["markerid"]))
	{
		$markerid = $_REQUEST["markerid"];
		deleteMapMarker($markerid);
	}
	elseif ($mytask == "create_marker" && isset($_REQUEST["media"]) && isset($_REQUEST["mapmedia"]))
	{

		$markerid = 0;

		if (isset($_REQUEST["lat"])) $maplat = $_REQUEST["lat"];
		if (isset($_REQUEST["lng"])) $maplng = $_REQUEST["lng"];
		if (isset($_REQUEST["zoom"])) $mapzoom = $_REQUEST["zoom"];
		
		$mapmediaref = $_REQUEST["mapmedia"];
		$mediaref = $_REQUEST["media"];

		$mediaInfo = getMediaInfo($mediaref); 

		$htmlbox = $mediaInfo->title . "<br/><a href='"
			. getMediaLink($mediaref) 
			. "' target='_blank' ><img border='3' src='" 
			. $mediaInfo->preview_url ."' width='160' height='120' /></a>";

		$markerid = addMediaToMap($mapmediaref, $mediaref, "new video", $htmlbox, $maplat, $maplng);

		echo $markerid;

	}
	elseif ($mytask == "update_marker_pos" && isset($_REQUEST["markerid"]) && isset($_REQUEST["markerlat"]) && isset($_REQUEST["markerlng"]))
	{

		$markerlat = $_REQUEST["markerlat"];
		$markerlng = $_REQUEST["markerlng"];
		$markerid = $_REQUEST["markerid"];

		$markerid = updateMapMarkerPos($markerid, $markerlng, $markerlat);


	}
	elseif ($mytask == "show_user_maps" && isset($_REQUEST["mapuser"]))
	{
		$mapclient = "publisher";
		$mapuser = $_REQUEST["mapuser"];

		if (isset($_REQUEST["mapclient"])) $mapclient = $_REQUEST["mapuser"];

		$mediaList = getUserMaps($mapuser);

		if ($mediaList)
		{
			echo "<html xmlns='http://www.w3.org/1999/xhtml'>\n"
  				. "<head>\n"
    				. "<meta http-equiv='content-type' content='text/html; charset=utf-8'/>\n"
  				. "<head>\n"
				. "<body>\n";

			echo "<script type='text/javascript'>"
			."function showMap(mymedia){\n"
			."document.selectmapform.mapmedia.value = mymedia;\n"
			."document.selectmapform.submit();\n"
			."}\n"
			."</script>\n";

			echo "<form name='selectmapform' action='index.php' method='get' >";

			echo "<table>";
			foreach ($mediaList as $media )
			{
				$mediaRef = $media->media_ref;
				$mediaTitle = $media->title;
				$mediaDesc = $media->description;
				$mediaPreview = $media->preview_url;
				$mediaLink = getMediaLink($mediaRef);
				echo "<tr><td><a href='javascript:showMap(\"$mediaRef\");' >"
					. "<img src='$mediaPreview' width='120' height='90' /></a></td>";
				echo "<td><a href='javascript:showMap(\"$mediaRef\");' >" 
					. utf8_encode($mediaTitle) . "</a><br />"
					. $mediaDesc . "</tr>";


			}
			echo "</table>";
			echo "<input type='hidden' name='mapmode' value='publishmedia' />\n";
			echo "<input type='hidden' name='mapmedia'  />\n";
			echo "<input type='hidden' name='mapuser' value='$mapuser' />\n";
			echo "<input type='hidden' name='mapclient' value='$mapclient' />\n";
			echo "</form></body></html>";

		}

	}

}

?>
