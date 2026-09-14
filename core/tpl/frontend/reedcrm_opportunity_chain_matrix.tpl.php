<?php
/* Copyright (C) 2023-2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/reedcrm_opportunity_chain_matrix.tpl.php
 * \ingroup reedcrm
 * \brief   Reusable fragment: opportunity->payment chain as a matrix (pieces = rows, projects = columns).
 *          Expects $matrixProjects (array of project objects: id, ref, title)
 *          and $matrixDocs (= reedcrm_get_pwa_projects_documents($ids, true)).
 *          Uses globals $conf/$langs; chain logic in reedcrm_compute_opportunity_chain().
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

$enabledPieces = [];
if (!empty($conf->global->REEDCRM_PWA_SHOW_OPP_AMOUNT))     $enabledPieces['montant']        = ['icon' => 'fas fa-wallet'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_PROPAL))         $enabledPieces['propal']         = ['icon' => 'fas fa-file-signature'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_COMMANDE))       $enabledPieces['commande']       = ['icon' => 'fas fa-shopping-cart'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_COMMANDE_FOURN)) $enabledPieces['commande_fourn'] = ['icon' => 'fas fa-shopping-bag'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_RECEPTION))      $enabledPieces['reception']      = ['icon' => 'fas fa-dolly'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_FACTURE_FOURN))  $enabledPieces['facture_fourn']  = ['icon' => 'fas fa-receipt'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_EXPEDITION))     $enabledPieces['expedition']     = ['icon' => 'fas fa-shipping-fast'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_FACTURE))        $enabledPieces['facture']        = ['icon' => 'fas fa-file-invoice-dollar'];
if (!empty($conf->global->REEDCRM_PWA_SHOW_PAYMENT))        $enabledPieces['payment']        = ['icon' => 'fas fa-coins'];

$matrixProjects = (isset($matrixProjects) && is_array($matrixProjects)) ? $matrixProjects : [];
$matrixDocs     = (isset($matrixDocs) && is_array($matrixDocs)) ? $matrixDocs : [];
$iconsOnly      = !empty($conf->global->REEDCRM_PWA_PIECES_ICONS_ONLY);

// Pre-compute the chain (state + issues) once per project.
$matrixChains = [];
foreach ($matrixProjects as $proj) {
    $matrixChains[$proj->id] = reedcrm_compute_opportunity_chain($matrixDocs[$proj->id] ?? []);
}

// Cancelled/refused statuses, per piece, to strike out the offending document.
$matrixBadStatuses = ['propal' => [3], 'commande' => [-1], 'facture' => [3]];

if (!empty($enabledPieces) && !empty($matrixProjects)) : ?>
    <div class="pwa-matrix-scroll">
        <table class="pwa-doc-matrix">
            <thead>
                <tr>
                    <th class="pwa-matrix-corner"><?php echo $langs->trans('PwaMatrixStepHeader'); ?></th>
                    <?php foreach ($matrixProjects as $proj) :
                        $projUrl   = DOL_URL_ROOT . '/projet/card.php?id=' . $proj->id;
                        $projTitle = trim((string) ($proj->title ?? '')); ?>
                        <th class="pwa-matrix-projhead">
                            <a href="<?php echo $projUrl; ?>" target="_blank"><?php echo dol_escape_htmltag($proj->ref); ?></a>
                            <?php if ($projTitle !== '') : ?>
                                <span class="pwa-matrix-projtitle" title="<?php echo dol_escape_htmltag($projTitle); ?>"><?php echo dol_escape_htmltag($projTitle); ?></span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enabledPieces as $key => $item) :
                    $label = $langs->trans('PwaPieceLabel_' . $key); ?>
                    <tr>
                        <th class="pwa-matrix-rowhead" title="<?php echo dol_escape_htmltag($label); ?>">
                            <i class="<?php echo $item['icon']; ?>"></i><?php echo $iconsOnly ? '' : dol_escape_htmltag($label); ?>
                        </th>
                        <?php foreach ($matrixProjects as $proj) :
                            $docs   = $matrixDocs[$proj->id] ?? [];
                            $piece  = $docs[$key] ?? null;
                            $all    = (is_array($piece) && !empty($piece['all'])) ? $piece['all'] : [];
                            $state  = $matrixChains[$proj->id][$key]['state'] ?? 'todo';
                            $issues = $matrixChains[$proj->id][$key]['issues'] ?? [];

                            $hasErr = false; $hasWarn = false; $issueMsgs = [];
                            foreach ($issues as $iss) {
                                if ($iss['level'] === 'err') { $hasErr = true; } else { $hasWarn = true; }
                                $issueMsgs[] = $iss['msg'];
                            }
                            $cls     = 'pwa-matrix-cell is-' . $state . ($hasErr ? ' has-err' : ($hasWarn ? ' has-warn' : ''));
                            $tooltip = $issueMsgs ? implode(' ; ', $issueMsgs) : ''; ?>
                            <td class="<?php echo $cls; ?>"<?php echo $tooltip ? ' title="' . dol_escape_htmltag($tooltip) . '"' : ''; ?>>
                                <?php if ($hasErr || $hasWarn) : ?><span class="pwa-matrix-badge<?php echo $hasErr ? ' err' : ''; ?>"><?php echo $hasErr ? '!' : '⚠'; ?></span><?php endif; ?>
                                <?php if (!empty($all)) : ?>
                                    <span class="pwa-matrix-count"><?php echo count($all); ?></span>
                                    <?php foreach ($all as $doc) :
                                        $isBad       = isset($matrixBadStatuses[$key], $doc['status']) && in_array((int) $doc['status'], $matrixBadStatuses[$key], true);
                                        $statusKey   = 'PwaPieceStatus_' . $key . '_' . (int) ($doc['status'] ?? 0);
                                        $statusTrans = $langs->trans($statusKey);
                                        $statusLabel = (!empty($doc['status']) && $statusTrans !== $statusKey) ? $statusTrans : '';
                                        $amountTxt   = ($doc['amount'] !== null) ? ' · ' . price($doc['amount'], 0, '', 11, -1, -1, 'auto') : ''; ?>
                                        <span class="pwa-matrix-doc<?php echo $isBad ? ' is-bad' : ''; ?>">
                                            <a href="<?php echo $doc['url']; ?>" target="_blank"><?php echo dol_escape_htmltag($doc['ref']) . $amountTxt; ?></a>
                                            <?php if ($statusLabel) : ?><span class="pwa-matrix-status"> · <?php echo dol_escape_htmltag($statusLabel); ?></span><?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
