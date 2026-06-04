<?php

/**
 * Thrown on HTTP 403 "Active website limit reached" — the customer's package
 * quota is exhausted. The UI should surface an upgrade CTA.
 */

declare(strict_types=1);

class Modules_Uptimeify_Api_Exception_QuotaExceededException extends Modules_Uptimeify_Api_Exception_ApiException
{
}
