<?php
/**
 * \file    core/tpl/frontend/reedcrm_pwa_header.tpl.php
 * \ingroup reedcrm
 * \brief   Homogeneous top header for all App pages
 */

// We expect $pwaHeaderCenterHtml to be optionally defined by the parent script
// to inject page-specific indicators in the middle of the header.

?>
<div id="id-top" class="page-header-tabs" style="position: fixed; top: 0; left: 0; right: 0; z-index: 999; width: 100%; box-sizing: border-box; margin: 0; border-radius: 0; background-color: #ffffff; padding: 0 15px; height: 60px; border-bottom: 2px solid #3b82f6; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;">
    
    <!-- Left: Logo -->
    <a href="<?php echo dol_buildpath('/custom/reedcrm/view/frontend/pwa_home.php?source=pwa', 1); ?>" class="company-logo-wrapper" style="display: flex; align-items: center; text-decoration: none;">
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
    
    <!-- Center: Page Specific Indicators -->
    <div class="pwa-header-center" style="display: flex; align-items: center; justify-content: center; flex: 1; margin: 0 15px;">
        <?php 
        if (!empty($pwaHeaderCenterHtml)) {
            print $pwaHeaderCenterHtml;
        }
        ?>
    </div>

    <!-- Right: User Profile Badge & VCard Trigger -->
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

<style>
    /* Global App layout alignment */
    body.template-pwa {
        padding: 0 !important;
        padding-top: 60px !important; /* Offset content exactly by the height of the fixed navbar */
        margin: 0 !important;
    }
    
    body.template-pwa .fiche,
    body.template-pwa #id-right,
    body.template-pwa #id-container {
        padding-top: 0 !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }
    
    @media (max-width: 1024px) {
        body.template-pwa {
            padding-top: 60px !important;
        }
        /* Compress Navigation Header (id-top) to fit sub-300px viepworts */
        #id-top {
            padding: 0 10px !important;
            gap: 5px !important; /* using flex gap for spacing elements inside the top bar */
            overflow-x: auto;
        }
        #id-top .company-logo {
            max-width: 60px !important;
        }
        #id-top .user-profile-widget {
            gap: 6px !important;
            padding: 2px 4px !important;
        }
    }
    
    /* Ensure user avatar is kept contained natively */
    .custom-badge-avatar {
        width: 100% !important;
        height: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        object-fit: contain !important;
        border: none !important;
    }
</style>
