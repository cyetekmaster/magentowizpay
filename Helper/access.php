<?php	

namespace Wizpay\Wizpay\Helper;

class WizpayUrlAccessManager{
    private  $base_url = 'https://api.wizpay.com.au/';
    private  $test_url = 'https://stagingapi.wizpay.com.au/';
    private  $version = 'v1/';
    private  $intermediate = 'api/';
    private  $apicall = '';


    public function GetApiUrl($environment){
        if($environment == 1){
            return $this->test_url . $this->version . $this->intermediate;
        }else{
            return $this->base_url . $this->version . $this->intermediate;   
        }
    } 
}