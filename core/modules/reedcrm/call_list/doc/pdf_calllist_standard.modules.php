<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/modules/reedcrm/call_list/doc/pdf_calllist_standard.modules.php
 * \ingroup reedcrm
 * \brief   PDF generation module for CallList — Standard template.
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once __DIR__ . '/../../modules_calllist.php';

/**
 * Class to generate PDF for CallList.
 */
class pdf_calllist_standard extends ModelePDFCallList
{
    /**
     * @var string Module name.
     */
    public string $name = 'Standard';

    /**
     * @var string Module description.
     */
    public string $description = 'Standard PDF template for call list';

    /**
     * @var string Module version.
     */
    public string $version = '1.0';

    /**
     * @var DoliDB $db Database handler.
     */
    protected DoliDB $db;

    /**
     * @var string Error string.
     */
    public string $error = '';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Generate PDF.
     *
     * @param  CallList  $object          CallList object
     * @param  Translate $outputlangs     Output language object
     * @param  string    $srctemplatepath Not used
     * @param  int       $hidedetails     0=show details, 1=hide
     * @param  int       $hidedesc        0=show desc, 1=hide
     * @param  int       $hideref         0=show ref, 1=hide
     * @return int                        1 if OK, <=0 if KO
     */
    public function write_file(CallList $object, Translate $outputlangs, string $srctemplatepath = '', int $hidedetails = 0, int $hidedesc = 0, int $hideref = 0): int
    {
        global $conf, $langs, $user;

        if (empty($conf->reedcrm->multidir_output[$conf->entity])) {
            $this->error = 'Bug 12123 ';
            return 0;
        }

        $outputlangs->loadLangs(['reedcrm@reedcrm', 'dict']);

        $dir      = $conf->reedcrm->multidir_output[$conf->entity] . '/call_list';
        $filename = dol_sanitizeFileName($object->ref) . '.pdf';
        $file     = $dir . '/' . $filename;

        if (!file_exists($dir)) {
            if (dol_mkdir($dir) < 0) {
                $this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
                return 0;
            }
        }

        $pdf = pdf_getInstance([0, 0]);
        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        $pdf->SetCreator(pdf_getDocCreator());
        $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
        $pdf->SetTitle($outputlangs->transnoentities('CallList') . ' ' . $object->ref);

        $pdf->SetMargins(10, 15, 10);
        $pdf->SetAutoPageBreak(true, 20);

        $pdf->AddPage('P', 'A4');

        $pageWidth = 190;
        $y         = 15;

        // Title
        $pdf->SetFont('', 'B', 14);
        $pdf->SetXY(10, $y);
        $pdf->MultiCell($pageWidth, 7, $outputlangs->transnoentities('CallList') . ' ' . $object->ref, 0, 'C');
        $y += 10;

        // Header block
        $pdf->SetFont('', '', 10);

        $pdf->SetXY(10, $y);
        $pdf->MultiCell(60, 6, $outputlangs->transnoentities('Label') . ' : ' . $object->label, 0, 'L');

        if (!empty($object->fk_user_assign)) {
            $userAssign = new User($this->db);
            $userAssign->fetch($object->fk_user_assign);
            $pdf->SetXY(75, $y);
            $pdf->MultiCell(65, 6, $outputlangs->transnoentities('AssignedTo') . ' : ' . $userAssign->getFullName($outputlangs), 0, 'L');
        }

        $pdf->SetXY(145, $y);
        $statusLabel = $object->LibStatut($object->status, 0);
        // Strip HTML tags for PDF output
        $pdf->MultiCell(55, 6, $outputlangs->transnoentities('Status') . ' : ' . strip_tags($statusLabel), 0, 'L');
        $y += 8;

        if (!empty($object->date_start)) {
            $pdf->SetXY(10, $y);
            $pdf->MultiCell(90, 6, $outputlangs->transnoentities('DateStart') . ' : ' . dol_print_date($object->date_start, 'day', 'tzuser'), 0, 'L');
        }

        if (!empty($object->date_end)) {
            $pdf->SetXY(105, $y);
            $pdf->MultiCell(95, 6, $outputlangs->transnoentities('DateEnd') . ' : ' . dol_print_date($object->date_end, 'day', 'tzuser'), 0, 'L');
        }

        $y += 12;

        // Table header
        $pdf->SetFont('', 'B', 9);
        $pdf->SetFillColor(200, 200, 200);

        $cols = [
            ['label' => $outputlangs->transnoentities('Lastname'),  'w' => 40],
            ['label' => $outputlangs->transnoentities('Firstname'), 'w' => 40],
            ['label' => $outputlangs->transnoentities('Phone'),     'w' => 50],
            ['label' => $outputlangs->transnoentities('Source'),    'w' => 30],
            ['label' => $outputlangs->transnoentities('Status'),    'w' => 30],
        ];

        $xCursor = 10;
        foreach ($cols as $col) {
            $pdf->SetXY($xCursor, $y);
            $pdf->Cell($col['w'], 7, $col['label'], 1, 0, 'C', true);
            $xCursor += $col['w'];
        }

        $y += 7;

        // Table rows
        $pdf->SetFont('', '', 8);
        $pdf->SetFillColor(240, 240, 255);

        require_once __DIR__ . '/../../../../../class/calllistline.class.php';

        $lineObj = new CallListLine($this->db);
        $lines   = $lineObj->fetchAllByCallList($object->id);
        $contact = new Contact($this->db);

        $fillRow = false;

        foreach ($lines as $line) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage('P', 'A4');
                $y = 15;
            }

            $lastname  = '';
            $firstname = '';
            $phone     = '';

            if (!empty($line->fk_contact)) {
                $contact->fetch($line->fk_contact);
                $lastname  = $contact->lastname;
                $firstname = $contact->firstname;
                $phone     = $contact->phone_pro ?: $contact->phone_mobile ?: '';
            }

            $sourceLabel = ($line->element_type === 'propal') ? $outputlangs->transnoentities('Proposal') : $outputlangs->transnoentities('Opportunity');
            $statusLabel = strip_tags($line->LibStatut($line->status, 0));

            $xCursor = 10;
            $rowData = [
                ['text' => $lastname,    'w' => 40],
                ['text' => $firstname,   'w' => 40],
                ['text' => $phone,       'w' => 50],
                ['text' => $sourceLabel, 'w' => 30],
                ['text' => $statusLabel, 'w' => 30],
            ];

            foreach ($rowData as $cell) {
                $pdf->SetXY($xCursor, $y);
                $pdf->Cell($cell['w'], 6, $cell['text'], 1, 0, 'L', $fillRow);
                $xCursor += $cell['w'];
            }

            $y += 6;
            $fillRow = !$fillRow;
        }

        $pdf->Output($file, 'F');

        return 1;
    }
}
