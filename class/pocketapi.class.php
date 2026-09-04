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
 * \file    class/pocketapi.class.php
 * \ingroup reedcrm
 * \brief   Client for the Pocket public API (https://docs.heypocketai.com/docs/api).
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/geturl.lib.php';

/**
 * Class PocketApi.
 *
 * Thin read-only client over the Pocket public API. Only the endpoints the module needs are
 * exposed: folders (to feed the admin selector), recordings and their detail, and the signed
 * audio URL. Every call is authenticated with the API key stored in the module configuration.
 */
class PocketApi
{
    /**
     * @var string Base URL of the Pocket public API.
     */
    public const BASE_URL = 'https://public.heypocketai.com/api/v1';

    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Last error message.
     */
    public $error = '';

    /**
     * @var int HTTP code of the last call.
     */
    public $httpCode = 0;

    /**
     * @var string API key used by this instance.
     */
    private $apiKey;

    /**
     * Constructor.
     *
     * @param DoliDB $db     Database handler.
     * @param string $apiKey Override of the configured key, used by the admin connection test.
     */
    public function __construct(DoliDB $db, string $apiKey = '')
    {
        $this->db     = $db;
        $this->apiKey = $apiKey !== '' ? $apiKey : getDolGlobalString('REEDCRM_POCKET_API_KEY');
    }

    /**
     * Tell whether an API key is available.
     *
     * @return bool True when a key is set.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get the folders and spaces of the account.
     *
     * The Pocket tree is returned nested, it is flattened here so the admin selector can show
     * every level with a single loop.
     *
     * @return array<int,array{id:string,label:string,depth:int}>|null Flattened folders, null on error.
     */
    public function getFolders(): ?array
    {
        $response = $this->call('GET', '/public/folders');
        if ($response === null) {
            return null;
        }

        return $this->flattenFolders($response['data'] ?? []);
    }

    /**
     * Get one page of recordings.
     *
     * @param  string $folderId Pocket folder id, empty to get every recording.
     * @param  int    $limit    Page size, capped by the API.
     * @param  int    $page     Page number, starting at 1.
     * @return array<string,mixed>|null                                Raw API payload, null on error.
     */
    public function getRecordings(string $folderId = '', int $limit = 100, int $page = 1): ?array
    {
        $query = ['limit' => $limit, 'page' => $page];
        if ($folderId !== '') {
            $query['folder_id'] = $folderId;
        }

        return $this->call('GET', '/public/recordings?' . http_build_query($query));
    }

    /**
     * Get the full detail of a recording: transcript, summary, mind map and action items.
     *
     * @param  string $pocketId Pocket recording id.
     * @return array<string,mixed>|null Raw API payload, null on error.
     */
    public function getRecording(string $pocketId): ?array
    {
        return $this->call('GET', '/public/recordings/' . rawurlencode($pocketId));
    }

    /**
     * Get a signed URL to download the audio file of a recording.
     *
     * The URL is short lived, it is never stored: it is resolved on demand when the user asks
     * for the audio.
     *
     * @param  string $pocketId Pocket recording id.
     * @return string           Signed URL, empty string on error.
     */
    public function getAudioUrl(string $pocketId): string
    {
        $response = $this->call('GET', '/public/recordings/' . rawurlencode($pocketId) . '/audio-url');

        return (string) ($response['data']['signed_url'] ?? '');
    }

    /**
     * Flatten the folder tree returned by Pocket.
     *
     * @param  array<int,array<string,mixed>> $folders Folder nodes.
     * @param  int                            $depth   Current depth, used for the indentation.
     * @return array<int,array{id:string,label:string,depth:int}> Flattened folders.
     */
    private function flattenFolders(array $folders, int $depth = 0): array
    {
        $flattened = [];

        foreach ($folders as $folder) {
            if (empty($folder['id'])) {
                continue;
            }

            $flattened[] = [
                'id'    => (string) $folder['id'],
                'label' => (string) ($folder['name'] ?? $folder['id']),
                'depth' => $depth
            ];

            if (!empty($folder['children']) && is_array($folder['children'])) {
                $flattened = array_merge($flattened, $this->flattenFolders($folder['children'], $depth + 1));
            }
        }

        return $flattened;
    }

    /**
     * Perform an authenticated call and decode the JSON answer.
     *
     * @param  string                    $method HTTP method.
     * @param  string                    $path   Path appended to the base URL, query string included.
     * @param  array<string,mixed>       $body   Body sent as JSON for the write methods.
     * @return array<string,mixed>|null          Decoded payload, null on error, $this->error is then set.
     */
    private function call(string $method, string $path, array $body = []): ?array
    {
        global $langs;

        $this->error    = '';
        $this->httpCode = 0;

        if (!$this->isConfigured()) {
            $this->error = $langs->trans('PocketApiKeyMissing');
            return null;
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json'
        ];

        $postOrGet = $method;
        $parameter = '';
        if (!empty($body)) {
            $headers[] = 'Content-Type: application/json';
            $postOrGet = $method . 'ALREADYFORMATED';
            $parameter = json_encode($body);
        }

        $result         = getURLContent(self::BASE_URL . $path, $postOrGet, $parameter, 1, $headers, ['https'], 0);
        $this->httpCode = (int) ($result['http_code'] ?? 0);

        if (!empty($result['curl_error_msg'])) {
            $this->error = $result['curl_error_msg'];
            dol_syslog('PocketApi::call ' . $path . ' curl error ' . $this->error, LOG_ERR);
            return null;
        }

        $decoded = json_decode((string) ($result['content'] ?? ''), true);

        if ($this->httpCode < 200 || $this->httpCode >= 300) {
            // The API answers a JSON envelope on error too, prefer its message over the raw body.
            $this->error = (string) ($decoded['error'] ?? $langs->trans('PocketApiHttpError', $this->httpCode));
            dol_syslog('PocketApi::call ' . $path . ' HTTP ' . $this->httpCode . ' ' . $this->error, LOG_ERR);
            return null;
        }

        if (!is_array($decoded)) {
            $this->error = $langs->trans('PocketApiInvalidResponse');
            return null;
        }

        return $decoded;
    }
}
