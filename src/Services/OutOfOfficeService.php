<?php

namespace Hwkdo\MsGraphLaravel\Services;

use App\Models\User;
use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Models\OutOfOfficeStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\BatchRequestBuilder;
use Microsoft\Graph\Core\Requests\BatchRequestContent;
use Microsoft\Graph\Core\Requests\BatchRequestItem;
use Microsoft\Graph\Core\Requests\BatchResponseContent;
use Microsoft\Graph\Core\Requests\BatchResponseItem;
use Microsoft\Graph\Generated\Models\MailboxSettings;
use Microsoft\Graph\GraphServiceClient;

class OutOfOfficeService
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    /**
     * Synchronize out-of-office status for the next batch of active users.
     *
     * @param  int  $batchSize  Number of users to process (max 20 for MS Graph batch)
     * @return array Statistics: ['success' => int, 'failed' => int, 'total' => int]
     */
    public function syncNextBatch(int $batchSize = 20): array
    {
        if ($batchSize > 20) {
            $batchSize = 20; // MS Graph batch limit
        }

        // Get next batch of active users that need syncing
        $users = $this->getNextUsersToSync($batchSize);

        if ($users->isEmpty()) {
            Log::info('OutOfOfficeService: No users to sync');

            return ['success' => 0, 'failed' => 0, 'total' => 0];
        }

        // Create batch request content
        ['content' => $batchRequestContent, 'userMap' => $userMap] = $this->createBatchRequestContent($users);

        // Send batch request
        try {
            $batchRequestBuilder = new BatchRequestBuilder(self::$graph->getRequestAdapter());
            $batchResponse = $batchRequestBuilder->postAsync($batchRequestContent)->wait();

            // Debug: Log how many responses we actually got
            Log::debug('OutOfOfficeService: Batch response received', [
                'requests_sent' => count($userMap),
                'responses_available' => method_exists($batchResponse, 'getResponses') ? count($batchResponse->getResponses()) : 'unknown',
            ]);

            // Process responses and save to database
            return $this->processBatchResponse($userMap, $batchRequestContent, $batchResponse);
        } catch (\Exception $e) {
            Log::error('OutOfOfficeService: Batch request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'users_count' => $users->count(),
                'users' => $users->pluck('id', 'upn')->toArray(),
            ]);

            return ['success' => 0, 'failed' => $users->count(), 'total' => $users->count()];
        }
    }

    /**
     * Get next batch of users that need syncing.
     */
    protected function getNextUsersToSync(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        // Priority: Users without status entry, then users with oldest synced_at
        return User::where('active', true)
            ->where(function ($query) {
                $query->whereDoesntHave('outOfOfficeStatus')
                    ->orWhereHas('outOfOfficeStatus', function ($q) {
                        $q->whereNull('synced_at')
                            ->orWhere('synced_at', '<', now()->subMinutes(75));
                    });
            })
            ->leftJoin('ms_graph_laravel_out_of_office_stati', 'users.id', '=', 'ms_graph_laravel_out_of_office_stati.user_id')
            ->select('users.*')
            ->orderBy('ms_graph_laravel_out_of_office_stati.synced_at', 'asc')
            ->orderBy('users.id', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create batch request content for mailbox settings.
     *
     * @return array{content: BatchRequestContent, userMap: array<string, User>}
     */
    protected function createBatchRequestContent(\Illuminate\Database\Eloquent\Collection $users): array
    {
        $batchRequestItems = [];
        $tempUserMap = []; // Temporary map: index in batchRequestItems -> User

        foreach ($users as $user) {
            try {
                // Create request information for mailboxSettings GET request
                $requestInfo = self::$graph->users()
                    ->byUserId($user->upn)
                    ->mailboxSettings()
                    ->toGetRequestInformation();

                $batchRequestItem = new BatchRequestItem($requestInfo);
                $itemIndex = count($batchRequestItems); // Index before adding
                $batchRequestItems[] = $batchRequestItem;
                
                // Store user reference at the same index as the batch item
                $tempUserMap[$itemIndex] = $user;
            } catch (\Exception $e) {
                Log::warning('OutOfOfficeService: Failed to create request for user', [
                    'user_id' => $user->id,
                    'upn' => $user->upn,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $batchRequestContent = new BatchRequestContent($batchRequestItems);

        // Create user map after requests are created (so we have IDs)
        // Important: Only map users that successfully created batch items
        $userMap = [];
        $requests = $batchRequestContent->getRequests();
        
        Log::debug('OutOfOfficeService: Creating user map', [
            'total_requests' => count($requests),
            'total_users_in_temp_map' => count($tempUserMap),
            'temp_map_indices' => array_keys($tempUserMap),
        ]);
        
        foreach ($requests as $index => $request) {
            // Use the same index for tempUserMap since we only added users when items were created
            if (isset($tempUserMap[$index])) {
                $userMap[$request->getId()] = $tempUserMap[$index];
                Log::debug('OutOfOfficeService: Mapped user to request', [
                    'request_id' => $request->getId(),
                    'user_id' => $tempUserMap[$index]->id,
                    'upn' => $tempUserMap[$index]->upn,
                ]);
            } else {
                Log::warning('OutOfOfficeService: No user found for request index', [
                    'request_id' => $request->getId(),
                    'index' => $index,
                ]);
            }
        }

        Log::info('OutOfOfficeService: Batch request content created', [
            'total_users' => $users->count(),
            'successful_requests' => count($batchRequestItems),
            'mapped_users' => count($userMap),
        ]);

        return ['content' => $batchRequestContent, 'userMap' => $userMap];
    }

    /**
     * Process batch response and save statuses to database.
     *
     * @param  array<string, User>  $userMap  Maps request ID to User
     */
    protected function processBatchResponse(
        array $userMap,
        BatchRequestContent $batchRequestContent,
        BatchResponseContent $batchResponse
    ): array {
        $success = 0;
        $failed = 0;
        $batchRequests = $batchRequestContent->getRequests();

        Log::debug('OutOfOfficeService: Processing batch response', [
            'total_requests' => count($batchRequests),
            'total_users_in_map' => count($userMap),
            'request_ids' => array_map(fn($r) => $r->getId(), $batchRequests),
            'user_map_ids' => array_keys($userMap),
        ]);

        foreach ($batchRequests as $batchRequest) {
            $requestId = $batchRequest->getId();
            $user = $userMap[$requestId] ?? null;

            if (! $user) {
                Log::warning('OutOfOfficeService: No user found for request ID', [
                    'request_id' => $requestId,
                    'available_request_ids' => array_keys($userMap),
                ]);
                $failed++;
                continue;
            }

            try {
                $responseItem = null;
                try {
                    $responseItem = $batchResponse->getResponse($requestId);
                } catch (\Exception $e) {
                    Log::error('OutOfOfficeService: Exception getting response for request', [
                        'user_id' => $user->id,
                        'upn' => $user->upn,
                        'request_id' => $requestId,
                        'error' => $e->getMessage(),
                    ]);
                }

                if (! $responseItem) {
                    Log::error('OutOfOfficeService: No response item found for request', [
                        'user_id' => $user->id,
                        'upn' => $user->upn,
                        'request_id' => $requestId,
                    ]);
                    $failed++;
                    continue;
                }

                if ($responseItem->getStatusCode() === 200) {
                    // Deserialize response to MailboxSettings model
                    $mailboxSettings = $batchResponse->getResponseBody($requestId, MailboxSettings::class);

                    if ($mailboxSettings && $mailboxSettings->getAutomaticRepliesSetting()) {
                        $this->saveOutOfOfficeStatus($user, $mailboxSettings->getAutomaticRepliesSetting());
                        $success++;
                    } else {
                        // No automatic replies setting found, save as disabled
                        $this->saveOutOfOfficeStatus($user, null);
                        $success++;
                    }
                } else {
                    // Request failed - get error details
                    $errorBody = null;
                    $errorCode = null;
                    try {
                        $errorStream = $responseItem->getBody();
                        if ($errorStream) {
                            $errorBody = $errorStream->getContents();
                            // Try to decode JSON if possible
                            $decoded = json_decode($errorBody, true);
                            if ($decoded) {
                                $errorBody = $decoded;
                                $errorCode = $decoded['error']['code'] ?? null;
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore errors reading body
                    }

                    // Check if error is "user doesn't exist" or "no mailbox" - save status to avoid retrying
                    if (in_array($errorCode, ['ErrorInvalidUser', 'MailboxNotEnabledForRESTAPI', 'ErrorNonExistentMailbox'])) {
                        // Save a "disabled" status so we don't keep trying this user
                        $this->saveOutOfOfficeStatus($user, null);
                        Log::info('OutOfOfficeService: User has no valid mailbox, saved as disabled', [
                            'user_id' => $user->id,
                            'upn' => $user->upn,
                            'error_code' => $errorCode,
                        ]);
                        $success++; // Count as success since we handled it
                    } else {
                        Log::warning('OutOfOfficeService: Request failed for user', [
                            'user_id' => $user->id,
                            'upn' => $user->upn,
                            'status_code' => $responseItem->getStatusCode(),
                            'error_code' => $errorCode,
                            'error_body' => $errorBody,
                        ]);
                        $failed++;
                    }
                }
            } catch (\Exception $e) {
                Log::error('OutOfOfficeService: Error processing response for user', [
                    'user_id' => $user->id,
                    'upn' => $user->upn,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'request_id' => $requestId,
                ]);
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'total' => count($userMap),
        ];
    }

    /**
     * Save out-of-office status to database.
     */
    protected function saveOutOfOfficeStatus(User $user, $automaticRepliesSetting): void
    {
        $status = null;
        $scheduledStartAt = null;
        $scheduledEndAt = null;

        if ($automaticRepliesSetting) {
            try {
                $status = $automaticRepliesSetting->getStatus()?->value();

                if ($status === 'scheduled') {
                    $startDateTime = $automaticRepliesSetting->getScheduledStartDateTime();
                    $endDateTime = $automaticRepliesSetting->getScheduledEndDateTime();

                    if ($startDateTime) {
                        $startDt = $startDateTime->getDateTime();
                        $startTz = $startDateTime->getTimezone();
                        $scheduledStartAt = new Carbon($startDt, $startTz);
                        $scheduledStartAt->setTimezone(config('app.timezone'));
                    }

                    if ($endDateTime) {
                        $endDt = $endDateTime->getDateTime();
                        $endTz = $endDateTime->getTimezone();
                        $scheduledEndAt = new Carbon($endDt, $endTz);
                        $scheduledEndAt->setTimezone(config('app.timezone'));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('OutOfOfficeService: Error parsing automatic replies setting', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        OutOfOfficeStatus::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'scheduled_start_at' => $scheduledStartAt,
                'scheduled_end_at' => $scheduledEndAt,
                'synced_at' => now(),
            ]
        );
    }
}
