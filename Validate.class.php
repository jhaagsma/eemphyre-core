<?php

namespace EmPHyre;

class Validate
{

    public static function email($email)
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL); //returns the email or false
        if ($email) {
            return false;
        }

        return new Result('EMAIL_NOT_VALID');

        //OLD, ALTERNATE CODE
        /*$isValid = true;
        $atIndex = strrpos($email, "@");
        if(is_bool($atIndex) && !$atIndex){
            $isValid = false;
        }else{
            $domain = substr($email, $atIndex+1);
            $local = substr($email, 0, $atIndex);
            $localLen = strlen($local);
            $domainLen = strlen($domain);
            if ($localLen < 1 || $localLen > 64){
                // local part length exceeded
                $isValid = false;
            }elseif ($domainLen < 1 || $domainLen > 255){
                // domain part length exceeded
                $isValid = false;
            }elseif ($local[0] == '.' || $local[$localLen-1] == '.'){
                // local part starts or ends with '.'
                $isValid = false;
            }elseif (preg_match('/\\.\\./', $local)){
                // local part has two consecutive dots
                $isValid = false;
            }elseif (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)){
                // character not valid in domain part
                $isValid = false;
            }elseif (preg_match('/\\.\\./', $domain)){
                // domain part has two consecutive dots
                $isValid = false;
            }elseif(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))){
                // character not valid in local part unless 
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/',str_replace("\\\\","",$local))){
                    $isValid = false;
                }
            }
        
        
            if($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))){
                // domain not found in DNS
                $isValid = false;
            }

        }
        
        return $isValid;*/
    }

    public static function password($pwd, $pwd2, $length = 8, $letters = true, $numbers = true, $specials = true)
    {
        if ($pwd != $pwd2) {
            return new Result('PASSWORD_NOMATCH');
        } elseif (strlen($pwd) < $length) {
            return new Result('PASSWORD_SHORT', $length);
        } elseif ($letters && !preg_match("/[A-z]/", $pwd)) {
            return new Result('PASSWORD_NO_LETTER');
        } elseif ($numbers && !preg_match("/[0-9]/", $pwd)) { // && preg_match("/[^A-Za-z0-9]/",$pwd)
            return new Result('PASSWORD_NO_NUMBER');
        } elseif ($specials && !preg_match("/[^A-Za-z0-9]/", $pwd)) {
            return new Result('PASSWORD_NO_SPECIAL');
        }

        return false;
    }

    public static function sanitizeName($name)
    {
        return preg_replace("/[^A-Za-z0-9\s]/", null, $name);
    }
}
