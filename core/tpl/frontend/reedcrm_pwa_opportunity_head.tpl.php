<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/reedcrm_pwa_opportunity_head.tpl.php
 * \ingroup reedcrm
 * \brief   Reusable App banner for ONE opportunity: ref/amount/probability, title, person to call.
 *          Expects $project (Project) and $oppContact (reedcrm_get_project_contact_details() output).
 *          Set $pwaHeadCompact = true to get the inline round call button used by the relaunch form
 *          instead of the full width one used by the opportunity detail page.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $conf, $langs;

$pwaHeadCompact = !empty($pwaHeadCompact);
$oppPhone       = trim((string) $oppContact['phone']);
$oppFullName    = trim($oppContact['firstname'] . ' ' . $oppContact['lastname']);
?>
<div class="pwa-opp-head">
    <div class="pwa-call-ref">
        <?php echo $project->getNomUrl(1); ?>
        <?php if ($project->opp_amount !== null && $project->opp_amount !== '') : ?>
            <span class="pwa-call-sep">|</span>
            <strong><?php echo price($project->opp_amount, 0, $langs, 0, 0, -1, $conf->currency); ?></strong>
        <?php endif; ?>
        <?php if ($project->opp_percent !== null && $project->opp_percent !== '') : ?>
            <span class="pwa-call-sep">|</span>
            <span class="pwa-call-percent"><?php echo round((float) $project->opp_percent); ?> %</span>
        <?php endif; ?>
    </div>

    <div class="pwa-call-title"><?php echo dol_escape_htmltag($project->title); ?></div>

    <div class="pwa-opp-contact<?php echo $pwaHeadCompact ? ' pwa-opp-contact--compact' : ''; ?>">
        <?php if ($oppFullName !== '') : ?>
            <span class="pwa-call-name"><i class="fas fa-user"></i> <?php echo dol_escape_htmltag($oppFullName); ?></span>
        <?php else : ?>
            <span class="pwa-call-name pwa-call-name--empty"><i class="fas fa-user-slash"></i> <?php echo $langs->trans('ContactNotFilled'); ?></span>
        <?php endif; ?>

        <?php if ($pwaHeadCompact && $oppPhone !== '') : ?>
            <div class="pwa-opp-contact-actions">
                <a class="pwa-call-btn-call pwa-call-btn-call--round" href="tel:<?php echo dol_escape_htmltag($oppPhone); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('Call') . ' ' . $oppPhone); ?>"><i class="fas fa-phone"></i></a>
                <button type="button" class="pwa-call-btn-copy" data-action="copy-phone" data-phone="<?php echo dol_escape_htmltag($oppPhone); ?>" title="<?php echo dol_escape_htmltag($langs->trans('CopyPhoneNumber')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('CopyPhoneNumber')); ?>"><i class="fas fa-copy"></i></button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$pwaHeadCompact) : ?>
        <?php if ($oppPhone !== '') : ?>
            <div class="pwa-call-phone-row">
                <a class="pwa-call-btn-call" href="tel:<?php echo dol_escape_htmltag($oppPhone); ?>"><i class="fas fa-phone"></i> <?php echo dol_escape_htmltag($oppPhone); ?></a>
                <button type="button" class="pwa-call-btn-copy" data-action="copy-phone" data-phone="<?php echo dol_escape_htmltag($oppPhone); ?>" title="<?php echo dol_escape_htmltag($langs->trans('CopyPhoneNumber')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('CopyPhoneNumber')); ?>"><i class="fas fa-copy"></i></button>
            </div>
        <?php else : ?>
            <div class="pwa-call-phone-row">
                <span class="pwa-call-phone pwa-call-phone--empty"><i class="fas fa-phone-slash"></i> <?php echo $langs->trans('NoPhoneNumber'); ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
