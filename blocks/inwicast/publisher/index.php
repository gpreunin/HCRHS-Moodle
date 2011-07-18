<?php 

require_once("../../../config.php");
require_once( '../lib/inwicast.lib.php');

if (isset($_REQUEST["download"])) 
{
	$filename = $_REQUEST['download'];

	$fullpath = getInwicastSysDir() . "publisher/" . $filename;

	if (file_exists($fullpath))
	{
		downloadVideo($fullpath, $filename);
	}
	else
		echo "Le fichier est introuvable sur le serveur";

}


?>
