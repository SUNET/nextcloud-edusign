<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A single, transient eduSign signing request.
 *
 * The lifecycle is:
 *   1. request()  -> a row is inserted with uuid, path and redirect_uri.
 *   2. request()  -> once the sign service answers, relay_state and uid are filled in.
 *   3. response() -> the row is looked up by relay_state, used to store the signed
 *                    file, and then deleted.
 *
 * Rows that never reach step 3 (failed or abandoned flows) are removed by the
 * `occ edusign:cleanup` command / background job based on created_at.
 *
 * @method string|null getUuid()
 * @method void setUuid(?string $uuid)
 * @method string|null getRelayState()
 * @method void setRelayState(?string $relayState)
 * @method string|null getUid()
 * @method void setUid(?string $uid)
 * @method string|null getPath()
 * @method void setPath(?string $path)
 * @method string|null getRedirectUri()
 * @method void setRedirectUri(?string $redirectUri)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class SignRequest extends Entity
{
    protected ?string $uuid = null;
    protected ?string $relayState = null;
    protected ?string $uid = null;
    protected ?string $path = null;
    protected ?string $redirectUri = null;
    protected int $createdAt = 0;

    public function __construct()
    {
        $this->addType('uuid', 'string');
        $this->addType('relayState', 'string');
        $this->addType('uid', 'string');
        $this->addType('path', 'string');
        $this->addType('redirectUri', 'string');
        $this->addType('createdAt', 'integer');
    }
}
