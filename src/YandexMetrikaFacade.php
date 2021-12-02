<?php

namespace Webjohny\YandexMetrika;

use Illuminate\Support\Facades\Facade;

class YandexMetrikaFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'yandexMetrika';
    }
}
