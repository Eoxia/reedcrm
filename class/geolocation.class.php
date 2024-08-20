<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/geolocation.class.php
 * \ingroup easycrm
 * \brief   This file is a CRUD class file for Geolocation (Create/Read/Update/Delete)
 */

// Load Saturne libraries
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for Geolocation
 */
class Geolocation extends SaturneObject
{
    /**
     * @var string Module name
     */
    public $module = 'easycrm';

    /**
     * @var string Element type of object
     */
    public $element = 'geolocation';

    /**
     * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management
     */
    public $table_element = 'element_geolocation';

    /**
     * @var int Does this object support multicompany module ?
     * 0 = No test on entity, 1 = Test with field entity, 'field@table' = Test with link by field@table
     */
    public $ismultientitymanaged = 0;

    /**
     * @var int  Does object support extrafields ? 0=No, 1=Yes
     */
    public $isextrafieldmanaged = 0;

    /**
     * 'type' field format:
     *      'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
     *      'select' (list of values are in 'options'),
     *      'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]',
     *      'chkbxlst:...',
     *      'varchar(x)',
     *      'text', 'text:none', 'html',
     *      'double(24,8)', 'real', 'price',
     *      'date', 'datetime', 'timestamp', 'duration',
     *      'boolean', 'checkbox', 'radio', 'array',
     *      'mail', 'phone', 'url', 'password', 'ip'
     *      Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
     * 'label' the translation key.
     * 'picto' is code of a picto to show before value in forms
     * 'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM' or '!empty($conf->multicurrency->enabled)' ...)
     * 'position' is the sort order of field.
     * 'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty '' or 0.
     * 'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
     * 'noteditable' says if field is not editable (1 or 0)
     * 'default' is a default value for creation (can still be overwroted by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
     * 'index' if we want an index in database.
     * 'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     * 'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     * 'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
     * 'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
     * 'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
     * 'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     * 'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
     * 'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
     * 'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
     * 'comment' is not used. You can store here any text of your choice. It is not used by application.
     * 'validate' is 1 if you need to validate with $this->validateField()
     * 'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
     * 'size' limit the length of a fields
     *
     * Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
     */

    /**
     * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
     */
    public $fields = [
        'rowid'        => ['type' => 'integer',      'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1,  'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'comment' => 'Id'],
        'latitude'     => ['type' => 'double(24,8)', 'label' => 'Latitude',    'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 0, 'default' => 0],
        'longitude'    => ['type' => 'double(24,8)', 'label' => 'Longitude',   'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 0, 'default' => 0],
        'element_type' => ['type' => 'varchar(255)', 'label' => 'ElementType', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 0],
        'fk_element'   => ['type' => 'integer',      'label' => 'FkElement',   'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 0, 'index' => 1],
        'fk_address'   => ['type' => 'integer',      'label' => 'FkAddress',   'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 0, 'index' => 1],
    ];

    /**
     * @var int ID
     */
    public int $rowid;

    /**
     * @var float Latitude
     */
    public float $latitude = 0;

    /**
     * @var float Longitude
     */
    public float $longitude = 0;

    /**
     * @var string Element type
     */
    public string $element_type;

    /**
     * @var int Fk_element
     */
    public $fk_element;

    /**
     * @var int|null Fk_element
     */
    public ?int $fk_address;

    /**
     * Constructor
     *
     * @param DoliDb $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->element);
    }

    /**
     * Convert longitude and latitude format WGS 84 (EPSG:4326) to Web Mercator (EPSG:3857)
     *
     * @return object
     */
    public function convertCoordinates()
    {
        $convertFactor   = 6378137.0;
        $longitude       = $this->longitude / 180 * pi();
        $latitude        = $this->latitude / 180 * pi();
        $this->longitude = $convertFactor * $longitude;
        $this->latitude  = $convertFactor * log(tan(pi() / 4 + $latitude / 2));

        return $this;
    }

    /**
     * Inject map features
     *
     * @param  array $features   array of features: id, description, color, longitude, latitude
     * @param  int   $chunkSize size of chunk
     * @param  int   $deep
     * @return int
     */
    public function injectMapFeatures(array $features, int $chunkSize, int $deep = 0): int
    {
        $error = 0;

        if (!empty($features)) {
            $bulkFeatures = array_chunk($features, $chunkSize);
            foreach ($bulkFeatures as $bulk) {
                $encodedBulk = json_encode($bulk);
                if (!empty($encodedBulk)) {
                    print "geojsonMarkers.features = $.merge(geojsonMarkers.features, $encodedBulk);\n";
                } else {
                    if ($chunkSize > 1) {
                        $result = $this->injectMapFeatures($bulk, floor($chunkSize / 2), $deep + 1);
                        $result < 0 ? $error++ : '';
                    } else {
                        ob_start();
                        print_r($bulk);
                        $content = ob_get_contents();
                        ob_clean();
                        print "console.error('Map: Error encode json map feature, data:', '" . dol_escape_js($content, 1) . "');\n";
                        $error++;
                    }
                }
            }
        }

        return $error > 0 ? -1 : 0;
    }
}
