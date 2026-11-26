<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/newsletter/newsletter_admin_setup.tpl.php
 * \ingroup reedcrm
 * \brief   Settings of the newsletters on the setup page of ReedCRM
 *
 * Variables from admin/newsletter.php:
 * @var Translate $langs             Translations
 * @var string    $emailTemplatesUrl Setup of the email templates, filtered on the newsletters
 * @var string    $cronListUrl       List of the cron jobs, filtered on the newsletter one
 * @var int       $maxPerRun         Mails per run of the cron
 * @var string    $cronFrequency     How often the job runs, as a text
 * @var string[]  $deploymentWarnings What the installation misses: job, rights
 * @var array|null $cronLastRun      Last run of the job, see reedcrm_newsletter_cron_last_run()
 */
?>
<?php print load_fiche_titre($langs->trans('Config'), '', ''); ?>

<?php if (!isModEnabled('cron')) : ?>
    <div class="warning"><?php echo $langs->trans('NewsletterCronModuleDisabled'); ?></div>
<?php endif; ?>
<?php if (!isModEnabled('categorie')) : ?>
    <div class="warning"><?php echo $langs->trans('NewsletterTagsModuleDisabled'); ?></div>
<?php endif; ?>
<?php foreach ($deploymentWarnings as $deploymentWarning) : ?>
    <div class="warning"><?php echo $deploymentWarning; ?></div>
<?php endforeach; ?>

<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" name="newsletter_config">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="set_config">

    <table class="noborder centpercent">
        <tr class="liste_titre">
            <td><?php echo $langs->trans('Parameters'); ?></td>
            <td><?php echo $langs->trans('Description'); ?></td>
            <td class="center"><?php echo $langs->trans('Value'); ?></td>
        </tr>

        <tr class="oddeven">
            <td><?php echo $langs->trans('NewsletterUnsubscribeLinkSetting'); ?></td>
            <td><?php echo $langs->trans('NewsletterUnsubscribeLinkSettingDesc'); ?></td>
            <td class="center"><?php echo ajax_constantonoff('REEDCRM_NEWSLETTER_UNSUBSCRIBE_LINK'); ?></td>
        </tr>

        <tr class="oddeven">
            <td><?php echo $langs->trans('NewsletterAgendaEventSetting'); ?></td>
            <td><?php echo $langs->trans('NewsletterAgendaEventSettingDesc'); ?></td>
            <td class="center"><?php echo ajax_constantonoff('REEDCRM_NEWSLETTER_AGENDA_EVENT'); ?></td>
        </tr>

        <tr class="oddeven">
            <td><?php echo $langs->trans('NewsletterMaxPerRunSetting'); ?></td>
            <td><?php echo $langs->trans('NewsletterMaxPerRunSettingDesc', $cronFrequency); ?></td>
            <td class="center nowraponall">
                <input type="number" name="max_per_run" class="flat width75" min="1" step="1" value="<?php echo (int) $maxPerRun; ?>">
                <input type="submit" class="button button-save smallpaddingimp" value="<?php echo dol_escape_htmltag($langs->trans('Save')); ?>">
            </td>
        </tr>

        <tr class="oddeven">
            <td><?php echo $langs->trans('NewsletterTemplatesSetting'); ?></td>
            <td><?php echo $langs->trans('NewsletterTemplatesSettingDesc'); ?></td>
            <td class="center"><a href="<?php echo $emailTemplatesUrl; ?>"><?php echo img_picto('', 'email', 'class="pictofixedwidth"') . $langs->trans('NewsletterManageTemplates'); ?></a></td>
        </tr>

        <tr class="oddeven">
            <td><?php echo $langs->trans('NewsletterCronSetting'); ?></td>
            <td>
                <?php echo $langs->trans('NewsletterCronSettingDesc', $cronFrequency); ?>
                <?php if ($cronLastRun !== null) : ?>
                    <div class="opacitymedium reedcrm-newsletter-inline-help">
                        <?php echo $cronLastRun['last'] > 0 ? $langs->trans('NewsletterCronLastRun', dol_print_date($cronLastRun['last'], 'dayhour')) : $langs->trans('NewsletterCronNeverRun'); ?>
                        <?php if ($cronLastRun['last'] > 0 && $cronLastRun['result'] !== '' && $cronLastRun['result'] !== '0') : ?>
                            <span class="error"><?php echo $langs->trans('NewsletterCronLastRunError'); ?></span>
                        <?php endif; ?>
                        <?php if ($cronLastRun['next'] > 0) : ?>
                            · <?php echo $langs->trans('NewsletterCronNextRun', dol_print_date($cronLastRun['next'], 'dayhour')); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </td>
            <td class="center"><a href="<?php echo $cronListUrl; ?>"><?php echo img_picto('', 'cron', 'class="pictofixedwidth"') . $langs->trans('NewsletterCronOpen'); ?></a></td>
        </tr>
    </table>
</form>
