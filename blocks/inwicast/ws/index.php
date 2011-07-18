<html>
<body>
<?php 
/**
 * INWICAST Mediacenter for MOODLE
 *
 * @version 1.0.2
 *
*
 * @license http://www.gnu.org/copyleft/gpl.html (GPL) GENERAL PUBLIC LICENSE
 *
 * @author INWICAST Community <dev@inwicast.com>
 */
require_once("../../../config.php");

require_once("../lib/inwicast.lib.php");
require_once("../lib/nusoap.php");

//getWsImportUserVideos();


$username = "fmercier";
$password = "123456";
$filtername = "";
$filtervalue = "";

$paramList = array('user'   => $username,
                       'password' => $password,  
                       'filtername'    => $filtername, 
                       'filtervalue'    => $filtervalue    );

$urlDuService = 'http://www.mobiclasse.com/ws/index.php';
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
   
        echo '<h1 align="center">Media list</h1>'
            .'<pre>';

		foreach ($mediaList as $media )
		{
			echo $media['media_ref'] . "</ br>";
		}

        echo '</pre>';

    }
    else
    {
        // AUTHENTICATION FAILED
        echo '<center>Authentication failed</center>';
    }

?>
</body>
</html>
