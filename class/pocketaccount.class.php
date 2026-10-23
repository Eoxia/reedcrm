<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/pocketaccount.class.php
 * \ingroup reedcrm
 * \brief   Pocket account a recording is read from: the personal one of a user, or the shared one.
 */

require_once __DIR__ . '/pocketapi.class.php';

/**
 * Class PocketAccount.
 *
 * Every salesperson carries their own dictaphone, hence their own Pocket account: the key and the
 * imported folder are first looked for on the user (llx_user_param, set from the Pocket tab of the
 * user card). The account of the module setup stays as the shared one, used by whoever has not set
 * a key of their own, the way a team sharing a single dictaphone works.
 *
 * The personal settings are kept per entity like the module setup: the folders of a Pocket account
 * often split the companies of a group, and each entity imports its own.
 */
class PocketAccount
{
    /**
     * @var string Name of the API key, as a constant of the shared account and as a user parameter.
     */
    public const PARAM_API_KEY = 'REEDCRM_POCKET_API_KEY';

    /**
     * @var string Name of the imported folder id.
     */
    public const PARAM_FOLDER_ID = 'REEDCRM_POCKET_FOLDER_ID';

    /**
     * @var string Name of the imported folder label.
     */
    public const PARAM_FOLDER_LABEL = 'REEDCRM_POCKET_FOLDER_LABEL';

    /**
     * @var DoliDB Database handler.
     */
    public DoliDB $db;

    /**
     * @var int Owner of the account, 0 for the shared account of the module setup.
     */
    public int $fk_user = 0;

    /**
     * @var string API key, in clear.
     */
    public string $apiKey = '';

    /**
     * @var string Imported Pocket folder id.
     */
    public string $folderId = '';

    /**
     * @var string Imported Pocket folder label, denormalised so the pages stay readable offline.
     */
    public string $folderLabel = '';

    /**
     * Constructor.
     *
     * @param DoliDB $db     Database handler.
     * @param int    $fkUser Owner of the account, 0 for the shared account.
     */
    public function __construct(DoliDB $db, int $fkUser = 0)
    {
        $this->db      = $db;
        $this->fk_user = max(0, $fkUser);
    }

    /**
     * Get the shared account of the module setup.
     *
     * @param  DoliDB $db Database handler.
     * @return self       Shared account, possibly not configured.
     */
    public static function getShared(DoliDB $db): self
    {
        $account = new self($db);

        // getDolGlobalString() hands the key back decrypted: dolibarr_set_const() encrypts the
        // constants named *_KEY
        $account->apiKey      = getDolGlobalString(self::PARAM_API_KEY);
        $account->folderId    = getDolGlobalString(self::PARAM_FOLDER_ID);
        $account->folderLabel = getDolGlobalString(self::PARAM_FOLDER_LABEL);

        return $account;
    }

    /**
     * Get the personal account of a user.
     *
     * @param  DoliDB $db     Database handler.
     * @param  int    $fkUser User id.
     * @return self           Personal account, possibly not configured.
     */
    public static function getPersonal(DoliDB $db, int $fkUser): self
    {
        global $conf;

        if (!isset($conf->cache['reedcrmPocketAccounts'][$fkUser])) {
            $account = new self($db, $fkUser);
            if ($fkUser > 0) {
                $params = self::readUserParams($db, $fkUser);
                $account->fill($params[$fkUser] ?? []);
            }

            $conf->cache['reedcrmPocketAccounts'][$fkUser] = $account;
        }

        return $conf->cache['reedcrmPocketAccounts'][$fkUser];
    }

    /**
     * Get the account a user works with: their own when they set a key, the shared one otherwise.
     *
     * A personal key without folder is still the user's account. Falling back to the shared one
     * there would attach the calls of someone else to the cards of the user.
     *
     * @param  DoliDB $db   Database handler.
     * @param  User   $user User.
     * @return self         Account of the user.
     */
    public static function getForUser(DoliDB $db, User $user): self
    {
        $personal = self::getPersonal($db, (int) $user->id);

        return $personal->hasApiKey() ? $personal : self::getShared($db);
    }

    /**
     * Get the account a mirrored recording was imported from.
     *
     * Pocket only answers the detail and the audio of a recording to the account holding it: the
     * owner stored on the recording decides the key, whoever is looking at the card.
     *
     * @param  DoliDB          $db        Database handler.
     * @param  PocketRecording $recording Mirrored recording.
     * @return self                       Account of the recording.
     */
    public static function getForRecording(DoliDB $db, PocketRecording $recording): self
    {
        $fkUser = (int) $recording->fk_user_pocket;

        return $fkUser > 0 ? self::getPersonal($db, $fkUser) : self::getShared($db);
    }

    /**
     * Get every account ready to be synchronised: the shared one and the personal ones.
     *
     * @param  DoliDB $db Database handler.
     * @return self[]     Accounts holding both a key and a folder.
     */
    public static function getAllConfigured(DoliDB $db): array
    {
        $accounts = [];

        $shared = self::getShared($db);
        if ($shared->isConfigured()) {
            $accounts[] = $shared;
        }

        foreach (self::readUserParams($db) as $fkUser => $params) {
            $account = new self($db, $fkUser);
            $account->fill($params);
            if ($account->isConfigured()) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    /**
     * Get the users who set a personal key, configured or not.
     *
     * @param  DoliDB $db Database handler.
     * @return self[]     Personal accounts, by user id.
     */
    public static function getAllPersonal(DoliDB $db): array
    {
        $accounts = [];

        foreach (self::readUserParams($db) as $fkUser => $params) {
            $account = new self($db, $fkUser);
            $account->fill($params);
            if ($account->hasApiKey()) {
                $accounts[$fkUser] = $account;
            }
        }

        return $accounts;
    }

    /**
     * Get the users working with the account, those its recordings come from.
     *
     * A personal account is the dictaphone of its owner. The shared one is used by every active user
     * allowed to write recordings who did not set a key of their own, the way getForUser() hands it out.
     *
     * @return int[] User ids.
     */
    public function getUserIds(): array
    {
        if (!$this->isShared()) {
            return [$this->fk_user];
        }

        $personalAccounts = self::getAllPersonal($this->db);

        $userIds = [];

        $sql  = 'SELECT u.rowid FROM ' . MAIN_DB_PREFIX . 'user as u';
        $sql .= ' WHERE u.statut = 1 AND u.entity IN (' . getEntity('user') . ')';
        $sql .= ' ORDER BY u.rowid';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $userIds;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $userId = (int) $obj->rowid;
            if (isset($personalAccounts[$userId])) {
                continue;
            }

            $candidate     = new User($this->db);
            $candidate->id = $userId;
            $candidate->loadRights('reedcrm');
            if ($candidate->hasRight('reedcrm', 'pocketrecording', 'write')) {
                $userIds[] = $userId;
            }
        }
        $this->db->free($resql);

        return $userIds;
    }

    /**
     * Tell whether the account is the shared one of the module setup.
     *
     * @return bool True for the shared account.
     */
    public function isShared(): bool
    {
        return $this->fk_user <= 0;
    }

    /**
     * Get the name the account is shown with in the messages.
     *
     * @return string Name of the owner, or the wording of the shared account.
     */
    public function getName(): string
    {
        global $langs;

        if ($this->isShared()) {
            return $langs->trans('PocketSharedAccount');
        }

        $owner = new User($this->db);

        return $owner->fetch($this->fk_user) > 0 ? $owner->getFullName($langs) : '#' . $this->fk_user;
    }

    /**
     * Tell whether an API key is set.
     *
     * @return bool True when a key is set.
     */
    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Tell whether the account can be synchronised: key and folder set.
     *
     * Without folder the synchronisation deliberately does nothing, importing the whole account
     * would flood the list.
     *
     * @return bool True when both the key and the folder are set.
     */
    public function isConfigured(): bool
    {
        return $this->hasApiKey() && $this->folderId !== '';
    }

    /**
     * Get an API client authenticated with the key of the account.
     *
     * @return PocketApi API client.
     */
    public function getApi(): PocketApi
    {
        return new PocketApi($this->db, $this->apiKey);
    }

    /**
     * Save the API key.
     *
     * @param  string $apiKey Key in clear.
     * @return int            > 0 if OK, < 0 if KO.
     */
    public function setApiKey(string $apiKey): int
    {
        $this->apiKey = $apiKey;

        if ($this->isShared()) {
            return $this->writeConsts([self::PARAM_API_KEY => $apiKey]);
        }

        // Encrypted like the constants named *_KEY, which llx_user_param would not do on its own
        require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';

        return $this->writeUserParams([self::PARAM_API_KEY => $apiKey !== '' ? dolEncrypt($apiKey) : '']);
    }

    /**
     * Save the imported folder.
     *
     * @param  string $folderId    Pocket folder id, empty to clear it.
     * @param  string $folderLabel Pocket folder label.
     * @return int                 > 0 if OK, < 0 if KO.
     */
    public function setFolder(string $folderId, string $folderLabel): int
    {
        $this->folderId    = $folderId;
        $this->folderLabel = $folderId !== '' ? $folderLabel : '';

        $params = [self::PARAM_FOLDER_ID => $this->folderId, self::PARAM_FOLDER_LABEL => $this->folderLabel];

        return $this->isShared() ? $this->writeConsts($params) : $this->writeUserParams($params);
    }

    /**
     * Forget the key and the folder of the account.
     *
     * @return int > 0 if OK, < 0 if KO.
     */
    public function delete(): int
    {
        $this->apiKey      = '';
        $this->folderId    = '';
        $this->folderLabel = '';

        $params = [self::PARAM_API_KEY => '', self::PARAM_FOLDER_ID => '', self::PARAM_FOLDER_LABEL => ''];

        return $this->isShared() ? $this->writeConsts($params) : $this->writeUserParams($params);
    }

    /**
     * Fill the account from the user parameters read in the database.
     *
     * @param  array<string,string> $params Parameter name => stored value.
     * @return void
     */
    private function fill(array $params): void
    {
        $apiKey = (string) ($params[self::PARAM_API_KEY] ?? '');
        if ($apiKey !== '') {
            require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
            $apiKey = (string) dolDecrypt($apiKey);
        }

        $this->apiKey      = $apiKey;
        $this->folderId    = (string) ($params[self::PARAM_FOLDER_ID] ?? '');
        $this->folderLabel = (string) ($params[self::PARAM_FOLDER_LABEL] ?? '');
    }

    /**
     * Read the Pocket parameters of the users of the current entity.
     *
     * Read here rather than from $user->conf: main.inc.php loads the parameters of the logged user
     * only, and the synchronisation walks every account.
     *
     * @param  DoliDB $db     Database handler.
     * @param  int    $fkUser User to read, 0 for every user.
     * @return array<int,array<string,string>> User id => parameter name => stored value.
     */
    private static function readUserParams(DoliDB $db, int $fkUser = 0): array
    {
        global $conf;

        $params = [];

        $sql  = 'SELECT up.fk_user, up.param, up.value FROM ' . MAIN_DB_PREFIX . 'user_param as up';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'user as u ON u.rowid = up.fk_user';
        $sql .= ' WHERE up.entity = ' . ((int) $conf->entity);
        $sql .= " AND up.param IN ('" . $db->escape(self::PARAM_API_KEY) . "', '" . $db->escape(self::PARAM_FOLDER_ID) . "', '" . $db->escape(self::PARAM_FOLDER_LABEL) . "')";
        if ($fkUser > 0) {
            $sql .= ' AND up.fk_user = ' . ((int) $fkUser);
        } else {
            // A disabled user keeps their parameters, their dictaphone is no longer synchronised
            $sql .= ' AND u.statut = 1';
        }

        $resql = $db->query($sql);
        if (!$resql) {
            return $params;
        }

        while ($obj = $db->fetch_object($resql)) {
            $params[(int) $obj->fk_user][(string) $obj->param] = (string) $obj->value;
        }
        $db->free($resql);

        return $params;
    }

    /**
     * Write constants of the shared account, an empty value removes the constant.
     *
     * @param  array<string,string> $consts Constant name => value to store.
     * @return int                          > 0 if OK, < 0 if KO.
     */
    private function writeConsts(array $consts): int
    {
        global $conf;

        require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

        foreach ($consts as $name => $value) {
            $result = $value !== '' ? dolibarr_set_const($this->db, $name, $value, 'chaine', 0, '', $conf->entity) : dolibarr_del_const($this->db, $name, $conf->entity);
            if ($result < 0) {
                return -1;
            }
        }

        return 1;
    }

    /**
     * Write parameters of the owner of the account, an empty value removes the parameter.
     *
     * @param  array<string,string> $params Parameter name => value to store.
     * @return int                          > 0 if OK, < 0 if KO.
     */
    private function writeUserParams(array $params): int
    {
        global $conf;

        require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

        $owner     = new User($this->db);
        $owner->id = $this->fk_user;

        $result = dol_set_user_param($this->db, $conf, $owner, $params, $conf->entity);

        unset($conf->cache['reedcrmPocketAccounts'][$this->fk_user]);

        return $result;
    }
}
