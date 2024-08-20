<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    core/triggers/interface_99_modEasycrm_EasycrmTriggers.class.php
 * \ingroup tinyurl
 * \brief   EasyCRM trigger
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

// Load EasyCRM libraries
require_once __DIR__ . '/../../lib/easycrm_function.lib.php';

/**
 * Class of triggers for EasyCRM module
 */
class InterfaceEasyCRMTriggers extends DolibarrTriggers
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
        $this->description = 'EasyCRM triggers';
        $this->version     = '1.4.0';
        $this->picto       = 'easycrm@easycrm';
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
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf): int
    {
        if (!isModEnabled('easycrm')) {
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
                if (!empty($object->fk_product) && getDolGlobalInt('EASYCRM_PRODUCTKIT_DESC_ADD_LINE_PROPAL') > 0) {
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
