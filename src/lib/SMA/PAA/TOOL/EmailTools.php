<?php
namespace SMA\PAA\TOOL;

class EmailTools
{
    public function isValid($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
