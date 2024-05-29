## RMSAPI PHP Client

## Introduction
RMSAPI PHP Client is a library that facilitates interaction with the RMS Super Controller module for managing various tasks such as server operations, updates handling, and more.

## Requirements
- PHP >= 7.0
- PhpZip library
- Carbon library

## Installation
You can install the RMSAPI PHP Client library via Composer. Run the following command in your project directory:

```bash
composer require rmsapi/rms
```

## Usage
```php
use RMSAPI\RMS\RMSAPI;

// Initialize RMSAPI instance
$rmsApi = new RMSAPI($api_key, $app_id);

// Sample usage: Check for updates availability
$updateStatus = $rmsApi->check_updates();
var_dump($updateStatus);
```

## Features

### 1. Server Operations
- **Run Server:** Start a PHP server locally.

### 2. Updates Management
- **Compile Updates:** Assemble updates into a zip file while excluding files specified in the ignore list.
- **Generate Controllers:** Generate controller files if they don't exist already.
- **Download Updates:** Download updates if available.
- **Install Updates:** Install updates if available.

### 3. Miscellaneous
- **Environment Variables:** Retrieve environment variables from the `.env` file.
- **Invoice Generation:** Initiate the process of generating invoices via API.

## Class Methods

### Constructor
- `__construct($api_key, $app_id)`: Initializes API key, app ID, and API endpoint.

### Server Operations
- `SPCMD($argv, $argc)`: Parses command-line arguments and executes corresponding actions.

### Updates Management
- `updatesAssembler($zipFileName)`: Assembles updates into a zip file.
- `generate_controllers($controllers)`: Generates controller files.
- `download_updates()`: Downloads updates if available.
- `install_updates()`: Installs updates if available.

### Miscellaneous
- `env($var_name)`: Retrieves environment variables.
- `invoice()`: Initiates invoice generation via API.

## License
This library is licensed under the MIT License. See the [LICENSE](https://github.com/Gicehajunior/rms-api/blob/main/LICENSE) file for details. 