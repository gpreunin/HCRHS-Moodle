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

    $id = required_param('id', PARAM_INT);   // course

    if (! $course = get_record("course", "id", $id)) {
        error("Wrong course ID");
    }

if (isset($_REQUEST["rssFeed"]))
{

	header('Content-Type: application/rss+xml; charset=utf-8');

	$userid = $_REQUEST["rssFeed"];

	$title = get_string("course_videos", "block_inwicast") . " " . $course->shortname . " - " . $course->fullname;

	$rsslinkurl = htmlspecialchars(getInwicastWeb()."?rssFeed=".$userid."&id=" .$id);

	$rsspl = "<?xml version='1.0' encoding='utf-8'?>\n"
	. "<rss version='2.0' xmlns:media='http://search.yahoo.com/mrss'>\n"
	. "<channel>\n"
	. "<title>" . $title . "</title>\n"
	. "<link>" . $rsslinkurl ."</link>\n"
	. "<description></description>\n"
	. "<lastBuildDate>" . date("d/m/Y") . "</lastBuildDate>\n"
	. "<copyright>Copyright " . date("Y") . "</copyright>\n";

	$mediaList = getCourseMedias($id, "all");

	foreach ($mediaList as $mymedia )
	{
		$mediaRef = $mymedia->media_ref;
		$userName = $mymedia->firstname . " " . $mymedia->lastname;
		$mediaTitle = stripslashes($mymedia->title);
		$mediaDesc = stripslashes($mymedia->description);
		$mediaDate = $mymedia->media_date;
		$previewUrl = $mymedia->preview_url;
		$mediaViewCount = $mymedia->viewed;

		$podcastlinkurl = getInwicastWeb()."medias?video=" .$mediaRef;			

		$rsspl = $rsspl . "<item>"
		. "<title>" . $mediaTitle . "</title>\n"
		. "<link>" . $podcastlinkurl . "</link>\n"
		. "<author>" . $userName . "</author>\n"
		. "<description>"
		."<![CDATA["
		. "<table><tr><td><a href='". $podcastlinkurl ."'>"
		. "<img align='left' width='160' height='120' src='". $previewUrl 
		. "' style='border: 1px solid black'></a></td><td>" . $mediaDesc
		. "</td></tr></table>"
		. "]]>"
		. "</description>\n"
		. "<guid>" . $mediaRef . "</guid>\n"
		. "<pubDate>". $mediaDate ."</pubDate>\n";

		if (hasMP4FullVideo($mediaRef))
			$rsspl = $rsspl . "<enclosure url='" .  getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef .  ".mp4") . "' type='video/mp4' />\n";
		elseif (hasMP3FullVideo($mediaRef))
			$rsspl = $rsspl . "<enclosure url='" .  getMediaContentUrl($mediaRef, "/multimedia/" . $mediaRef .  ".mp3") . "' type='audio/mpeg' />\n";

		$rsspl = $rsspl . "<media:thumbnail url='" . $previewUrl . "' width='320' height='240'/>\n"
		. "</item>";

			
	}

	$rsspl = $rsspl . "</channel>\n";
	$rsspl = $rsspl . "</rss>\n";

	echo $rsspl;

}

?>
