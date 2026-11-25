<?php

/**
 * Thrown on HTTP 403 with a quota `data.code` (activeMonitorLimitReached,
 * monitorQuotaReached, monitorQuotaNoHigherTier) or the DNSBL IP limit
 * (`data.code: generic`, statusMessage "Active IP limit reached…") — the
 * customer's package or the organization quota is exhausted. The UI should
 * surface an upgrade CTA.
 */

declare(strict_types=1);

class Modules_Uptimeify_Api_Exception_QuotaExceededException extends Modules_Uptimeify_Api_Exception_ApiException
{
}
