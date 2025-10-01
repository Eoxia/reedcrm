<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    class/reedcrmcron.class.php
 * \ingroup reedcrm
 * \brief   Class file for manage ReedcrmCron
 */

/**
 * Class for ReedcrmCron
 */
class ReedcrmCron
{
    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var string Last output from end job execution
     */
    public string $output = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Update all notation object contacts (Cronjob)
     *
     * @param  string    $className Object className
     * @param  string    $filter    Filter
     * @return int                  0 < if KO, > 0 if OK
     * @throws Exception
     */
    public function updateNotationObjectContacts(string $className, string $filter = ''): int
    {
        global $conf, $langs;

        // Load Dolibarr libraries
        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';

        // Load Saturne libraries
        require_once __DIR__ . '/../../saturne/lib/object.lib.php';

        // Load ReedCRM libraries
        require_once __DIR__ . '/../lib/reedcrm_function.lib.php';

        $objects = saturne_fetch_all_object_type($className, '', '', 0, 0, ['customsql' => 't.entity = ' . $conf->entity . ' ' . $filter]);

        if (is_array($objects) &&!empty($objects)) {
            foreach ($objects as $object) {
                $result = set_notation_object_contact($object);
                if ($result < 0) {
                    return -1;
                }
            }
            $this->output = $langs->transnoentities('NotationObjectContactsUpdated', count($objects),  $langs->transnoentities($className . 'Mins'));
        } else {
            $this->output = $langs->transnoentities('NoObject', $langs->transnoentities($className . 'Min'));
        }
        return 0;
    }
}
