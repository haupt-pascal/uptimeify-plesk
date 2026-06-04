<?php

/**
 * Thin, typed HTTP client for the uptimeify.io REST API.
 *
 * Uses Guzzle (PSR-18) with a strict 5 second timeout so the Plesk UI stays
 * responsive. All requests are authenticated with the organization-scoped
 * Bearer token (prefix "wsm_").
 *
 * @see https://uptimeify.io/docs/api
 */

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Modules_Uptimeify_Api_Client
{
    private const BASE_URL = 'https://uptimeify.io';
    private const TIMEOUT  = 5.0;

    private GuzzleClient $http;

    public function __construct(private readonly string $token)
    {
        // Guzzle is bundled via composer into the extension's vendor/ directory.
        require_once __DIR__ . '/../../../vendor/autoload.php';

        $this->http = new GuzzleClient([
            'base_uri'        => self::BASE_URL,
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => self::TIMEOUT,
            'http_errors'     => false,
            'headers'         => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
                'User-Agent'    => 'uptimeify-plesk/1.0',
            ],
        ]);
    }

    public static function fromSettings(): self
    {
        return new self(Modules_Uptimeify_Settings::getApiToken());
    }

    /**
     * Validate the token by resolving the organization it belongs to.
     *
     * @return array<string, mixed> Raw organization payload (id, name, ...).
     */
    public function getOrganization(): array
    {
        return $this->request('GET', '/api/organization');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCustomers(int $organizationId): array
    {
        $data = $this->request('GET', '/api/customers', ['query' => ['organizationId' => $organizationId]]);
        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomer(string $customerPublicId): array
    {
        return $this->request('GET', '/api/customers/' . rawurlencode($customerPublicId));
    }

    /**
     * @return array<string, mixed> The created customer record.
     */
    public function createCustomer(string $name, string $email, string $packageType): array
    {
        $data = $this->request('POST', '/api/customers', [
            'json' => [
                'name'        => $name,
                'email'       => $email,
                'packageType' => $packageType,
            ],
        ]);
        return $data['customer'] ?? $data;
    }

    /**
     * Change a customer's assigned package. Affects ALL websites of the customer.
     *
     * @return array<string, mixed>
     */
    public function changePackage(string $customerPublicId, string $packageType): array
    {
        return $this->request('PATCH', '/api/customers/' . rawurlencode($customerPublicId), [
            'json' => ['packageType' => $packageType],
        ]);
    }

    /**
     * @return list<array<string, mixed>> Package configs incl. displayName, maxUrls, ...
     */
    public function listPackageConfigs(): array
    {
        $data = $this->request('GET', '/api/package-configs');
        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @return list<array<string, mixed>> Website monitors (items array is unwrapped).
     */
    public function listWebsites(?string $customerPublicId = null): array
    {
        $query = ['perPage' => 200];
        if ($customerPublicId !== null && $customerPublicId !== '') {
            $query['customerId'] = $customerPublicId;
        }
        $data = $this->request('GET', '/api/websites', ['query' => $query]);
        $items = $data['items'] ?? $data;
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * Create a website monitor for a customer.
     *
     * @return array<string, mixed> The created website record.
     */
    public function createWebsite(
        string $customerPublicId,
        string $name,
        string $url,
        string $monitoringType = 'combined',
        int $checkInterval = 5,
    ): array {
        return $this->request('POST', '/api/websites', [
            'json' => [
                'customerId'     => $customerPublicId,
                'name'           => $name,
                'url'            => $url,
                'monitoringType' => $monitoringType,
                'checkInterval'  => $checkInterval,
            ],
        ]);
    }

    /**
     * Change monitoring status: active | inactive | maintenance.
     *
     * @return array<string, mixed>
     */
    public function changeWebsiteStatus(string $websitePublicId, string $status): array
    {
        return $this->request('PATCH', '/api/websites/' . rawurlencode($websitePublicId), [
            'json' => ['status' => $status],
        ]);
    }

    public function deleteWebsite(string $websitePublicId): bool
    {
        $data = $this->request('DELETE', '/api/websites/' . rawurlencode($websitePublicId));
        return (bool) ($data['success'] ?? true);
    }

    /**
     * Register a server IP for DNSBL / blacklist monitoring (add-on).
     *
     * @return array<string, mixed>
     */
    public function createCustomerIp(string $customerPublicId, string $ipAddress, string $label): array
    {
        return $this->request('POST', '/api/customers/' . rawurlencode($customerPublicId) . '/ips', [
            'json' => [
                'ipAddress' => $ipAddress,
                'label'     => $label,
                'status'    => 'active',
            ],
        ]);
    }

    /**
     * @return array<string, mixed> Uptime stats for a single website.
     */
    public function getUptimeStats(string $websitePublicId): array
    {
        return $this->request('GET', '/api/websites/' . rawurlencode($websitePublicId) . '/uptime-stats');
    }

    /**
     * Perform a request and decode the JSON body, mapping HTTP errors to typed exceptions.
     *
     * @param array<string, mixed> $options
     * @return array<int|string, mixed>
     *
     * @throws Modules_Uptimeify_Api_Exception_ApiException
     */
    private function request(string $method, string $path, array $options = []): array
    {
        try {
            $response = $this->http->request($method, $path, $options);
        } catch (ConnectException $e) {
            throw new Modules_Uptimeify_Api_Exception_ApiException(
                'Could not reach uptimeify.io (timeout/connection error).',
                0,
                $e,
            );
        } catch (RequestException $e) {
            throw new Modules_Uptimeify_Api_Exception_ApiException($e->getMessage(), 0, $e);
        }

        $this->assertSuccess($response);

        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new Modules_Uptimeify_Api_Exception_ApiException('Unexpected API response: ' . $body);
        }

        return $decoded;
    }

    private function assertSuccess(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        $body    = (string) $response->getBody();
        $decoded = json_decode($body, true);
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error'] ?? $body) : $body;

        if ($status === 401) {
            throw new Modules_Uptimeify_Api_Exception_UnauthorizedException(
                'Invalid or expired API token.',
            );
        }

        if ($status === 403 && stripos($message, 'limit reached') !== false) {
            throw new Modules_Uptimeify_Api_Exception_QuotaExceededException($message);
        }

        throw new Modules_Uptimeify_Api_Exception_ApiException(
            sprintf('uptimeify API error (HTTP %d): %s', $status, $message),
            $status,
        );
    }
}
