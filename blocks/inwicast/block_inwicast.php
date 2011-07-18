<?PHP 

/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * @version 1.03 - may 2008
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

 require_once("lib/inwicast.lib.php");


class block_inwicast extends block_list {
    function init() {
        $this->title = get_string('mediacenter','block_inwicast');
        $this->version = 2008051400;
    }
    
    function has_config() {
        return true;
    }

    function get_content() {

        global $CFG, $COURSE;

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

 
        $this->content = new object();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

	$courseid = $COURSE->id;

	$mediaref = "";
	
	$mediaInfo = getCourseLastMedia($courseid);

	if ($mediaInfo)
	{
		if (mediaIsLive($mediaInfo->status))
			$previewimage = getInwicastWeb() . "images/live.jpg";

		else
			$previewimage = $mediaInfo->preview_url;
		
		
			$lastmedia = "<div align='center'><br/>" . get_string("last_video","block_inwicast") 
			."<a href='" . $CFG->wwwroot."/blocks/inwicast/view.php?id=".$courseid
			. "&cmd=show_media&mediaRef=" . $mediaInfo->media_ref ."' >"
			."<img src='" . $previewimage . "' width='160' height='120' /></a><br/>"
			."<a href='" . $CFG->wwwroot."/blocks/inwicast/view.php?id=".$courseid
			. "&cmd=show_media&mediaRef=" . $mediaInfo->media_ref ."' >"
			. $mediaInfo->title . "</a></div>";

	}
	else
		$lastmedia ="";		

        $this->content->items[] = '<img src="' . $CFG->wwwroot.'/blocks/inwicast/images/icon.gif" class="icon" alt="" /><a title="'.get_string('course_videos','block_inwicast').'" href="'.
                                  $CFG->wwwroot.'/blocks/inwicast/index.php?id='.$courseid.'">'
				.get_string('course_videos','block_inwicast').'</a>' . $lastmedia;
        //$this->content->icons[] = '';

        return $this->content;
    }

   function applicable_formats() {
        return array('all' => true, 'my' => false);
    }

}

?>
