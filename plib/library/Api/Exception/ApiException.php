<?php

/**
 * Base exception for all uptimeify API errors.
 */

declare(strict_types=1);

class Modules_Uptimeify_Api_Exception_ApiException extends Exception
{
    /** Machine code from the API's error envelope (`data.code`), '' when there is none. */
    public string $apiCode = '';

    /** @var array<mixed> The envelope's `data` object (e.g. `existingPublicId` on a 409). */
    public array $apiData = [];
}
