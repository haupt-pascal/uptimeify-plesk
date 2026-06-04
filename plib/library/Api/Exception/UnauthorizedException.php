<?php

/**
 * Thrown on HTTP 401 — the API token is missing, malformed or revoked.
 */

declare(strict_types=1);

class Modules_Uptimeify_Api_Exception_UnauthorizedException extends Modules_Uptimeify_Api_Exception_ApiException
{
}
