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
    
    public function compile_update($zipFileName) {
        $zipFileName = $zipFileName . '.zip';

        try { 
            $zip = new ZipFile(); 
            $ignorePatterns = file(getcwd() . '/.updateignore', FILE_IGNORE_NEW_LINES);
            $files = $this->listFiles('.', $ignorePatterns);
            
            foreach ($files as $file) { 
                $destination = 'update/' . dirname($file); 
                
                if (!file_exists($destination)) {
                    mkdir($destination, 0777, true);
                }

                copy($file, 'update/' . $file); 
            }
            
            $files = scandir(getcwd() . '/update');
            foreach ($files as $file) { 
                $zip->addFile($file);
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
    
    public function isUpdateCycleReached($referenceDate) { 
        $referenceDate = Carbon::createFromFormat('Y-m-d', $referenceDate); 
        $cycleDate = $referenceDate->copy()->addDays(14); 
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
            $ch = curl_init();
            $url = 'https://rms-v1.daphascomputerconsultants.com/update.zip';
            
            // Set cURL options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $zip_data = curl_exec($ch);
            
            if(curl_errno($ch)){ 
                $output = [
                    'status' => 'error',
                    'message' => 'Curl error: ' . curl_error($ch),
                    'update_stream' => getcwd() . '/updates'
                ];
            } else { 
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode == 200) { 
                    if (!empty($zip_data))
                    { 
                        if (file_put_contents(getcwd() . '/current_version.zip', $zip_data) !== false) {
                            $zipFile = new ZipFile();
                            $zipFile->openFile(getcwd() . '/current_version.zip');
                            if($zipFile->extractTo(getcwd())) {
                                unlink(getcwd() . '/current_version.zip');
                                $output = [
                                    'status' => 'success',
                                    'message' => 'Updates installed successfully!'
                                    
                                ];
                            }
                            else {
                                $output = [
                                    'status' => 'success',
                                    'message' => 'Updates instatllation error!'
                                ];
                            }
                        } else {
                            $output = [
                                'status' => 'success',
                                'message' => 'Updates instatllation error!'
                            ];
                        } 
                    }
                    else {
                        $output = [
                            'status' => 'success',
                            'message' => 'Updates instatllation error!'
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

    public function download_updates() {
        if (!empty($this->api_key) && !empty($this->app_id)) { 
            $ch = curl_init();
            $url = 'https://rms-v1.daphascomputerconsultants.com/api/rms/download-updates';
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
                    if ($response == true)
                    {
                        $output = [
                            'status' => 'success',
                            'message' => 'Downloaded updates successfully!', 
                            
                        ];
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

