<?php

namespace App\Services;

class Helper
{

    public static function changeDateFormat($reqDate, $backend = 1)
    {
        if (!isset($reqDate)) {
            return null;
        }
        if ($backend == 1) {
            if (strlen($reqDate) > 8) {
                return $reqDate;
            }
            $date = str_split(str_replace('/', '-', $reqDate), 3);
            return $date = '20' . $date[2] . '-' . $date[1] . str_replace('-', '', $date[0]);
        } else {
            $getDate = explode(" ", $reqDate);
            $getDate = explode("-", $getDate[0]);
            return $date = $getDate[2] . "/" . $getDate[1] . "/" . substr($getDate[0], 2);
        }
    }
}
