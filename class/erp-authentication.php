<?php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
class ErpAuthentication {

    private $api_url;
    private $api_user;
    private $api_password;

    public function __construct() {
        $this->api_url = API_URL;
        $this->api_user = API_USER;
        $this->api_password = API_PASSWORD;
    }
    
    public function authenticate() {
        $client = new Client();
        $headers = [
          'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $options = [
        'form_params' => [
          'username' => $this->api_user,
          'password' => $this->api_password,
          'ttl' => '36000000'
        ]];
        $request = new Request('POST', $this->api_url . '/login', $headers);
        $res = $client->sendAsync($request, $options)->wait();
        $body = json_decode($res->getBody(), true);
        if(isset($body['token'])) {
            return $body['token'];
        }
        return false;
    }
}