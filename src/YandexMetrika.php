<?php namespace Alexusmai\YandexMetrika;

use Log;
use Cache;
use DateTime;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;

class YandexMetrika
{

    use DataPreparation;

    /**
     * URL Yandex Metriki
     * @var string
     */
    protected $url = 'https://api-metrika.yandex.ru/';

    /**
     * OAuth token
     * @var
     */
    protected $token;

    /**
     * Id счетчика
     * @var
     */
    protected $counter_id;

    /**
     * Время жизни кэша в минутах
     * @var mixed
     */
    protected $cache;

    /**
     * Имя метода получения даннных
     * @var
     */
    protected $getMethodName;

    /**
     * Имя метода обработки данных
     * @var
     */
    protected $adaptMethodName;

    /**
     * Полученные данные с серверов Yandex Metriki
     * @var
     */
    public $data;

    /**
     * Данные прошедшие обработку
     * @var
     */
    public $adaptData;


    /**
     * YandexMetrika constructor.
     */
    public function __construct()
    {
        $this->token = config('yandex-metrika.token');
        $this->counter_id = config('yandex-metrika.counter_id');
        $this->cache = config('yandex-metrika.cache');
    }


    /**
     * Вызов методов получения данных
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        //Если такой метод существует, то вызываем его
        if( method_exists($this, $name) ){

            //Имя метода
            $this->getMethodName = $name;

            //Формируем имя для функции адаптации
            $this->adaptMethodName = str_replace(['get','ForPeriod'], ['adapt',''], $this->getMethodName);

            //Вызываем нужную функцию
            call_user_func_array([$this, $name], $arguments);
        }

        return $this;
    }


    /**
     * Приводим полученные данные в удобочитаемый вид
     * @return $this
     */
    public function adapt(){

        //Если такой метод есть, а также данные получены, то обрабатываем их
        if( method_exists($this, $this->adaptMethodName) && $this->data ){

            //Вызываем функцию для адаптации полученных данных
            call_user_func([$this, $this->adaptMethodName]);
        }

        return $this;
    }


    /**--------------------------------------------------------------------
     * Получаем кол-во: визитов, просмотров, уникальных посетителей по дням,
     * за выбранное кол-во дней
     * @param int $days
     */
    protected function getVisitsViewsUsers($days = 30)
    {

        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getVisitsViewsUsersForPeriod($startDate, $endDate);
    }


    /**
     * Получаем кол-во: визитов, просмотров, уникальных посетителей по дням,
     * за выбранный период
     * @param DateTime $startDate
     * @param DateTime $endDate
     */
    protected function getVisitsViewsUsersForPeriod(DateTime $startDate, DateTime $endDate)
    {
        $cacheName = md5(serialize('visits-views-users'.$startDate->format('Y-m-d').$endDate->format('Y-m-d')));

        //Параметры запроса
        $urlParams = [
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'metrics'       => 'ym:s:visits,ym:s:pageviews,ym:s:users',
            'dimensions'    => 'ym:s:date',
            'sort'          => 'ym:s:date',
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token
        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }


    /**-----------------------------------------------------------------
     * Самые просматриваемые страницы за $days, количество - $maxResult
     * @param int $days
     * @param int $maxResults
     */
    protected function getTopPageViews($days = 30, $maxResults = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getTopPageViewsForPeriod($startDate, $endDate, $maxResults);
    }


    /**
     * Самые просматриваемые страницы за выбранный период, количество - $maxResult
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResults
     */
    protected function getTopPageViewsForPeriod(DateTime $startDate, DateTime $endDate, $maxResults = 10)
    {

        $cacheName = md5(serialize('top-pages-views'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResults));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'metrics'       => 'ym:pv:pageviews',
            'dimensions'    => 'ym:pv:URLPathFull,ym:pv:title',
            'sort'          => '-ym:pv:pageviews',
            'limit'         => $maxResults

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);

    }


    /**----------------------------------------------------
     * Отчет "Источники - Сводка" за последние $days дней
     * @param int $days
     */
    protected function getSourcesSummary($days = 30)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getSourcesSummaryForPeriod($startDate, $endDate);
    }


    /**
     * Отчет "Источники - Сводка" за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     */
    protected function getSourcesSummaryForPeriod(DateTime $startDate, DateTime $endDate)
    {
        $cacheName = md5(serialize('sources-summary'.$startDate->format('Y-m-d').$endDate->format('Y-m-d')));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'preset'        => 'sources_summary'

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }


    /**-----------------------------------------------------------------------------------
     * Отчет "Источники - Поисковые фразы" за $days дней, кол-во результатов - $maxResult
     * @param int $days
     * @param int $maxResult
     */
    protected function getSourcesSearchPhrases($days = 30, $maxResult = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getSourcesSearchPhrasesForPeriod($startDate, $endDate, $maxResult);
    }


    /**
     * Отчет "Источники - Поисковые фразы" за период, кол-во результатов - $maxResult
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResult
     */
    protected function getSourcesSearchPhrasesForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)
    {
        $cacheName = md5(serialize('sources-search-phrases'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'preset'        => 'sources_search_phrases',
            'limit'         => $maxResult

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }


    /**-----------------------------------------------------------------------------
     * Отчет "Технологии - Браузеры" за $days дней, кол-во результатов - $maxResult
     * @param int $days
     * @param int $maxResult
     */
    protected function getTechPlatforms($days = 30, $maxResult = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getTechPlatformsForPeriod($startDate, $endDate, $maxResult);
    }


    /**
     * Отчет "Технологии - Браузеры" за период, кол-во результатов - $maxResult
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResult
     */
    protected function getTechPlatformsForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)
    {
        $cacheName = md5(serialize('tech_platforms'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'preset'        => 'tech_platforms',
            'dimensions'    => 'ym:s:browser',
            'limit'         => $maxResult

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }


    /**-------------------------------------------------------------------------
     * Количество визитов и посетителей с учетом поисковых систем за $days дней
     * @param int $days
     * @param int $maxResult
     */
    protected function getVisitsUsersSearchEngine($days = 30, $maxResult = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getVisitsUsersSearchEngineForPeriod($startDate, $endDate, $maxResult);
    }


    /**
     * Количество визитов и посетителей с учетом поисковых систем за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResult
     */
    protected function getVisitsUsersSearchEngineForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)
    {
        $cacheName = md5(serialize('visits-users-searchEngine'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'metrics'       => 'ym:s:users',
            'dimensions'    => 'ym:s:searchEngine',
            'filters'       => "ym:s:trafficSource=='organic'",
            'limit'         => $maxResult

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }


    /**-----------------------------------------------------------------------------
     * Количество визитов с глубиной просмотра больше $pages страниц, за $days дней
     * @param int $days
     * @param int $pages
     */
    protected function getVisitsViewsPageDepth($days = 30, $pages = 5)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getVisitsViewsPageDepthForPeriod($startDate, $endDate, $pages);
    }


    /**
     * Количество визитов с глубиной просмотра больше $pages страниц, за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $pages
     */
    protected function getVisitsViewsPageDepthForPeriod(DateTime $startDate, DateTime $endDate, $pages = 5)
    {
        $cacheName = md5(serialize('visits-views-page-depth'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$pages));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'metrics'       => 'ym:s:visits',
            'filters'       => 'ym:s:pageViews>'.$pages
        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }


    /**---------------------------------------------------------------------------------------
     * Отчеты о посещаемости сайта с распределением по странам и регионам, за последние $days,
     * кол-во результатов - $maxResult
     * @param int $days
     * @param int $maxResult
     */
    protected function getGeoCountry($days = 7, $maxResult = 100)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getGeoCountryForPeriod($startDate, $endDate, $maxResult);
    }


    /**
     * Отчеты о посещаемости сайта с распределением по странам и регионам, за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResult
     */
    protected function getGeoCountryForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 100)
    {

        $cacheName = md5(serialize('geo_country'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'dimensions'    => 'ym:s:regionCountry,ym:s:regionArea',
            'metrics'       => 'ym:s:visits',
            'sort'          => '-ym:s:visits',
            'limit'         => $maxResult

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }


    /**------------------------------------------------------------------------------------------------
     * Отчеты о посещаемости сайта с распределением по областям и городам, за последние $days,
     * кол-во результатов - $maxResult, $countryId - id страны(225 - Россия, 187 - Украина... и т.п.)
     * @param int $days
     * @param int $maxResult
     * @param int $countryId
     */
    protected function getGeoArea($days = 7, $maxResult = 100, $countryId = 225)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        $this->getGeoAreaForPeriod($startDate, $endDate, $maxResult, $countryId);
    }

    /**
     * Отчеты о посещаемости сайта с распределением по областям и городам, за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResult
     * @param int $countryId
     */
    protected function getGeoAreaForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 100, $countryId = 225)
    {

        $cacheName = md5(serialize('geo_region'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult.$countryId));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'dimensions'    => 'ym:s:regionArea,ym:s:regionCity',
            'metrics'       => 'ym:s:visits',
            'sort'          => '-ym:s:visits',
            'filters'       => "ym:s:regionCountry=='$countryId'",     //225 - Россия
            'limit'         => $maxResult

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);
    }

    /**-------------------------------------------------------------------
     * Произвольный запрос к Api Yandex Metrika
     * Пример:
     * $urlParams = [
     *      'ids'           => id счетчика,
     *      'oauth_token'   => ваш токен,
     *      'date1'         => Дата в формате 'Y-m-d',
     *      'date2'         => Дата в формате 'Y-m-d',
     *      'filters'       => 'ym:s:pageViews>5',
     *      'metrics'       => 'ym:s:visits'
     * ]
     *
     * <раздел_API>/<версия>/<имя_метода>.<формат_результата>?
     * $urlApi = 'stat/v1/data?';
     *
     * @param array $urlParams
     * @param $urlApi
     * @return $this
     */
    public function getRequestToApi(array $urlParams, $urlApi)
    {
        $cacheName = md5(serialize($urlParams));

        //Формируем url для запроса
        $requestUrl = $this->url.$urlApi.urldecode(http_build_query($urlParams));

        //Запрос данных - возвращает массив или false,если данные не получены
        $this->data = $this->request($requestUrl, $cacheName);

        return $this;
    }

    /**----------------------------------------------------------------------
     * GET запрос данных и кэширование
     * @param $url
     * @return bool|mixed
     */
    protected function request($url, $cacheName)
    {
        return Cache::remember($cacheName, $this->cache, function() use($url){
            try
            {
                $client = new GuzzleClient();
                $response = $client->request('GET', $url);

                //Получаем массив с данными
                $result = json_decode($response->getBody(), true);

            } catch (ClientException $e)
            {
                //Логируем ошибку
                Log::error('Yandex Metrika: '.$e->getMessage());

                //Данные не получены
                $result = null;
            }

            return $result;
        });
    }


    /**----------------------------------------------------------------------
     * Вычисляем даты
     * @param $numberOfDays
     * @return array
     */
    protected function calculateDays($numberOfDays)
    {
        //Сегодня
        $endDate = Carbon::today();
        //Вычисляем (Сегодня - кол-во дней)
        $startDate = Carbon::today()->subDays($numberOfDays);

        return [$startDate, $endDate];
    }
}