<?php  

/**
 * INWICAST Mediacenter module (block) for MOODLE
 *
 * Upgrade DB script - adds new tables and fiedls to the database
 *
 * @version 1.03 - may  2008
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

function xmldb_block_inwicast_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

if ($result && $oldversion < 2008011400) {


    	/// Define field width to be added to inwi_medias
        $table = new XMLDBTable('inwi_medias');

        $field = new XMLDBField('width');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '480', 'status');
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('height');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '360', 'width');
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('is_downloadable');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'is_public');
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('map_lat');
        $field->setAttributes(XMLDB_TYPE_CHAR, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'module_ref');
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('map_lng');
        $field->setAttributes(XMLDB_TYPE_CHAR, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'map_lat');
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('map_type');
        $field->setAttributes(XMLDB_TYPE_CHAR, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 'G_NORMAL_MAP', 'map_lng');
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('map_zoom');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'map_type');
        $result = $result && add_field($table, $field);

	/// Define table inwi_map_medias to be created
        $table = new XMLDBTable('inwi_map_medias');

    	/// Adding fields to table inwi_map_medias
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('mediamap_ref', XMLDB_TYPE_CHAR, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('media_ref', XMLDB_TYPE_CHAR, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('label', XMLDB_TYPE_CHAR, '250', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('html', XMLDB_TYPE_TEXT, 'small', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('lat', XMLDB_TYPE_CHAR, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('lng', XMLDB_TYPE_CHAR, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('icon', XMLDB_TYPE_CHAR, '30', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 'default');
        $table->addFieldInfo('date_created', XMLDB_TYPE_DATETIME, null, XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('visible', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1');

    /// Adding keys to table inwi_map_medias
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for inwi_map_medias
        $result = $result && create_table($table);



}



    return $result;
}

?>
