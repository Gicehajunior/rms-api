<?php

namespace RMSAPI\RMS;
use \PhpZip\ZipFile;
use \Carbon\Carbon; 

class RMSAPI {
    
    public $api_key; 
    public $app_id; 

    public $invoicing_endpoint; 

    public function __construct($api_key=null, $app_id=null) {
        $this->api_key = $api_key; 
        $this->app_id = $app_id; 
    }


    public static function SPCMD($argv, $argc) {  
        if (isset($argv) && $argc > 0) {
            array_shift($argv);
            RMSAPI::ArgumentsParser($argv);
        }
    }

    public static function ArgumentsParser($args) { 
        if (count($args) < 1) { 
            echo "Usage: php script arg1 arg2 ...\n";
            return;
        }  

        // Server arguments parser
        if ($args[0] === 'run') {
            $host = '127.0.0.1';
            $port = 8000; // Default port
            $overridePort = false;

            // Check if override port option is provided
            if (count($args) >= 2 && $args[1] === '-o' && isset($args[2])) {
                $port = intval($args[2]); // Override port
                $overridePort = true;
            }

            $command = "php -S $host:$port";
            echo "Starting SP server on http://$host:$port\n";

            // If port is overridden, print the overridden port
            if ($overridePort) {
                echo "(Port overridden to $port)\n";
            }
            
            // Start PHP server
            system($command);
        }
        else if ($args[0] === 'updates:compile') { 
            // Extract the zip file name from the arguments
            $zipFileName = isset($args[3]) ? $args[3] : 'Archive';

            // Instantiate the class and compile updates
            $handler = new self();
            $result = $handler->updatesAssembler($zipFileName);

            if ($result === true) {
                echo "Updates compiled successfully.\n";
            } else {
                echo "Failed to compile updates: $result\n";
            }
        }
        else if ($args[0] === 'generate:controllers') { 
            // Extract controllers from arguments
            $controllers = array_slice($args, 1); 

            // Instantiate the class and generate controllers
            $handler = new self();
            $result = $handler->generate_controllers($controllers);

            echo $result . "\n";
        }
    }

    public function isIgnored($file, $patterns) {  
        foreach ($patterns as $pattern) {
            $pattern = './' . trim($pattern);
            $file = trim($file);   
            if (fnmatch($pattern, $file) || fnmatch($pattern, './' . $file)  || fnmatch($pattern, basename($file)) || fnmatch($pattern, './' . basename($file))) {
                return true;
            }
        }
        return false;
    }

    public function listFiles($dir, $ignore = []) {
        $files = [];
        $handle = opendir($dir);

        if (!$handle) {
            return $files;
        }

        while (($file = readdir($handle)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $fullPath = $dir . '/' . $file;
                if (!$this->isIgnored($fullPath, $ignore)) { // Check against full path
                    if (!is_dir($fullPath)) {
                        $files[] = $fullPath;
                    } else {
                        $files = array_merge($files, $this->listFiles($fullPath, $ignore));
                    }
                }
            }
        }

        closedir($handle);

        return $files;
    }

    public function generate_controllers($controllers = []) { 
        foreach ($controllers as $controller) {
            $controllerFileName = ucfirst($controller) . '.php';
            $controllerFilePath = getcwd() . ("/app/http/controllers/{$controllerFileName}");

            if (!file_exists($controllerFilePath)) {
                $content = "<?php\n\n";
                $content .= "use SelfPhp\Request;\n";
                $content .= "use SelfPhp\SP;\n";
                $content .= "use SelfPhp\Auth;\n";
                $content .= "use App\models\DashboardModel;\n";
                $content .= "use App\services\MailerService;\n";
                $content .= "use App\http\middleware\AuthMiddleware;\n\n";
                $content .= "class {$controller} extends SP\n";
                $content .= "{\n";
                $content .= "    public function __construct()\n";
                $content .= "    {\n";
                $content .= "        AuthMiddleware::AuthView();\n";
                $content .= "    }\n\n";
                $content .= "    public function index()\n";
                $content .= "    {\n";
                $content .= "        // Your controller logic goes here\n";
                $content .= "    }\n";
                $content .= "}\n";
                
                file_put_contents($controllerFilePath, $content);
                
                echo "Controller '{$controller}' generated successfully.\n";
            } else {
                echo "Controller '{$controller}' already exists.\n";
            }
        } 
    }

    function removeFilesRecursively($dir) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
    
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile()) {
                unlink($fileinfo->getRealPath());
            }
        }
    }
    
    public function updatesAssembler($zipFileName) {
        $zipFileName = $zipFileName . '.zip';
        
        try { 
            $zip = new ZipFile(); 
            $ignorePatterns = file(getcwd() . '/.updateignore', FILE_IGNORE_NEW_LINES);
            $files = $this->listFiles('.', $ignorePatterns);
            $this->removeFilesRecursively(getcwd() . '/update');
            foreach ($files as $file) { 
                $destination = 'update/' . dirname($file); 
                
                if (!file_exists($destination)) {
                    mkdir($destination, 0777, true);
                } 

                copy($file, 'update/' . $file); 
            } 
            
            $directoryIterator = new \RecursiveDirectoryIterator(getcwd() . '/update');
            $zip->addFilesFromIterator($directoryIterator);
            if (file_exists(getcwd() . '/' . $zipFileName)) {
                unlink(getcwd() . '/' . $zipFileName);
            }
            $save = $zip->saveAsFile($zipFileName);
            
            if ($save) {
                $zip->close();
                return true;
            }
            else {
                $zip->close();
                return false;
            }
            
        } catch (\Throwable $th) {
            return $th->getMessage();
        } 
    }

    public function compile_update() { 
        $output = '';

        if (is_readable(getcwd() . '/public/storage/files/updates/Archive.zip')) {
            $stream = file_get_contents(getcwd() . '/public/storage/files/updates/Archive.zip');
            if (!empty($stream)) {
                $output = $stream;
            }
        }
        
        return $output;
    }
    
    public function isUpdateCycleReached($referenceDate) { 
        $referenceDate = Carbon::createFromFormat('Y-m-d', $referenceDate); 
        $cycleDate = $referenceDate->copy()->addDays(28); 
        $currentDate = Carbon::now(); 
        
        return $currentDate->gte($cycleDate);
    }

    public function check_updates() { 
        try { 
            if (!empty($this->api_key) && !empty($this->app_id)) { 
                $referenceDate = "2024-03-01";
                if (!$this->isUpdateCycleReached($referenceDate)) {
                    $output = [
                        'status' => 'success',
                        'message' => 'Updates available. To download, click download updates button.', 
                    ];
                } else {
                    $output = [
                        'status' => 'info',
                        'message' => 'System update cycle has not been reached yet.', 
                    ]; 
                } 
            }
            else {
                $output = [
                    'status' => 'error',
                    'message' => 'API key and APP ID mismatch!', 
                ];
            }
        } catch (\Throwable $th) {
            $output = [
                'status' => 'info',
                'message' => 'System update cycle has not been reached yet!', 
            ]; 
        } 

        return $output; 
    }

    public function install_updates() {
        if (!empty($this->api_key) && !empty($this->app_id)) { 
            if (!empty($this->api_key) && !empty($this->app_id)) { 
                if (is_readable(getcwd() . '/public/storage/files/updates/current_version.zip')) {
                    $zipFile = new ZipFile();
                    $zipFile->openFile(getcwd() . '/public/storage/files/updates/current_version.zip');
                    if($zipFile->extractTo(getcwd())) { 
                        $output = [
                            'status' => 'success',
                            'message' => 'Updates installed successfully!' 
                        ];
                    }
                    else {
                        unlink(getcwd() . '/public/storage/files/updates/current_version.zip'); 
                        $output = [
                            'status' => 'error',
                            'message' => 'Updates installation error!'
                        ];
                    } 
                } 
                else {
                    $filelist = scandir(getcwd() . '/public/storage/files/updates');
                    $output = [
                        'status' => 'error',
                        'message' => 'Upload again. Updates installation error!',
                        'filelist' => $filelist
                    ];
                } 
            }
            else {
                $output = [
                    'status' => 'error',
                    'message' => 'API key and APP ID mismatch!', 
                ];
            } 
        }
        else {
            $output = [
                'status' => 'error',
                'message' => 'API key and APP ID mismatch!', 
            ];
        }

        return $output;
    }

    public function download_updates() {
        if (!empty($this->api_key) && !empty($this->app_id)) { 
            $ch = curl_init();
            $url = 'https://rms-v1.daphascomputerconsultants.com/public/storage/files/updates/Archive.zip';
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $zip_data = curl_exec($ch);
            
            if(curl_errno($ch)){ 
                $output = [
                    'status' => 'error',
                    'message' => 'Curl error: ' . curl_error($ch)
                ];
            } else { 
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode == 200) {  
                    if (empty($zip_data) || $zip_data == null) {
                        $output = [
                            'status' => 'error',
                            'message' => 'No available updates for download!'
                        ];
                    }
                    else {
                        if (!is_dir(getcwd() . '/public/storage/files/updates')) {
                            mkdir(getcwd() . '/public/storage/files/updates', 0777, true);
                        }
                        if (file_put_contents(getcwd() . '/public/storage/files/updates/current_version.zip', $zip_data) !== false) {
                            $zipFile = new ZipFile();
                            $zipFile->openFile(getcwd() . '/public/storage/files/updates/current_version.zip');
                            if($zipFile->extractTo(getcwd())) { 
                                $output = [
                                    'status' => 'success',
                                    'message' => 'Downloaded updates successfully!'
                                ]; 
                            }
                            else { 
                                $output = [
                                    'status' => 'error',
                                    'message' => 'Updates download error!'
                                ];
                            }
                            $zipFile->close();
                        } else {
                            $output = [
                                'status' => 'error',
                                'message' => 'Updates download error!'
                            ];
                        }  
                    }
                    
                } else { 
                    $output = [
                        'status' => 'error',
                        'message' => 'HTTP Error: ' . $httpCode
                    ]; 
                }
            }
            
            curl_close($ch); 
        }
        else {
            $output = [
                'status' => 'error',
                'message' => 'API key and APP ID mismatch!', 
            ];
        }

        return $output;
    }

    public function invoice ($invoicing_endpoint = null) {  
        if (!empty($this->api_key) && !empty($this->app_id)) {
            $this->invoicing_endpoint = !empty($invoicing_endpoint) ? $invoicing_endpoint : $this->env('INVOICING_ENDPOINT');
            
            $ch = curl_init();
            $url = $this->invoicing_endpoint;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            $response = curl_exec($ch);

            if(curl_errno($ch)){
                $output = [
                    'status' => 'error',
                    'message' => 'Curl error: ' . curl_error($ch)
                ];
            } else { 
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode == 200) { 
                    if (isset($response['status']))
                    {
                        if ($response['status'] == 'success')
                        {
                            $output = [
                                'status' => 'success',
                                'message' => $response['message'],  
                            ];
                        }
                        else {
                            $output = [
                                'status' => 'error',
                                'message' => $response['message'],  
                            ];
                        }
                    }
                    else {
                        $output = [
                            'status' => 'success',
                            'message' => 'Updates error on downloading!', 
                        ];
                    }
                } else { 
                    $output = [
                        'status' => 'error',
                        'message' => 'HTTP Error: ' . $httpCode
                    ];
                }
            }
            
            curl_close($ch);
        }
        else {
            $output = [
                'status' => 'error',
                'message' => 'API key and APP ID mismatch!', 
            ];
        }
        return $output;
    }

    public function env($var_name) { 
        $dotenv = Dotenv\Dotenv::createImmutable(getcwd());
        $dotenv->load();  
        
        return isset($_ENV[strtoupper($var_name)]) 
            ?   $_ENV[strtoupper($var_name)] 
            :   "Environment variable " . $var_name . " is not set in the .env file.";
    }
}

