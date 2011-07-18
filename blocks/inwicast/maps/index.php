<?php

/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * This script is for GoogleMap support
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

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <script type="text/javascript" src="prototype.js"></script>
<?php
		echo "<script src='http://maps.google.com/maps?file=api&amp;v=2&amp;key=" . getGoogleMapKey() 
		.  "' type='text/javascript'></script>";
?>
  </head>
  <body onunload="GUnload()">

<?php

		doMap();

function doMap()
{
	$maplng = "2.373047";
	$maplat = "28.835797";
	$mapzoom = "2";
	$mapid = "";
	$mapmedia = "nomedia";
	$mapurl = getInwicastWeb() ."maps/index.php";
	$mapajaxurl = getInwicastWeb() ."maps/map.php";
	$mapwidth = 630;
	$mapheight = 480;
	$maptype = "G_NORMAL_MAP";
	$mapmode = "showmap";
	$maptypecontrol = "yes";
	$mapmovecontrol = "yes";
	$mapcanscroll = "yes";
	$mapshowmarkers = "yes";
	$mapclient = "server";

	if (isset($_REQUEST["lat"])) $maplat = $_REQUEST["lat"];
	if (isset($_REQUEST["lng"])) $maplng = $_REQUEST["lng"];
	if (isset($_REQUEST["zoom"])) $mapzoom = $_REQUEST["zoom"];
	if (isset($_REQUEST["map"])) $mapid = $_REQUEST["map"];
	if (isset($_REQUEST["media"])) $mediaref = $_REQUEST["media"];
	if (isset($_REQUEST["mapmedia"])) $mapmediaref = $_REQUEST["mapmedia"];

	if (isset($_REQUEST["markersfile"])) $markersfile = $_REQUEST["markersfile"];
	if (isset($_REQUEST["mapwidth"])) $mapwidth = $_REQUEST["mapwidth"];
	if (isset($_REQUEST["mapheight"])) $mapheight = $_REQUEST["mapheight"];
	if (isset($_REQUEST["mapuser"])) $mapuser = $_REQUEST["mapuser"];
	if (isset($_REQUEST["maptype"])) $maptype = $_REQUEST["maptype"];
	if (isset($_REQUEST["mapmode"])) $mapmode = $_REQUEST["mapmode"];// showmap,createmap,publishmedia
	if (isset($_REQUEST["maptypecontrol"])) $maptypecontrol = $_REQUEST["maptypecontrol"];
	if (isset($_REQUEST["mapmovecontrol"])) $mapmovecontrol = $_REQUEST["mapmovecontrol"];
	if (isset($_REQUEST["mapcanscroll"])) $mapcanscroll = $_REQUEST["mapcanscroll"];
	if (isset($_REQUEST["mapshowmarkers"])) $mapshowmarkers = $_REQUEST["mapshowmarkers"];
	if (isset($_REQUEST["mapclient"])) $mapclient = $_REQUEST["mapclient"];

	if ($mapclient == "publisher")
	{
		$mapInfo = getMediaInfo($mapmediaref);
		if ($mapInfo)
		{
			$maplat = $mapInfo->map_lat;
			$maplng = $mapInfo->map_lng;
			$maptype = $mapInfo->map_type;
			$mapzoom = $mapInfo->map_zoom;
		}
	}


	if ($mapmode == "publishmedia")
	{
		$mediaInfo = getMediaInfo($mediaref); 
		
		if ($mediaInfo)
			$htmlbox = "<a href='". getMediaLink($mediaref) ."' target='_blank' ><img border='3' src='" . $mediaInfo['preview_url'] ."' width='160' height='120' /></a>";
		else
			$htmlbox = "test";
			
			$addmarkerbox = "\"<table><tr><td>"
			.  utf8_encode(get_string("mediamap_select_media_to_publish","block_inwicast")) . "<br />"
			. "<input type='button' value='" . get_string("save","block_inwicast") . "'"
			. " onclick='javascript:addVideoMarker();' />"
			. "<input type='button' value='" . get_string("cancel","block_inwicast") . "'"
			. " onclick='javascript:map.closeInfoWindow();' />"
			."</td></tr><tr><td align='center'>"
			."<iframe src='$mapajaxurl?mapmedia=". $mapmediaref 
			."&media=nomedia&mapuser=" . $mapuser
			."&mapmode=publishmedia&task=get_user_medias'"
			." width='256' height='150'"
			." frameborder='0' scrolling='yes'"
			." marginwidth='0' marginheight='0' />"
			."</td></tr></table>"
			."\"";

			$addpublishermarkerbox = "\"<table><tr><td>"
			.  utf8_encode(get_string("mediamap_confirm_media_publish","block_inwicast")) . "<br />"
			. "<input type='button' value='" . get_string("save","block_inwicast") . "'"
			. " onclick='javascript:addVideoMarker();' />"
			. "<input type='button' value='" . get_string("cancel","block_inwicast") . "'"
			. " onclick='javascript:map.closeInfoWindow();' />"
			."</td></tr><tr><td align='center'>"
			."<iframe src='$mapajaxurl?mapmedia=". $mapmediaref 
			."&media=\" + document.iform.imedia.value + \"&mapuser=" . $mapuser
			."&mapmode=publishmedia&task=confirm_media_publish'"
			." width='256' height='150'"
			." frameborder='0' scrolling='yes'"
			." marginwidth='0' marginheight='0' />"
			."</td></tr></table>"
			."\"";

		$markerinfoparams = "'mapmode=publishmedia&task=get_media_html&markerid=' + mymarkerid";

	}
	else
		$markerinfoparams = "'mapmode=showmedia&task=get_media_html&markerid=' + mymarkerid";



    	echo "<div id='map_canvas' style='width: " . $mapwidth . "px; height: ".$mapheight ."px'></div>";
?>

    <script type="text/javascript">
 
      function deleteVideoMarker(markerid) {
	var myparams = "task=delete_marker&markerid=" + markerid; 
	new Ajax.Request(<?php echo "'$mapajaxurl'"; ?>,{method: 'get', parameters: myparams});
	map.removeOverlay(currentmarker);       
      }


      function addVideoMarker() {
        document.iform.ievent.value = "1";
	var mymapmedia = <?php echo "'$mapmediaref'"; ?>;
	var myparams = "task=create_marker&media=" + document.iform.imedia.value + "&lng=" + document.iform.ilng.value + "&lat=" +  document.iform.ilat.value + "&mapmedia=" + mymapmedia;
	new Ajax.Request(<?php echo "'$mapajaxurl'"; ?>,{method: 'get', 
		parameters: myparams,
		onSuccess: function(transport){
				var markerid = transport.responseText;
				if (markerid > 0)
				{
				var point = new GLatLng(document.iform.ilat.value,document.iform.ilng.value);
				//var html = "<?php echo $htmlbox; ?>";
				var label = "Media " + document.iform.imedia.value;
				var html = "Media " + document.iform.imedia.value;
				var marker = createMarker(point,markerid, label,html);
				map.addOverlay(marker);
				}
				else{ alert("Can not publish this media.");}
					

			}
	});

	map.closeInfoWindow();

      }

      var gmarkers = [];
      var gmarkerids = [];
      var nbmarkers = 0;
      var currentmarker;

      function createMarker(point,mediaref,label,html) {
        var marker = new GMarker(point 
<?php 
	if ($mapmode == "publishmedia") 
		echo ", {title: label, draggable: true}"; 
	else
		echo ", {title: label}";
?>);

        GEvent.addListener(marker, "dragend", function() 
	{
		var mypoint = marker.getLatLng();

		var myparams = "task=update_marker_pos&markerid=" + getMarkerId(marker);
		myparams += "&markerlat=" + mypoint.y.toFixed(6) + "&markerlng=" + mypoint.x.toFixed(6)
		new Ajax.Request(<?php echo "'$mapajaxurl'"; ?>,{method: 'get', parameters: myparams});

	});

        GEvent.addListener(marker, "click", function() {

	var mymarker = 0;
	var html = "";

	currentmarker = marker;

	mymarkerid = getMarkerId(marker)

	if (mymarkerid > 0)
	{
		var myparams = <?php echo $markerinfoparams; ?>;
		var myajax = new Ajax.Request(<?php echo "'$mapajaxurl'"; ?>,{
			method: 'get', 
			parameters: myparams,
			onSuccess: function(transport){
				var myresponse = transport.responseText;
				currentmarker.openInfoWindowHtml(myresponse);
			}
		});

	        

	}

        });
        gmarkers[nbmarkers] = marker;
	currentmarker = marker;
        gmarkerids[nbmarkers] = mediaref;
       nbmarkers++;
        return marker;
      }

      // This function picks up the click and opens the corresponding info window
      function myclick(i) {
        GEvent.trigger(gmarkers[i], "click");
      }

	function getMarkerId(marker)
	{
		var mymarkerid = 0;
		for (i=0;i < nbmarkers;i++)
		{

			if (marker == gmarkers[i])
			{

				mymarkerid = gmarkerids[i];
			
				break;
			}
		}
		return mymarkerid;
	}

		
      if (GBrowserIsCompatible()) {

        var map = new GMap2(document.getElementById("map_canvas"));
<?php
        echo "map.setCenter(new GLatLng($maplat, $maplng), $mapzoom);\n";
        echo "map.setMapType($maptype);\n";
        echo "map.addMapType(G_PHYSICAL_MAP);\n";

	if ($mapmode == "publishmedia")
	{
	        echo "GEvent.addListener(map,'click', function(overlay, point) {\n" 
			."document.iform.ilng.value = point.x.toFixed(6);\n"
	  		."document.iform.ilat.value = point.y.toFixed(6);\n"
	  		."document.iform.izoom.value =  map.getZoom();\n";
		if ($mapclient =="publisher")
          		echo "var myHtml = $addpublishermarkerbox;\n";
		else
          		echo "var myHtml = $addmarkerbox;\n";

       		echo "map.openInfoWindow(point, myHtml);\n";
        	echo "});\n";
	}
	else
	{
?>
        GEvent.addListener(map,"moveend", function() {   
	  var mycenter = map.getCenter();
	  document.iform.ilng.value = mycenter.x.toFixed(6);
	  document.iform.ilat.value = mycenter.y.toFixed(6);
	  document.iform.izoom.value =  map.getZoom();
        });

        GEvent.addListener(map,"zoomend", function() {   
	  var mycenter = map.getCenter();
	  document.iform.ilng.value = mycenter.x.toFixed(6);
	  document.iform.ilat.value = mycenter.y.toFixed(6);
	  document.iform.izoom.value =  map.getZoom();
        });

        GEvent.addListener(map,"maptypechanged", function() {   
	  var mymaptype = map.getCurrentMapType();
	  if (mymaptype == G_SATELLITE_MAP)
	  	document.iform.imaptype.value =  'G_SATELLITE_MAP';
	  else if (mymaptype == G_HYBRID_MAP)	
	  	document.iform.imaptype.value =  'G_HYBRID_MAP';
	  else if (mymaptype == G_PHYSICAL_MAP)	
	  	document.iform.imaptype.value =  'G_PHYSICAL_MAP';
	  else	
	  	document.iform.imaptype.value =  'G_NORMAL_MAP';
        });
<?php
	}

        if ($mapmovecontrol == "yes") echo "map.addControl(new GLargeMapControl());\n";
	if ($maptypecontrol == "yes") echo "map.addControl(new GMapTypeControl());\n";

	// Load markers for this map

	if ($mapshowmarkers =="yes")
	{
		$mediaList = getMapMedias($mapmediaref);

		if ($mediaList)
		{
			foreach ($mediaList as $media)
			{
				$mhtml = "";
				$mlat = $media->lat;
				$mlng = $media->lng;
				$mid = $media->id;
				$mlabel = str_replace("'", "\'", utf8_encode($media->title));

	?>
		var html = <?php echo "\"" . $mhtml . "\""; ?>;
	<?php
		echo "var marker = createMarker(new GLatLng('$mlat','$mlng'),'$mid','$mlabel',html);\n"
		   			. "map.addOverlay(marker);\n";

			}
		}
	}
?>


      }

    </script>

<?php
	echo "<form name='iform'>"
		."<input type='hidden' name='ilat' value='$maplat' />"
		."<input type='hidden' name='ilng' value='$maplng' />"
		."<input type='hidden' name='izoom' value='$mapzoom' />"
		."<input type='hidden' name='ifile' value='$markersfile' />"
		."<input type='hidden' name='imedia' value='' />"
		."<input type='hidden' name='imapmedia' value='$mapmediaref' />"
		."<input type='hidden' name='imaptype' value='$maptype' />"
		."<input type='hidden' name='ievent' value='0' />"
		."</form>";
}
?>
  </body>
</html>

