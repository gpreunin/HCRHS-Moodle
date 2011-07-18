<?php 
/**
 * INWICAST Mediacenter for CLAROLINE
 *
 * @version 1.0.2
 *
 * @copyright (c) 2007 INWI SARL
 *
 * @license http://www.gnu.org/copyleft/gpl.html (GPL) GENERAL PUBLIC LICENSE
 *
 * @author INWI Team <dev@inwicast.com>
 */

require '../../../claroline/inc/claro_init_global.inc.php';
require '../../../claroline/inc/lib/user.lib.php';
require '../../../claroline/inc/lib/nusoap.php';

require_once '../lib/inwicast.lib.php';

$server = new soap_server();

$server->register('get_user_media_list',
                   array('user'   => 'xsd:string',
                         'password' => 'xsd:string',
                         'filtername'    => 'xsd:string',
                         'filtervalue'    => 'xsd:string' ) );

$server->service($HTTP_RAW_POST_DATA);

function get_user_media_list($user, $password, $filtername, $filtervalue)
{

	$res = getTeacherMedias($user, "all", $filtername, $filtervalue);

	return $res;

}


?>
