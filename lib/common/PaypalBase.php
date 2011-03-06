<?php
//  PaypalBase.php
//  PaypalBase
//
// Copyright 2011 Roman Efimov <romefimov@gmail.com>
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//    http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
//

require_once "HTTPRequest.php";
require_once "PaypalGateway.php";

class PaypalBase {
    
    protected $gateway;
    protected $endpoint = '/nvp';
    
    public function __construct(PaypalGateway $gateway) {
        $this->gateway = $gateway;
    }
    
    protected function response($data){
        $request = new HTTPRequest($this->gateway->getHost(), $this->endpoint, 'POST', true);
        $result = $request->connect($data);
        if ($result < 400) return $request;
        return false;
    }
    
    protected function responseParse($resp) {
        $a = explode("&", $resp);
        $out = array();
        foreach ($a as $v) {
            $k = strpos($v, '=');
            if ($k) {
                $key = trim(substr($v, 0, $k));
                $value = trim(substr($v, $k+1));
                if (!$key) continue;
                $out[$key] = urldecode($value);
            } else {
                $out[] = $v;
            }
        }
        return $out;
    }
    
    protected function buildQuery($data = array()) {
        $data['USER'] = $this->gateway->apiUsername;
        $data['PWD'] = $this->gateway->apiPassword;
        $data['SIGNATURE'] = $this->gateway->apiSignature;
        $data['VERSION'] = '65.0';
        $data['RETURNURL'] = $this->gateway->returnUrl;
        $data['CANCELURL']  = $this->gateway->cancelUrl;
        $query = http_build_query($data);
        return $query;
    }
    
    
    protected function runQueryWithParams($data) {
        $query = $this->buildQuery($data);
        $result = $this->response($query);
        if (!$result) return false;
        
        $response = $result->getContent();
        $return = $this->responseParse($response);
        $return['ACK'] = strtoupper($return['ACK']);
        return $return;
    }
}


?>