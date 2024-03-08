<?php

namespace API\RMS;

class RMSAPI {
    
    public $api_key; 
    public $app_id; 

    public $invoicing_endpoint; 

    public function __construct($api_key=null, $app_id=null) {
        $this->api_key = $api_key; 
        $this->app_id = $app_id; 
    }
        
    function zipFiles($zipFileName, $excludedFiles = []) {
        try { 
            $zip = new ZipArchive(); 

            if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) { 
                $files = scandir('.');
                foreach ($files as $file) { 
                    if ($file != '.' && $file != '..' && !in_array($file, $excludedFiles)) {
                        $zip->addFile($file);
                    }
                }
                
                $zip->close();
                
                return true;
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function check_updates() {
        
    }

    public function update_rms() {
        if (!empty($this->api_key) && !empty($this->app_id)) {
            
        }
    }

    public function invoice ($invoicing_endpoint = null) {  
        $this->invoicing_endpoint = !empty($invoicing_endpoint) ? $invoicing_endpoint : $this->env('INVOICING_ENDPOINT');
        
        $ch = curl_init();
        $url = $this->invoicing_endpoint;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        $response = curl_exec($ch);

        if(curl_errno($ch)){
            $output = 'Curl error: ' . curl_error($ch);
        } else { 
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode == 200) { 
                $output = $response;
            } else { 
                $output =  'HTTP Error: ' . $httpCode;
            }
        }
        
        curl_close($ch);
        
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

