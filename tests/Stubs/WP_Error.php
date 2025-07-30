<?php

/**
 * Mock WP_Error class for testing purposes
 */
class WP_Error
{
    protected $errors = [];
    protected $error_data = [];

    public function __construct($code = '', $message = '', $data = '')
    {
        if (!empty($code)) {
            $this->errors[$code][] = $message;
        }
        
        if (!empty($data)) {
            $this->error_data[$code] = $data;
        }
    }

    public function get_error_code()
    {
        $codes = array_keys($this->errors);
        return empty($codes) ? '' : $codes[0];
    }

    public function get_error_message($code = '')
    {
        if (empty($code)) {
            $code = $this->get_error_code();
        }
        
        if (isset($this->errors[$code])) {
            return $this->errors[$code][0];
        }
        
        return '';
    }

    public function get_error_messages($code = '')
    {
        if (empty($code)) {
            $all_messages = [];
            foreach ($this->errors as $code => $messages) {
                $all_messages = array_merge($all_messages, $messages);
            }
            return $all_messages;
        }

        if (isset($this->errors[$code])) {
            return $this->errors[$code];
        }

        return [];
    }

    public function add($code, $message, $data = '')
    {
        $this->errors[$code][] = $message;
        
        if (!empty($data)) {
            $this->error_data[$code] = $data;
        }
    }
}