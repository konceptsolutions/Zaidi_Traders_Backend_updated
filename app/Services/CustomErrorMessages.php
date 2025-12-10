<?php

namespace App\Services;

class CustomErrorMessages
{
    public static function getCustomMessage($exception, $item = '')
    {
        // $code = $exception->getCode();
        // if ($code == '23000') {
        //     return 'You cannot delete this ' . $item . ' it is used somewhere else';
        // } elseif ($code == '42S22') {
        //     return 'Column not found in database';
        // } else {
        return $exception->getMessage();
        // }
    }
    public static function getCustomMessage2($exception, $item = '')
    {
        // $code = $exception->getCode();
        // if ($code == '23000') {
        //     return 'You cannot delete this ' . $item . ' it is used somewhere else';
        // } elseif ($code == '42S22') {
        //     return 'Column not found in database';
        // } else {
        //  $exception->getMessage = "Suppllier can not be deleted but it has been deactivated";
        return "Suppllier can not be deleted but it has been deactivated";
        // }
    }
}
