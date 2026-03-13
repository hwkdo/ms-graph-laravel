<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Carbon\Carbon;
use Exception;
use Hwkdo\MsGraphLaravel\Client;
use Illuminate\Support\Facades\Log;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAppServiceInterface;
use Microsoft\Graph\Generated\Applications\ApplicationsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Applications\ApplicationsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\GraphServiceClient;

class AppService implements MsGraphAppServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    /**
     * @return array<int, array{id: string, appId: string, displayName: string, secrets: array<int, array{keyId: string, displayName: string, startDateTime: Carbon|null, endDateTime: Carbon|null, daysUntilExpiry: int|null, status: string, credentialType: string}>}>
     */
    public function getApplicationsWithSecrets(): array
    {
        try {
            $config = new ApplicationsRequestBuilderGetRequestConfiguration;
            $config->queryParameters = new ApplicationsRequestBuilderGetQueryParameters;
            $config->queryParameters->select = ['id', 'appId', 'displayName', 'passwordCredentials', 'keyCredentials'];
            $config->queryParameters->top = 999;

            $response = self::$graph->applications()->get($config)->wait();
            $applications = $response->getValue() ?? [];
            $mapped = array_values(array_filter(
                array_map([$this, 'mapApplication'], $applications),
                fn (array $app) => count($app['secrets']) > 0,
            ));
            usort($mapped, fn (array $a, array $b) => strcasecmp($a['displayName'], $b['displayName']));

            return $mapped;
        } catch (Exception $e) {
            $context = [
                'exception' => $e::class,
                'message' => $e->getMessage() ?: null,
            ];

            if ($e instanceof ODataError) {
                $context['graph_error_code'] = $e->getError()?->getCode();
                $context['graph_error_message'] = $e->getPrimaryErrorMessage();
                $details = $e->getError()?->getDetails();
                if ($details !== null && $details !== []) {
                    $context['graph_error_details'] = array_map(
                        fn ($d) => ['code' => $d->getCode(), 'message' => $d->getMessage()],
                        $details
                    );
                }
            } else {
                $context['file'] = $e->getFile();
                $context['line'] = $e->getLine();
                if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
                    $response = $e->getResponse();
                    $context['http_status'] = $response->getStatusCode();
                    $context['response_body'] = $response->getBody()->getContents();
                }
                if ($e->getPrevious() !== null) {
                    $prev = $e->getPrevious();
                    $context['previous_exception'] = $prev::class;
                    $context['previous_message'] = $prev->getMessage();
                }
            }

            Log::warning('MsGraph AppService: getApplicationsWithSecrets failed', $context);

            return [];
        }
    }

    /**
     * @return array{id: string, appId: string, displayName: string, secrets: array<int, array{keyId: string, displayName: string, startDateTime: Carbon|null, endDateTime: Carbon|null, daysUntilExpiry: int|null, status: string, credentialType: string}>}
     */
    private function mapApplication(mixed $app): array
    {
        $secrets = $this->mapPasswordCredentials($app->getPasswordCredentials() ?? []);
        $certs = $this->mapKeyCredentials($app->getKeyCredentials() ?? []);

        return [
            'id' => $app->getId() ?? '',
            'appId' => $app->getAppId() ?? '',
            'displayName' => $app->getDisplayName() ?? '',
            'secrets' => array_merge($secrets, $certs),
        ];
    }

    /**
     * @param  array<int, mixed>  $credentials
     * @return array<int, array{keyId: string, displayName: string, startDateTime: Carbon|null, endDateTime: Carbon|null, daysUntilExpiry: int|null, status: string, credentialType: string}>
     */
    private function mapPasswordCredentials(array $credentials): array
    {
        return array_map(function (mixed $credential): array {
            return $this->credentialToRow($credential, 'secret');
        }, $credentials);
    }

    /**
     * @param  array<int, mixed>  $credentials
     * @return array<int, array{keyId: string, displayName: string, startDateTime: Carbon|null, endDateTime: Carbon|null, daysUntilExpiry: int|null, status: string, credentialType: string}>
     */
    private function mapKeyCredentials(array $credentials): array
    {
        return array_map(function (mixed $credential): array {
            return $this->credentialToRow($credential, 'certificate');
        }, $credentials);
    }

    /**
     * @return array{keyId: string, displayName: string, startDateTime: Carbon|null, endDateTime: Carbon|null, daysUntilExpiry: int|null, status: string, credentialType: string}
     */
    private function credentialToRow(mixed $credential, string $credentialType): array
    {
        $endDateTime = $credential->getEndDateTime()
            ? Carbon::instance($credential->getEndDateTime())
            : null;

        $startDateTime = $credential->getStartDateTime()
            ? Carbon::instance($credential->getStartDateTime())
            : null;

        $daysUntilExpiry = $endDateTime ? (int) now()->diffInDays($endDateTime, false) : null;

        $status = match (true) {
            $daysUntilExpiry === null => 'unknown',
            $daysUntilExpiry < 0 => 'expired',
            $daysUntilExpiry <= 30 => 'expiring_soon',
            default => 'active',
        };

        return [
            'keyId' => $credential->getKeyId() ?? '',
            'displayName' => $credential->getDisplayName() ?? '(kein Name)',
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
            'daysUntilExpiry' => $daysUntilExpiry,
            'status' => $status,
            'credentialType' => $credentialType,
        ];
    }
}
