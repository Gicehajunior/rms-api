# RMSAPI

RMSAPI is a PHP class designed to handle various tasks related to managing updates and interacting with an invoicing endpoint in RMS Web application.

## Features

- **Update Management**: Easily compile, check, download, and install updates for your application.
- **Invoicing Integration**: Seamlessly interact with an invoicing endpoint for billing purposes.
- **Flexible Configuration**: Use environment variables for configuration, allowing easy customization.

## Installation

1. Clone the repository or download the `RMSAPI.php` file.
2. Include the `RMSAPI.php` file in your PHP project.
3. Make sure to have the required dependencies installed (`PhpZip`, `Carbon`, `Dotenv`).

## Usage

```php
// Include the RMSAPI class
require_once('RMSAPI.php');

// Create an instance of RMSAPI
$rms = new RMSAPI\RMSAPI($api_key, $app_id);

// Example usage: Check for updates
$update_status = $rms->check_updates();
print_r($update_status);
```

## Documentation

### Constructor

```php
public function __construct($api_key = null, $app_id = null)
```

Initializes the RMSAPI object with the provided API key and app ID.

### Methods

- `isIgnored($file, $patterns)`: Checks if a file matches any of the ignore patterns.
- `listFiles($dir, $ignore = [])`: Lists all files in a directory, excluding those specified in the ignore patterns.
- `compile_update($zipFileName)`: Compiles updates into a zip file.
- `isUpdateCycleReached($referenceDate)`: Checks if the update cycle has been reached.
- `check_updates()`: Checks for available updates.
- `install_updates()`: Installs available updates.
- `download_updates()`: Downloads available updates.
- `invoice($invoicing_endpoint = null)`: Sends a request to the invoicing endpoint.
- `env($var_name)`: Retrieves environment variables.

For detailed usage instructions, refer to the source code or inline comments.

## Contributing

Contributions are welcome! Feel free to open an issue or submit a pull request.

## License

This project is licensed under the MIT License - see the [LICENSE](https://github.com/Gicehajunior/rms-api/blob/main/LICENSE) file for details.