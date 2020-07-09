<?php
namespace MilanKragujevic\HuaweiApi;
/*
 * Depends on:
 * MilanKragujevic\HuaweiAPI\CustomHttpClient
 */

class Router {
    private $http = null;
    
    private $routerAddress = 'http://192.168.8.1';
    
    private $sessionInfo = '';
    private $tokenInfo = '';
    
    public function __construct() {
        $this->http = new CustomHttpClient();
    }
    
    public function setAddress($address) {
        $address = rtrim($address, '/');
        
        if(strpos($address, 'http') !== 0) {
            $address = 'http://' . $address;
        }
        
        $this->routerAddress = $address;
    }
    
    public function generalizedGet($route) {
        $this->prepare();
        
        $xml = $this->http->get($this->getUrl($route));
        $obj = new \SimpleXMLElement($xml);
        
        if(property_exists($obj, 'code')) {
            throw new \UnexpectedValueException('The API returned error code: ' . $obj->code);
        }
        
        return $obj;
    }
    
    public function getStatus() {
        return $this->generalizedGet('api/monitoring/status');
    }
    
    public function getTrafficStats() {
        return $this->generalizedGet('api/monitoring/traffic-statistics');
    }
    
    public function getMonthStats() {
        return $this->generalizedGet('api/monitoring/month_statistics');
    }
    
    public function getNetwork() {
        return $this->generalizedGet('api/net/current-plmn');
    }
    
    public function getSmsCount() {
        return $this->generalizedGet('api/sms/sms-count');
    }
    
    public function getWlanClients() {
        return $this->generalizedGet('api/wlan/host-list');
    }
    
    public function getNotifications() {
        return $this->generalizedGet('api/monitoring/check-notifications');
    }
    
    public function setMode2GOnly() {
        $this->prepare();
        
        $ledXml = '<?xml version="1.0" encoding="UTF-8"?><request><NetworkMode>02</NetworkMode><NetworkBand>3FFFFFFF</NetworkBand><LTEBand>7FFFFFFFFFFFFFFF</LTEBand></request>';
        $xml = $this->http->postXml($this->getUrl('api/net/net-mode'), $ledXml);
        $obj = new \SimpleXMLElement($xml);
        
        return ((string) $obj == 'OK');
    }
    
    public function setModeB3Only() {
        $this->prepare();
        
        $ledXml = '<?xml version: "1.0" encoding="UTF-8"?><request><NetworkMode>03</NetworkMode><NetworkBand>0</NetworkBand><LTEBand>4</LTEBand></request>';
        $xml = $this->http->postXml($this->getUrl('api/net/net-mode'), $ledXml);
        $obj = new \SimpleXMLElement($xml);
        
        return ((string) $obj == 'OK');
    }
    
    public function setModeAuto() {
        $this->prepare();
        
        $ledXml = '<?xml version: "1.0" encoding="UTF-8"?><request><NetworkMode>03</NetworkMode><NetworkBand>0</NetworkBand><LTEBand>80004</LTEBand></request>';
        $xml = $this->http->postXml($this->getUrl('api/net/net-mode'), $ledXml);
        $obj = new \SimpleXMLElement($xml);
        
        return ((string) $obj == 'OK');
    }
    
    public function toggleLTE() {
        $this->setMode2GOnly();
        sleep(1);
        $this->setModeB3Only();
        sleep(1);
        $this->setModeAuto();
    }
    
    public function isLoggedIn() {
        $obj = $this->generalizedGet('api/user/state-login');
        if(property_exists($obj, 'State')) {
            /*
             * Logged out seems to be -1
             * Logged in seems to be 0.
             */
            if($obj->State == '0') {
                return true;
            }
        }
        return false;
    }
    
    public function getInbox($page = 1, $count = 20, $unreadPreferred = false) {
        $this->prepare();
        
        $inboxXml = '<?xml version="1.0" encoding="UTF-8"?><request>
            <PageIndex>' . $page . '</PageIndex>
            <ReadCount>' . $count . '</ReadCount>
            <BoxType>1</BoxType>
            <SortType>0</SortType>
            <Ascending>0</Ascending>
            <UnreadPreferred>' . ($unreadPreferred ? '1' : '0') . '</UnreadPreferred>
            </request>
        ';
        $xml = $this->http->postXml($this->getUrl('api/sms/sms-list'), $inboxXml);
        $obj = new \SimpleXMLElement($xml);
        return $obj;
    }
    
    public function deleteSms($index) {
        $this->prepare();
        
        $deleteXml = '<?xml version="1.0" encoding="UTF-8"?><request>
            <Index>' . $index . '</Index>
            </request>
        ';
        $xml = $this->http->postXml($this->getUrl('api/sms/delete-sms'), $deleteXml);
        $obj = new \SimpleXMLElement($xml);
        return ((string) $obj == 'OK');
    }
    
    public function sendSms($receiver, $message) {
        $this->prepare();
        
        $sendSmsXml = '<?xml version="1.0" encoding="UTF-8"?><request>
            <Index>-1</Index>
            <Phones>
                <Phone>' . $receiver . '</Phone>
            </Phones>
            <Sca/>
            <Content>' . $message . '</Content>
            <Length>' . strlen($message) . '</Length>
            <Reserved>1</Reserved>
            <Date>' . date('Y-m-d H:i:s') . '</Date>
            <SendType>0</SendType>
            </request>
        ';
        $xml = $this->http->postXml($this->getUrl('api/sms/send-sms'), $sendSmsXml);
        $obj = new \SimpleXMLElement($xml);
        return ((string) $obj == 'OK');
    }
    
    public function login($username, $password) {
        $this->prepare();
        
        $loginXml = '<?xml version="1.0" encoding="UTF-8"?><request>
        <Username>' . $username . '</Username>
        <password_type>4</password_type>
        <Password>' . base64_encode(hash('sha256', $username . base64_encode(hash('sha256', $password, false)) . $this->http->getToken(), false)) . '</Password>
        </request>
        ';
        $xml = $this->http->postXml($this->getUrl('api/user/login'), $loginXml);
        $obj = new \SimpleXMLElement($xml);
        return ((string) $obj == 'OK');
    }
    
    private function getUrl($route) {
        return $this->routerAddress . '/' . $route;
    }
    
    private function prepare() {
        if(strlen($this->sessionInfo) == 0 || strlen($this->tokenInfo) == 0) {
            $xml = $this->http->get($this->getUrl('api/webserver/SesTokInfo'));
            $obj = new \SimpleXMLElement($xml);
            if(!property_exists($obj, 'SesInfo') || !property_exists($obj, 'TokInfo')) {
                throw new \RuntimeException('Malformed XML returned. Missing SesInfo or TokInfo nodes.');
            }
            $this->http->setSecurity($obj->SesInfo, $obj->TokInfo);
        }
    }
}