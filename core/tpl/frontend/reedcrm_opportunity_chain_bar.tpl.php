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
 * \file    core/tpl/frontend/reedcrm_opportunity_chain_bar.tpl.php
 * \ingroup reedcrm
 * \brief   Reusable fragment: opportunity->payment chain bar for ONE project.
 *          Expects $chainBarDocs (= reedcrm_get_pwa_projects_documents([$id])[$id], may be []).
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

if (!empty($enabledPieces)) :
    $projectDocs = (isset($chainBarDocs) && is_array($chainBarDocs)) ? $chainBarDocs : [];
    $chain       = reedcrm_compute_opportunity_chain($projectDocs);
    $iconsOnly   = !empty($conf->global->REEDCRM_PWA_PIECES_ICONS_ONLY);
    ?>
    <div class="pwa-doc-bar<?php echo $iconsOnly ? ' icons-only' : ''; ?>">
        <?php foreach ($enabledPieces as $key => $item) :
            $doc    = $projectDocs[$key] ?? null;
            $state  = $chain[$key]['state'] ?? 'todo';
            $issues = $chain[$key]['issues'] ?? [];
            $hasErr = false; $hasWarn = false; $issueMsgs = [];
            foreach ($issues as $iss) { if ($iss['level'] === 'err') { $hasErr = true; } else { $hasWarn = true; } $issueMsgs[] = $iss['msg']; }
            $label  = $langs->trans('PwaPieceLabel_' . $key);
            $statusLabel = '';
            if ($doc && !empty($doc['status'])) {
                $statusKey   = 'PwaPieceStatus_' . $key . '_' . (int) $doc['status'];
                $statusTrans = $langs->trans($statusKey);
                $statusLabel = ($statusTrans !== $statusKey) ? $statusTrans : '';
            }
            $valueText = $doc ? ($doc['ref'] . ($doc['amount'] !== null ? ' · ' . price($doc['amount'], 0, '', 11, -1, -1, 'auto') : '')) : 'NA';
            $tooltip = trim($label . ' · ' . $valueText . ($statusLabel ? ' · ' . $statusLabel : '') . ($issueMsgs ? ' — ' . implode(' ; ', $issueMsgs) : ''));
            $cls = 'pwa-doc-item is-' . $state . ($doc ? '' : ' na') . ($hasErr ? ' has-err' : ($hasWarn ? ' has-warn' : ''));
            ?>
            <div class="<?php echo $cls; ?>" title="<?php echo dol_escape_htmltag($tooltip); ?>">
                <?php if ($state === 'current') : ?><span class="pwa-doc-curtag"><?php echo $langs->trans('PwaCurrentStep'); ?></span><?php endif; ?>
                <?php if ($hasErr || $hasWarn) : ?><span class="pwa-doc-badge<?php echo $hasErr ? ' err' : ''; ?>"><?php echo $hasErr ? '!' : '⚠'; ?></span><?php endif; ?>
                <i class="<?php echo $item['icon']; ?>"></i>
                <span class="pwa-doc-label"><?php echo $label; ?> :</span>
                <?php if ($doc) : ?>
                    <a href="<?php echo $doc['url']; ?>" class="prevent-edit-click"><?php echo $doc['ref'] . ($doc['amount'] !== null ? ' - ' . price($doc['amount'], 0, '', 11, -1, -1, 'auto') : ''); ?><?php echo $statusLabel ? ' · ' . $statusLabel : ''; ?></a>
                <?php else : ?>
                    <span>NA</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
