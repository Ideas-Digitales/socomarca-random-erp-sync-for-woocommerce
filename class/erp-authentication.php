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
            update_option('random_erp_token', $body['token']);
            return $body['token'];
        }
        return false;
    }

    public function getEntities() {
        $client = new Client();
        $headers = [
          //'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . get_option('random_erp_token')
        ];
        $request = new Request('GET', $this->api_url . '/web32/entidades?empresa=01&rut=134549696', $headers);
        $res = $client->sendAsync($request)->wait();
        $body = json_decode($res->getBody(), true);
        if(is_array($body)) {
            return [
              'quantity' => count($body),
              'items' => $body
            ];
        }
        return false;
    }
}