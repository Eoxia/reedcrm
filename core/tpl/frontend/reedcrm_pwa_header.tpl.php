<?php
/**
 * \file    core/tpl/frontend/reedcrm_pwa_header.tpl.php
 * \ingroup reedcrm
 * \brief   Homogeneous top header for all App pages
 */

// We expect $pwaHeaderCenterHtml to be optionally defined by the parent script
// to inject page-specific indicators in the middle of the header.

?>
<div id="id-top" class="page-header-tabs" style="position: fixed; top: 0; left: 0; right: 0; z-index: 999; width: 100%; box-sizing: border-box; margin: 0; border-radius: 0; background-color: #ffffff; padding: 0 15px; height: 60px; border-bottom: 2px solid #3b82f6; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;">
    
    <!-- Left: Logo (grid col 1 — justify-self: start keeps it on the left) -->
    <a href="<?php echo dol_buildpath('/custom/reedcrm/view/frontend/pwa_home.php?source=pwa', 1); ?>" class="company-logo-wrapper" style="display: flex; align-items: center; text-decoration: none; justify-self: start;">
        <?php
        global $mysoc, $db, $conf, $user;
        if (empty($mysoc)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $mysoc = new Societe($db);
            $mysoc->setMysoc($conf);
        }
        $logoFile = '';
        if (!empty($mysoc->logo_squarred)) {
            $logoFile = 'logos/'.$mysoc->logo_squarred;
        } elseif (!empty($mysoc->logo)) {
            $logoFile = 'logos/'.$mysoc->logo;
        }
        if (!empty($logoFile)) {
            $logoUrl = DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode($logoFile);
            print '<img class="company-logo" src="'.$logoUrl.'" alt="Logo" style="max-height: 40px; max-width: 140px; object-fit: contain;">';
        }
        ?>
    </a>
    
    <!-- Center: Page Specific Indicators (grid col 2 — naturally centered) -->
    <div class="pwa-header-center" style="display: flex; align-items: center; justify-content: center; margin: 0 10px;">
        <?php 
        if (!empty($pwaHeaderCenterHtml)) {
            print $pwaHeaderCenterHtml;
        }
        ?>
    </div>

    <!-- Right: Person Filter + User Profile Badge (grid col 3 — justify-self: end keeps it on the right) -->
    <div style="display: flex; align-items: center; justify-self: end;">
    <?php
    // --- Person filter selector ---
    $pwaCurrentFilterUserId = getDolUserInt('REEDCRM_PWA_FILTER_USER_ID', 0, $user);
    $setFilterUrl = dol_buildpath('/custom/reedcrm/view/frontend/pwa_set_filter_user.php', 1);
    $currentPageUri = dol_escape_htmltag($_SERVER['REQUEST_URI']);

    // Fetch all active users via direct SQL (simple and reliable)
    $resqlUsers  = $db->query("SELECT rowid AS id, firstname, lastname, email, photo FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 AND entity IN (0, " . (int) $conf->entity . ") ORDER BY lastname, firstname");
    $pwaUserList = [];
    if ($resqlUsers) {
        while ($rowUser = $db->fetch_object($resqlUsers)) {
            $pwaUserList[] = $rowUser;
        }
    }
    ?>
    <?php
    // Build JS data array and resolve current label + avatar HTML via showphoto() (same as elsewhere)
    $pwaFormObj = new Form($db);

    $pwaUserData           = [['id' => 0, 'label' => 'Tout le monde', 'avatarHtml' => '<i class="fas fa-users" style="font-size:11px;color:#94a3b8;"></i>']];
    $pwaCurrentFilterLabel = 'Tout le monde';
    $pwaCurrentFilterAvatarHtml = '<i class="fas fa-users" style="font-size:11px;color:#94a3b8;"></i>';

    foreach ($pwaUserList as $pwaUserRow) {
        $fullName = trim($pwaUserRow->firstname . ' ' . $pwaUserRow->lastname);
        if (empty($fullName)) $fullName = 'User #' . $pwaUserRow->id;

        // Pass the real User object directly to showphoto() — same as everywhere else in the module
        $listAvatarHtml = $pwaFormObj->showphoto('userphoto', $pwaUserRow, 28, 28, 0, 'pwa-combo-avatar', 'small', 0);

        $pwaUserData[] = ['id' => (int) $pwaUserRow->id, 'label' => $fullName, 'avatarHtml' => $listAvatarHtml];

        if ((int) $pwaUserRow->id === $pwaCurrentFilterUserId) {
            $pwaCurrentFilterLabel      = $fullName;
            $pwaCurrentFilterAvatarHtml = $pwaFormObj->showphoto('userphoto', $pwaUserRow, 22, 22, 0, 'pwa-trigger-avatar', 'small', 0);
        }
        // Keep fallback label in sync with renamed "Tout le monde"
        if ((int) $pwaUserRow->id === $pwaCurrentFilterUserId && $pwaCurrentFilterUserId === 0) {
            $pwaCurrentFilterLabel = 'Tout le monde';
        }
    }
    $isFiltered = $pwaCurrentFilterUserId > 0;
    ?>
    <form id="pwa-filter-user-form" method="POST" action="<?php echo $setFilterUrl; ?>" style="display: flex; align-items: center; margin-right: 8px;">
        <input type="hidden" name="backtopage" value="<?php echo $currentPageUri; ?>">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="pwa_filter_user_id" id="pwa-filter-uid-val" value="<?php echo $pwaCurrentFilterUserId; ?>">

        <div id="pwa-user-combo" style="position: relative;" data-users="<?php echo htmlspecialchars(json_encode($pwaUserData), ENT_QUOTES, 'UTF-8'); ?>">
            <!-- Trigger: looks like a pill input -->
            <div id="pwa-filter-uid-trigger"
                style="display: flex; align-items: center; gap: 6px; padding: 4px 8px; border: 1.5px solid <?php echo $isFiltered ? '#3b82f6' : '#cbd5e0'; ?>; border-radius: 20px; background: <?php echo $isFiltered ? '#eff6ff' : '#fff'; ?>; cursor: text; max-width: 160px;">

                <!-- Avatar via showphoto() — identical rendering as the rest of the app -->
                <div class="pwa-filter-avatar-wrap" style="width: 22px; height: 22px; border-radius: 50%; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #e2e8f0;">
                    <?php echo $pwaCurrentFilterAvatarHtml; ?>
                </div>

                <!-- Text input -->
                <input type="text" id="pwa-filter-uid-input" autocomplete="off"
                    value="<?php echo dol_escape_htmltag($pwaCurrentFilterLabel); ?>"
                    style="border: none; outline: none; background: transparent; font-size: 12px; color: <?php echo $isFiltered ? '#1e40af' : '#94a3b8'; ?>; width: 95px; min-width: 0; cursor: text;">

                <!-- Clear button -->
                <i id="pwa-filter-uid-clear" class="fas fa-times" title="Retirer le filtre"
                    style="font-size: 10px; color: #94a3b8; cursor: pointer; flex-shrink: 0; display: <?php echo $isFiltered ? 'block' : 'none'; ?>;"></i>
            </div>

            <!-- Dropdown list -->
            <ul id="pwa-filter-uid-list"
                style="display: none; position: absolute; top: calc(100% + 4px); right: 0; min-width: 200px; max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); padding: 4px 0; margin: 0; list-style: none; z-index: 10000;">
            </ul>
        </div>
    </form>

    <?php
    $vcardUrl = '';
    if (getDolUserInt('USER_ENABLE_PUBLIC', 0, $user)) {
        $vcardUrl = $user->getOnlineVirtualCardUrl('', 'internal');
    }
    
    if ($vcardUrl) {
        $widgetTag = 'div';
        $widgetAttr = 'onclick="document.getElementById(\'vcard-modal\').style.display=\'flex\'"';
    } else {
        $widgetTag = 'a';
        $profileUrl = dol_buildpath('/user/virtualcard.php', 1) . '?id=' . $user->id;
        $widgetAttr = 'href="' . dol_escape_htmltag($profileUrl) . '" target="_blank" title="Cliquez ici pour activer votre carte de visite"';
    }
    ?>
    <<?php echo $widgetTag; ?> class="user-profile-widget" <?php echo $widgetAttr; ?> style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: #1e293b; text-decoration: none; padding: 4px 10px; border-radius: 8px; transition: background 0.2s; border: 1px solid transparent;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent';">
        <?php
        $formObj = new Form($db);
        $nativeAvatar = $formObj->showphoto('userphoto', $user, 0, 0, 0, 'custom-badge-avatar', 'small', 0);
        
        print '<div class="user-avatar-wrap" style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; background: transparent;">';
        print $nativeAvatar;
        print '</div>';
        
        if ($vcardUrl) {
            print '<i class="fas fa-qrcode" style="font-size: 22px; color: #64748b;"></i>';
        }
        ?>
    </<?php echo $widgetTag; ?>>
    </div><!-- end right col -->
</div>

<!-- VCard Modal -->
<?php if ($vcardUrl) { ?>
<div id="vcard-modal" class="wpeo-modal modal-vcard" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; display: none; align-items: center; justify-content: center;">
    <div class="modal-container" style="background: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); width: 95%; max-width: 480px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; animation: modalFadeIn 0.3s ease;">
        <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
            <h2 class="modal-title" style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">Carte de visite</h2>
            <div class="modal-close" onclick="document.getElementById('vcard-modal').style.display='none'" style="cursor: pointer; color: #64748b; font-size: 20px; line-height: 1;" onmouseover="this.style.color='#e74c3c';" onmouseout="this.style.color='#64748b';"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content" style="padding: 0; overflow-y: auto; flex-grow: 1; height: 75vh; background: #f1f5f9;">
            <iframe src="<?php echo dol_escape_htmltag($vcardUrl); ?>" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>
<?php } ?>

