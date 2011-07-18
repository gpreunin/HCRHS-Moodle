<script language="JavaScript" type="text/javascript">
function addOption(theSel, theText, theValue)
{
  var newOpt = new Option(theText, theValue);
  var selLength = theSel.length;
  theSel.options[selLength] = newOpt;
}

function deleteOption(theSel, theIndex)
{ 
  var selLength = theSel.length;
  if(selLength>0)
  {
    theSel.options[theIndex] = null;
  }
}
function moveOptions(theSelFrom, theSelTo)
{
  
  var selLength = theSelFrom.length;
  var selectedText = new Array();
  var selectedValues = new Array();
  var selectedCount = 0;
  
  var i;

  // Find the selected Options in reverse order
  // and delete them from the 'from' Select.
  for(i=selLength-1; i>=0; i--)
  {
    if(theSelFrom.options[i].selected)
    {
      selectedText[selectedCount] = theSelFrom.options[i].text;
      selectedValues[selectedCount] = theSelFrom.options[i].value;
      deleteOption(theSelFrom, i);
      selectedCount++;
    }
  }
  
  // Add the selected text/values in reverse order.
  // This will add the Options to the 'to' Select
  // in the same order as they were in the 'from' Select.
  for(i=selectedCount-1; i>=0; i--)
  {
    addOption(theSelTo, selectedText[i], selectedValues[i]);
  }
 
}

function selectAll(theSel)
{
  var pls = document.getElementById("pls");
  var i;

  pls.value = "";

  for(i=0; i<=theSel.length-1; i++)
  {
    pls.value = pls.value + theSel.options[i].value + ";";
  }
}
</script>
<?php
	/* Youtube Video Playlist Moodle Block
	 * Paul Holden, Greenhead College, 31st July 2007
	 * http://gcmoodle.greenhead.ac.uk/external/youtube/
	 * -------------------------------------------------
	 * tab_configblock.php
	 * Tab containing playlist creating options
	 */

	$videos_pl = array();
	$videos_av = array();
        $form_pl = array();
        if(!empty($this->config->pls)){         
          $form_pl = explode(';', $this->config->pls);
        }

	echo('<input type="hidden" name="pls" id="pls" value="' . implode(';', $form_pl) . '" />');

	$videos = get_records_select('block_youtube_video', 'courseid = ' . $this->instance->pageid . ' or shared = 1', 'title ASC');

	foreach ($videos as $video) {
		$array = 'videos_' . (in_array($video->id, $form_pl) ? 'pl' : 'av');
		array_push($$array, $video);
	}

	$table = new Object();
	$table->head = array(get_string('config_playlist', 'block_youtube_video'), '&nbsp;', get_string('config_available', 'block_youtube_video'));
	$table->tablealign = 'left';
	$table->width = '*';

	$select_playlist = '<select style="width:350px" name="playlist" id="playlist" size="15" multiple>';
	foreach ($videos_pl as $video) {
		$share = ($video->shared == 1 && $video->courseid != $this->instance->pageid ? ' (*)' : '');
		$select_playlist .= '<option value="' . $video->id . '">' . $video->title . $share . '</option>';
	}
	$select_playlist .= '</select>';

	$select_avail = '<select style="width:350px" name="available" id="available" size="15" multiple>';
	foreach ($videos_av as $video) {
		$share = ($video->shared == 1 && $video->courseid != $this->instance->pageid ? ' (*)' : '');
		$select_avail .= '<option value="' . $video->id . '">' . $video->title . $share . '</option>';
	}
	$select_avail .= '</select>';

	$buttons = '<input type="button" value="&larr;" onclick="moveOptions(this.form.available, this.form.playlist);" />' .
		   '<br />' .
		   '<input type="button" value="&rarr;" onclick="moveOptions(this.form.playlist, this.form.available);" />';

	$table->data[] = array($select_playlist, $buttons, $select_avail);
	$table->data[] = array();
	$table->data[] = array('<input type="submit" value="' . get_string('savechanges') . '" onclick="selectAll(this.form.playlist);" />', '&nbsp;', '(*) ' . get_string('config_shared', 'block_youtube_video'));

	print_table($table);

?>
