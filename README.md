# Azure Storage Laravel

A Laravel package for managing Azure Blob Storage with OAuth token authentication. Upload, list, and delete files from Azure Blob Storage with automatic token management and support for multiple connections.

## Installation

You can install the package via composer:

```bash
composer require hwkdo/azure-storage-laravel
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="azure-storage-laravel-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="azure-storage-laravel-config"
```

## Configuration

Add the following environment variables to your `.env` file:

```env
# Azure Blob Storage
AZURE_STORAGE_TENANT_ID=your-tenant-id
AZURE_STORAGE_CLIENT_ID=your-client-id
AZURE_STORAGE_CLIENT_SECRET=your-client-secret
AZURE_STORAGE_ACCOUNT_NAME=your-storage-account-name
AZURE_STORAGE_CONTAINER=your-container-name

# Azure AI Search (optional - only if using indexer features)
AZURE_SEARCH_SERVICE_NAME=your-search-service-name
AZURE_SEARCH_ADMIN_API_KEY=your-admin-api-key
AZURE_SEARCH_INDEX_NAME=your-index-name
AZURE_SEARCH_API_VERSION=2024-07-01
```

### Azure Setup

1. Create an App Registration in Azure Portal
2. Create a Client Secret for the app
3. Assign the **Storage Blob Data Contributor** role to your app on the Storage Account
4. The package will automatically manage OAuth tokens with scope `https://storage.azure.com/.default`

### Multiple Connections

You can configure multiple Azure Storage connections in `config/azure-storage-laravel.php`:

```php
'connections' => [
    'azure' => [
        'tenant_id' => env('AZURE_STORAGE_TENANT_ID'),
        'client_id' => env('AZURE_STORAGE_CLIENT_ID'),
        'client_secret' => env('AZURE_STORAGE_CLIENT_SECRET'),
        'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME'),
        'container' => env('AZURE_STORAGE_CONTAINER'),
    ],
    'backup' => [
        'tenant_id' => env('AZURE_STORAGE_BACKUP_TENANT_ID'),
        'client_id' => env('AZURE_STORAGE_BACKUP_CLIENT_ID'),
        'client_secret' => env('AZURE_STORAGE_BACKUP_CLIENT_SECRET'),
        'account_name' => env('AZURE_STORAGE_BACKUP_ACCOUNT_NAME'),
        'container' => env('AZURE_STORAGE_BACKUP_CONTAINER'),
    ],
],
```

## Usage

### Using the Facade

```php
use Hwkdo\AzureStorageLaravel\Facades\AzureStorageLaravel;

// Upload a file
$result = AzureStorageLaravel::uploadFile('document.pdf', storage_path('app/document.pdf'));
// Returns: ['success' => true, 'url' => '...', 'blob_name' => 'document.pdf', 'size' => 12345, ...]

// List all blobs
$blobs = AzureStorageLaravel::listBlobs();
// Returns: [['name' => 'file.pdf', 'url' => '...', 'size' => 12345, 'content_type' => 'application/pdf', ...], ...]

// List blobs with prefix
$blobs = AzureStorageLaravel::listBlobs('uploads/2024/');

// Delete a blob
$deleted = AzureStorageLaravel::deleteBlob('document.pdf');
// Returns: true
```

### Using Multiple Connections

```php
// Use a specific connection
$result = AzureStorageLaravel::connection('backup')->uploadFile('backup.zip', storage_path('app/backup.zip'));

// List blobs from backup connection
$blobs = AzureStorageLaravel::connection('backup')->listBlobs();
```

### Using Dependency Injection

```php
use Hwkdo\AzureStorageLaravel\AzureStorageLaravel;

class FileController extends Controller
{
    public function upload(Request $request, AzureStorageLaravel $storage)
    {
        $file = $request->file('document');
        $tmpPath = $file->store('temp');
        
        try {
            $result = $storage->uploadFile(
                $file->getClientOriginalName(),
                storage_path('app/' . $tmpPath)
            );
            
            unlink(storage_path('app/' . $tmpPath));
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

## Azure AI Search Indexer Operations

The package also supports Azure AI Search indexer management:

### Run an Indexer

```php
use Hwkdo\AzureStorageLaravel\Facades\AzureStorageLaravel;

// Trigger an indexer to run (using config default)
AzureStorageLaravel::runIndexer();
// Returns: true (202 Accepted - indexer run queued)

// Or specify a specific indexer name
AzureStorageLaravel::runIndexer('my-indexer-name');
```

### Get Indexer Status

```php
// Check the status of default indexer
$status = AzureStorageLaravel::getIndexerStatus();

// Or check a specific indexer
$status = AzureStorageLaravel::getIndexerStatus('my-indexer-name');

// Returns array with status information:
// [
//     'status' => 'running',
//     'lastResult' => [...],
//     'executionHistory' => [...],
// ]
```

### Reset an Indexer

```php
// Reset the default indexer (clears execution history)
AzureStorageLaravel::resetIndexer();
// Returns: true

// Or reset a specific indexer
AzureStorageLaravel::resetIndexer('my-indexer-name');
```

### List All Indexers

```php
// Get list of all indexers in the search service
$indexers = AzureStorageLaravel::listIndexers();

// Returns array of indexers:
// [
//     ['name' => 'indexer1', 'dataSourceName' => '...', ...],
//     ['name' => 'indexer2', 'dataSourceName' => '...', ...],
// ]
```

### Example: Run Indexer After Upload

```php
use Hwkdo\AzureStorageLaravel\Facades\AzureStorageLaravel;

// Upload a file
$result = AzureStorageLaravel::uploadFile('document.pdf', storage_path('app/document.pdf'));

// Trigger the indexer to process the new file
if ($result['success']) {
    AzureStorageLaravel::runIndexer('document-indexer');
}
```

## Features

### Azure Blob Storage
- **Automatic Token Management**: OAuth tokens are automatically fetched, cached, and refreshed
- **Multiple Connections**: Support for multiple Azure Storage accounts
- **Blob Name Sanitization**: Automatic sanitization of blob names (ASCII conversion, slugification)

### Azure AI Search
- **Indexer Management**: Run, reset, and monitor Azure AI Search indexers
- **Status Monitoring**: Check indexer execution status and history
- **List Operations**: View all configured indexers

### General
- **Laravel HTTP Client**: Uses Laravel's built-in HTTP client for all requests
- **Comprehensive Logging**: All operations are logged for debugging
- **Exception Handling**: Proper error handling with detailed error messages

## API Reference

### `listBlobs(?string $prefix = null): array`

List all blobs in the configured container.

**Parameters:**
- `$prefix` (optional): Filter blobs by prefix

**Returns:** Array of blob information including name, url, size, content_type, last_modified

### `uploadFile(string $blobName, string $pathToFile): array`

Upload a file to Azure Blob Storage.

**Parameters:**
- `$blobName`: The name for the blob (will be sanitized)
- `$pathToFile`: Local path to the file to upload

**Returns:** Array with success, url, blob_name, container, size, content_type

### `deleteBlob(string $blobName): bool`

Delete a blob from Azure Blob Storage.

**Parameters:**
- `$blobName`: Name of the blob to delete

**Returns:** `true` on success

### `connection(string $connection): self`

Switch to a different connection.

**Parameters:**
- `$connection`: Name of the connection from config

**Returns:** New instance for the specified connection

### `runIndexer(?string $indexerName = null): bool`

Run an Azure AI Search indexer.

**Parameters:**
- `$indexerName` (optional): Name of the indexer to run. Uses `ai_search.index_name` from config if not provided.

**Returns:** `true` on success (202 Accepted)

### `getIndexerStatus(?string $indexerName = null): array`

Get the status and execution history of an indexer.

**Parameters:**
- `$indexerName` (optional): Name of the indexer. Uses `ai_search.index_name` from config if not provided.

**Returns:** Array with status, lastResult, executionHistory

### `resetIndexer(?string $indexerName = null): bool`

Reset an indexer, clearing its execution history.

**Parameters:**
- `$indexerName` (optional): Name of the indexer to reset. Uses `ai_search.index_name` from config if not provided.

**Returns:** `true` on success

### `listIndexers(): array`

List all indexers in the Azure AI Search service.

**Returns:** Array of indexer configurations

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [hwkdo](https://github.com/hwkdo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
