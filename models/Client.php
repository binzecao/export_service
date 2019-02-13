<?php

namespace models;

class Client extends Base
{
    public static $pkColumn = 'client_id';

    public static function tableName()
    {
        return '{{client_list}}';
    }

}