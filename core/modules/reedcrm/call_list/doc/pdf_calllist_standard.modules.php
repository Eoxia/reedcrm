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
    /** @var string */
    public $name = 'Standard';
    /** @var string */
    public $description = 'Standard PDF template for call list';
    /** @var string */
    public $version = '1.0';
    /** @var DoliDB */
    protected $db;
    /** @var string */
    public $error = '';

    // ── Color palette ────────────────────────────────────────────────────────
    private $cNavy   = [26,  45,  64];
    private $cTeal   = [0,   157, 170];
    private $cGray   = [108, 117, 125];
    private $cLight  = [242, 245, 248];
    private $cWhite  = [255, 255, 255];
    private $cBlack  = [30,  30,  30];

    // ── Layout ───────────────────────────────────────────────────────────────
    private $mLeft    = 15;
    private $mRight   = 15;
    private $pWidth   = 180;  // 210 − 15 − 15
    private $rowH     = 10;   // data row height (mm)
    private $footerY  = 272;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $langs, $user;

        if (empty($conf->reedcrm->multidir_output[$conf->entity])) {
            $this->error = 'Missing output directory';
            return 0;
        }

        $outputlangs->loadLangs(['reedcrm@reedcrm', 'dict']);

        $dir  = $conf->reedcrm->multidir_output[$conf->entity] . '/call_list';
        $file = $dir . '/' . dol_sanitizeFileName($object->ref) . '.pdf';

        if (!file_exists($dir) && dol_mkdir($dir) < 0) {
            $this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
            return 0;
        }

        $pdf = pdf_getInstance([0, 0]);
        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        $pdf->SetCreator('Dolibarr ' . DOL_VERSION);
        $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
        $pdf->SetTitle($outputlangs->transnoentities('CallList') . ' ' . $object->ref);
        $pdf->SetMargins($this->mLeft, 15, $this->mRight);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('P', 'A4');

        $y       = 15;
        $pageNum = 1;

        $y = $this->drawHeader($pdf, $object, $outputlangs, $y);
        $y = $this->drawInfoGrid($pdf, $object, $outputlangs, $y);
        $y = $this->drawSectionBanner($pdf, $outputlangs->transnoentities('CallListLines'), $y);
        $y = $this->drawTableHeader($pdf, $outputlangs, $y);

        require_once __DIR__ . '/../../../../../class/calllistline.class.php';
        $lineObj = new CallListLine($this->db);
        $lines   = $lineObj->fetchAllByCallList($object->id);
        $contact = new Contact($this->db);
        $fillRow = false;

        foreach ($lines as $line) {
            if ($y + $this->rowH > $this->footerY - 5) {
                $this->drawFooter($pdf, $outputlangs, $pageNum);
                $pdf->AddPage('P', 'A4');
                $pageNum++;
                $y       = 15;
                $fillRow = false;
                $y       = $this->drawTableHeader($pdf, $outputlangs, $y);
            }

            $lastname  = '';
            $firstname = '';
            $phone     = '';
            $sourceRef = '';

            if (!empty($line->fk_contact)) {
                $contact->fetch($line->fk_contact);
                $lastname  = $contact->lastname;
                $firstname = $contact->firstname;
                $phone     = $contact->phone_pro ?: $contact->phone_mobile ?: '';
            }

            if ($line->element_type === 'propal' && isModEnabled('propale')) {
                require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
                $p = new Propal($this->db);
                if ($p->fetch($line->element_id) > 0) {
                    $sourceRef = $p->ref;
                }
            } elseif ($line->element_type === 'project' && isModEnabled('projet')) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                $p = new Project($this->db);
                if ($p->fetch($line->element_id) > 0) {
                    $sourceRef = $p->ref;
                }
            }

            $this->drawDataRow($pdf, $outputlangs, $y, $line, $lastname, $firstname, $phone, $sourceRef, $fillRow);
            $y      += $this->rowH;
            $fillRow = !$fillRow;
        }

        if (empty($lines)) {
            $this->fill($pdf, $this->cLight);
            $this->text($pdf, $this->cGray);
            $pdf->SetFont('', 'I', 9);
            $pdf->SetXY($this->mLeft, $y);
            $pdf->Cell($this->pWidth, 12, $outputlangs->transnoentities('NoCallListLine'), 0, 1, 'C', true);
        }

        $this->drawFooter($pdf, $outputlangs, $pageNum);
        $pdf->Output($file, 'F');

        return 1;
    }

    // ── Section drawers ───────────────────────────────────────────────────────

    private function drawHeader($pdf, $object, $outputlangs, $y)
    {
        // Navy banner
        $this->fill($pdf, $this->cNavy);
        $pdf->Rect($this->mLeft, $y, $this->pWidth, 14, 'F');
        $this->text($pdf, $this->cWhite);
        $pdf->SetFont('', 'B', 13);
        $pdf->SetXY($this->mLeft, $y);
        $pdf->Cell($this->pWidth, 14, strtoupper($outputlangs->transnoentities('CallList')), 0, 0, 'C');
        $y += 16;

        // Ref (large) ── left    Status ── right
        $this->text($pdf, $this->cNavy);
        $pdf->SetFont('', 'B', 14);
        $pdf->SetXY($this->mLeft, $y);
        $pdf->Cell(110, 8, $object->ref, 0, 0, 'L');

        $statusLabel = strip_tags($object->LibStatut($object->status, 0));
        $this->text($pdf, $this->cGray);
        $pdf->SetFont('', '', 9);
        $pdf->SetXY($this->mLeft + 110, $y);
        $pdf->Cell(70, 8, $statusLabel, 0, 0, 'R');
        $y += 8;

        // Label
        if (!empty($object->label)) {
            $this->text($pdf, $this->cGray);
            $pdf->SetFont('', '', 10);
            $pdf->SetXY($this->mLeft, $y);
            $pdf->Cell($this->pWidth, 7, $object->label, 0, 0, 'L');
            $y += 7;
        }

        // Teal separator
        $this->draw($pdf, $this->cTeal);
        $pdf->SetLineWidth(0.7);
        $pdf->Line($this->mLeft, $y + 1, $this->mLeft + $this->pWidth, $y + 1);
        $y += 5;

        return $y;
    }

    private function drawInfoGrid($pdf, $object, $outputlangs, $y)
    {
        $h      = 10;
        $labelW = 35;
        $valueW = 55;
        $col2X  = $this->mLeft + $labelW + $valueW;

        $userAssign = null;
        if (!empty($object->fk_user_assign)) {
            $userAssign = new User($this->db);
            $userAssign->fetch($object->fk_user_assign);
        }

        $rows = [
            [
                'l1' => $outputlangs->transnoentities('Label'),
                'v1' => $object->label ?: '—',
                'l2' => $outputlangs->transnoentities('AssignedTo'),
                'v2' => $userAssign ? $userAssign->getFullName($outputlangs) : '—',
            ],
            [
                'l1' => $outputlangs->transnoentities('DateStart'),
                'v1' => !empty($object->date_start) ? dol_print_date($object->date_start, 'day', 'tzuser') : '—',
                'l2' => $outputlangs->transnoentities('DateEnd'),
                'v2' => !empty($object->date_end)   ? dol_print_date($object->date_end,   'day', 'tzuser') : '—',
            ],
        ];

        foreach ($rows as $row) {
            // Label col 1
            $this->fill($pdf, $this->cLight);
            $this->text($pdf, $this->cGray);
            $pdf->SetFont('', 'B', 8);
            $pdf->SetXY($this->mLeft, $y);
            $pdf->Cell($labelW, $h, $row['l1'], 0, 0, 'L', true);

            // Value col 1
            $this->fill($pdf, $this->cWhite);
            $this->text($pdf, $this->cBlack);
            $pdf->SetFont('', '', 9);
            $pdf->SetXY($this->mLeft + $labelW, $y);
            $pdf->Cell($valueW, $h, $row['v1'], 0, 0, 'L', true);

            // Label col 2
            $this->fill($pdf, $this->cLight);
            $this->text($pdf, $this->cGray);
            $pdf->SetFont('', 'B', 8);
            $pdf->SetXY($col2X, $y);
            $pdf->Cell($labelW, $h, $row['l2'], 0, 0, 'L', true);

            // Value col 2
            $this->fill($pdf, $this->cWhite);
            $this->text($pdf, $this->cBlack);
            $pdf->SetFont('', '', 9);
            $pdf->SetXY($col2X + $labelW, $y);
            $pdf->Cell($valueW, $h, $row['v2'], 0, 0, 'L', true);

            $y += $h;
        }

        return $y + 5;
    }

    private function drawSectionBanner($pdf, $title, $y)
    {
        $this->fill($pdf, $this->cNavy);
        $pdf->Rect($this->mLeft, $y, $this->pWidth, 9, 'F');
        $this->text($pdf, $this->cWhite);
        $pdf->SetFont('', 'B', 10);
        $pdf->SetXY($this->mLeft + 3, $y);
        $pdf->Cell($this->pWidth - 6, 9, strtoupper($title), 0, 0, 'L');
        return $y + 11;
    }

    private function drawTableHeader($pdf, $outputlangs, $y)
    {
        $this->fill($pdf, $this->cTeal);
        $this->text($pdf, $this->cWhite);
        $pdf->SetFont('', 'B', 9);

        foreach ($this->cols($outputlangs) as $col) {
            $pdf->SetXY($col['x'], $y);
            $pdf->Cell($col['w'], 9, $col['label'], 0, 0, $col['align'], true);
        }

        return $y + 9;
    }

    private function drawDataRow($pdf, $outputlangs, $y, $line, $lastname, $firstname, $phone, $sourceRef, $fill)
    {
        $bg = $fill ? $this->cLight : $this->cWhite;

        $colDefs = $this->cols($outputlangs);
        $values  = [$lastname, $firstname, $phone, $sourceRef, ''];

        foreach ($colDefs as $i => $col) {
            if ($col['key'] === 'status') {
                $label = strip_tags($line->LibStatut((int) $line->status, 0));
                $this->fill($pdf, $bg);
                $this->text($pdf, $this->cBlack);
                $pdf->SetFont('', '', 9);
                $pdf->SetXY($col['x'], $y);
                $pdf->Cell($col['w'], $this->rowH, $label, 0, 0, 'C', true);
            } elseif ($col['key'] === 'phone') {
                // Bold phone
                $this->fill($pdf, $bg);
                $this->text($pdf, $this->cNavy);
                $pdf->SetFont('', 'B', 9);
                $pdf->SetXY($col['x'], $y);
                $pdf->Cell($col['w'], $this->rowH, $values[$i], 0, 0, 'L', true);
            } else {
                $this->fill($pdf, $bg);
                $this->text($pdf, $this->cBlack);
                $pdf->SetFont('', '', 9);
                $pdf->SetXY($col['x'], $y);
                $pdf->Cell($col['w'], $this->rowH, $values[$i], 0, 0, 'L', true);
            }
        }
    }

    private function drawFooter($pdf, $outputlangs, $pageNum)
    {
        $this->draw($pdf, [200, 200, 200]);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($this->mLeft, $this->footerY, $this->mLeft + $this->pWidth, $this->footerY);

        $y = $this->footerY + 2;
        $this->text($pdf, $this->cGray);
        $pdf->SetFont('', '', 7);

        $pdf->SetXY($this->mLeft, $y);
        $pdf->Cell(90, 5, 'ReedCRM · ' . $outputlangs->transnoentities('CallList'), 0, 0, 'L');

        $pdf->SetXY($this->mLeft + 90, $y);
        $pdf->Cell(90, 5, $outputlangs->transnoentities('Page') . ' ' . $pageNum, 0, 0, 'R');
    }

    // ── Column definitions ────────────────────────────────────────────────────

    private function cols($outputlangs)
    {
        $defs = [
            ['key' => 'lastname',  'label' => $outputlangs->transnoentities('Lastname'),  'w' => 38, 'align' => 'L'],
            ['key' => 'firstname', 'label' => $outputlangs->transnoentities('Firstname'), 'w' => 38, 'align' => 'L'],
            ['key' => 'phone',     'label' => $outputlangs->transnoentities('Phone'),     'w' => 50, 'align' => 'L'],
            ['key' => 'source',    'label' => $outputlangs->transnoentities('Source'),    'w' => 28, 'align' => 'L'],
            ['key' => 'status',    'label' => $outputlangs->transnoentities('Status'),    'w' => 26, 'align' => 'C'],
        ];

        $x = $this->mLeft;
        foreach ($defs as &$col) {
            $col['x'] = $x;
            $x       += $col['w'];
        }

        return $defs;
    }

    // ── Color helpers ─────────────────────────────────────────────────────────

    private function fill($pdf, array $rgb)
    {
        $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
    }

    private function text($pdf, array $rgb)
    {
        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    }

    private function draw($pdf, array $rgb)
    {
        $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    }
}
