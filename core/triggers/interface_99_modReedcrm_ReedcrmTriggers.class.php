<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modReedcrm_ReedcrmTriggers.class.php
 * \ingroup tinyurl
 * \brief   ReedCRM trigger
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../../lib/reedcrm_function.lib.php';

/**
 * Class of triggers for ReedCRM module
 */
class InterfaceReedCRMTriggers extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;

        $this->name        = preg_replace('/^Interface/i', '', get_class($this));
        $this->family      = 'demo';
        $this->description = 'ReedCRM triggers';
        $this->version     = '22.1.0';
        $this->picto       = 'reedcrm@reedcrm';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName(): string
    {
        return parent::getName();
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc(): string
    {
        return parent::getDesc();
    }

    /**
     * Function called when a Dolibarr business event is done
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param  string       $action Event action code
     * @param  CommonObject $object Object
     * @param  User         $user   Object user
     * @param  Translate    $langs  Object langs
     * @param  Conf         $conf   Object conf
     * @return int                  0 < if KO, 0 if no triggered ran, >0 if OK
     * @throws Exception
     */
    private function tagStateEvent($elementType, $elementId, $statusTag, User $user, $label) 
    {
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
        $now = dol_now();
        $sql = "SELECT id FROM " . MAIN_DB_PREFIX . "actioncomm WHERE ";
        if ($elementType === 'project') {
            $sql .= "fk_project = " . (int)$elementId;
        } else {
            $sql .= "fk_element = " . (int)$elementId . " AND elementtype = '" . $this->db->escape($elementType) . "'";
        }
        $sql .= " ORDER BY datep DESC, id DESC LIMIT 1";

        $res = $this->db->query($sql);
        if ($res && $this->db->num_rows($res) > 0) {
            $obj = $this->db->fetch_object($res);
            $actioncomm = new ActionComm($this->db);
            if ($actioncomm->fetch($obj->id) > 0) {
                if ($actioncomm->datep >= ($now - 5)) {
                    $actioncomm->fetch_optionals();
                    $actioncomm->array_options['options_reedcrm_status_object'] = $statusTag;
                    $actioncomm->insertExtraFields('ACTIONCOMM_CUSTOM_OPTIONS'); // Ensure it writes
                    
                    // Direct SQL fallback just in case insertExtraFields has bugs with actioncomm context
                    $sqlExtra = "INSERT INTO " . MAIN_DB_PREFIX . "actioncomm_extrafields (fk_object, reedcrm_status_object) VALUES (" . (int)$actioncomm->id . ", '" . $this->db->escape($statusTag) . "') ON DUPLICATE KEY UPDATE reedcrm_status_object = '" . $this->db->escape($statusTag) . "'";
                    $this->db->query($sqlExtra);
                    return;
                }
            }
        }
        
        // Generate fallback technical actioncomm
        $actioncomm = new ActionComm($this->db);
        $actioncomm->type_code = 'AC_OTH_AUTO';
        $actioncomm->datep = $now;
        if ($elementType === 'project') {
            $actioncomm->fk_project = $elementId;
            $actioncomm->elementtype = 'project';
            $actioncomm->fk_element = $elementId; // Both for safety
        } else {
            $actioncomm->fk_element = $elementId;
            $actioncomm->elementtype = $elementType;
        }
        $actioncomm->userownerid = $user->id;
        $actioncomm->percentage = -1;
        $actioncomm->label = $label;
        $actioncomm->array_options['options_reedcrm_status_object'] = $statusTag;
        $rescreate = $actioncomm->create($user);
        if ($rescreate > 0) {
            $sqlExtra = "INSERT INTO " . MAIN_DB_PREFIX . "actioncomm_extrafields (fk_object, reedcrm_status_object) VALUES (" . (int)$rescreate . ", '" . $this->db->escape($statusTag) . "') ON DUPLICATE KEY UPDATE reedcrm_status_object = '" . $this->db->escape($statusTag) . "'";
            $this->db->query($sqlExtra);
        }
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf): int
    {
        if (!isModEnabled('reedcrm')) {
            return 0; // If module is not enabled, we do nothing
        }

        // Data and type of action are stored into $object and $action
        dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
        $now        = dol_now();
        $actioncomm = new ActionComm($this->db);

        $actioncomm->type_code   = 'AC_OTH_AUTO';
        $actioncomm->datep       = $now;
        $actioncomm->fk_element  = $object->id;
        $actioncomm->userownerid = $user->id;
        $actioncomm->percentage  = -1;

        switch ($action) {
            case 'BILL_CREATE' :
            case 'BILLREC_CREATE' :
                $object->fetch($object->id);
                set_notation_object_contact($object);
                break;
                
            // ReedCRM Object Status Extrafield Tracking
            case 'PROJECT_CREATE':   $this->tagStateEvent('project', $object->id, 'project_draft', $user, 'Projet créé (Brouillon)'); break;
            case 'PROJECT_VALIDATE': $this->tagStateEvent('project', $object->id, 'project_valid', $user, 'Projet validé (Ouvert)'); break;
            case 'PROJECT_CLOSE':    $this->tagStateEvent('project', $object->id, 'project_closed', $user, 'Projet clôturé'); break;
            
            case 'PROPAL_CREATE':          $this->tagStateEvent('propal', $object->id, 'propal_draft', $user, 'Proposition créée (Brouillon)'); break;
            case 'PROPAL_VALIDATE':        $this->tagStateEvent('propal', $object->id, 'propal_valid', $user, 'Proposition validée (Ouverte)'); break;
            case 'PROPAL_CLASSIFY_BILLED': $this->tagStateEvent('propal', $object->id, 'propal_billed', $user, 'Proposition classée facturée'); break;
            
            case 'PROPAL_CLOSE_SIGNED':
                $this->tagStateEvent('propal', $object->id, 'propal_signed', $user, 'Proposition signée');
                // Execute standard hook legacy code below
            case 'PROPAL_CLOSE_REFUSED':
                if ($action === 'PROPAL_CLOSE_REFUSED') {
                    $this->tagStateEvent('propal', $object->id, 'propal_notsigned', $user, 'Proposition refusée');
                }
                
                if (isset($_SESSION['LAST_ACTION_CREATED'])) {
                    $actioncomm->fetch($_SESSION['LAST_ACTION_CREATED']);
                    if ($actioncomm->id > 0 && $actioncomm->elementtype == 'propal' && !empty(GETPOST('note_private'))) {
                        $actioncomm->setValueFrom('note', GETPOST('note_private', 'alpha'), '', null, '', 'id');
                    }
                }
                break;
            case 'PROJECT_ADD_CONTACT':

                require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

                $contactType = getDictionaryValue('c_type_contact', 'code', GETPOST('typecontact'));

                if ($contactType == 'PROJECTADDRESS' || empty( GETPOST('typecontact'))) {
                    require_once __DIR__ . '/../../class/geolocation.class.php';

                    $contactID = GETPOST('contactid');
                    $contact   = new Contact($this->db);
                    $contact->fetch($contactID);

                    if (dol_strlen($contact->address) > 0) {
                        $geolocation   = new Geolocation($this->db);
                        $addressesList = $geolocation->getDataFromOSM($contact);

                        if (!empty($addressesList)) {
                            $address = $addressesList[0];

                            $geolocation->element_type = 'contact';
                            $geolocation->gis          = 'osm';
                            $geolocation->latitude     = $address->lat;
                            $geolocation->longitude    = $address->lon;
                            $geolocation->fk_element   = $contactID;
                            $geolocation->status       = Geolocation::STATUS_GEOLOCATED;
                            $geolocation->create($user);

                        } else {
                            $geolocation->status = Geolocation::STATUS_NOTFOUND;
                            $geolocation->create($user);
                        }
                        $contact->array_options['options_address_status'] = $geolocation->status;
                        $contact->updateExtraField('address_status');
                    }
                }
                break;
            case 'FACTURE_ADD_CONTACT' :
                $actioncomm->elementtype = $object->element;
                $actioncomm->code        = 'AC_' . strtoupper($object->element) . '_ADD_CONTACT';
                $actioncomm->label       = $langs->transnoentities('ObjectAddContactTrigger');
                $actioncomm->create($user);
                break;
            case 'USER_UPDATE_OBJECT_CONTACT' :
                $actioncomm->code   = 'AC_USER_UPDATE_OBJECT_CONTACT';
                $actioncomm->label  = $langs->transnoentities('UpdateObjectContactTrigger');
                $actioncomm->create($user);
                break;
            case 'USER_ADD_CONTACT_NOTIFICATION' :
                $actioncomm->code   = 'AC_USER_ADD_CONTACT_NOTIFICATION';
                $actioncomm->label  = $langs->transnoentities('AddContactNotificationTrigger');
                $actioncomm->create($user);
                break;
            case 'LINEPROPAL_INSERT' :
                if (!empty($object->fk_product) && getDolGlobalInt('REEDCRM_PRODUCTKIT_DESC_ADD_LINE_PROPAL') > 0) {
                    $product     = new Product($this->db);
                    $product->id = $object->fk_product;
                    $product->get_sousproduits_arbo();
                    if (!empty($product->sousprods) && is_array($product->sousprods) && count($product->sousprods)) {
                        $labelProductService   = '';
                        $tmpArrayOfSubProducts = reset($product->sousprods);
                        foreach ($tmpArrayOfSubProducts as $subProdVal) {
                            $productChild = new Product($this->db);
                            $productChild->fetch($subProdVal[0]);
                            $concatDesc          = dol_concatdesc('<b>' . $productChild->label . '</b>',$productChild->description);
                            $labelProductService = dol_concatdesc($labelProductService, $concatDesc);
                        }
                        $result = $object->setValueFrom('description', $labelProductService, '', '', '', '', $user, '', '');
                        if ($result < 0) {
                            $this->error   .= $object->error;
                            $this->errors[] = $object->error;
                            $this->errors   = array_merge($this->errors, $object->errors);
                            return -1;
                        }
                    }
                }
                break;
        }
        return 0;
    }
}
