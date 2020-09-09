<?php

namespace Alexusmai\YandexMetrika;

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
     *
     * @var string
     */
    protected $url = 'https://api-metrika.yandex.net/stat/v1/data';

    /**
     * Id счетчика
     *
     * @var
     */
    protected $counter_id;

    /**
     * Token
     *
     * @var
     */
    protected $token;

    /**
     * Время кэширования в минутах, для laravel 5.8 и выше - в секундах
     *
     * @var
     */
    protected $cache;

    /**
     * Имя метода получения даннных
     *
     * @var
     */
    protected $getMethodName;

    /**
     * Имя метода обработки данных
     *
     * @var
     */
    protected $adaptMethodName;

    /**
     * Полученные данные с серверов Yandex Metriki
     *
     * @var
     */
    public $data;

    /**
     * Данные прошедшие обработку
     *
     * @var
     */
    public $adaptData;


    /**
     * YandexMetrika constructor.
     */
    public function __construct()
    {
        $this->counter_id = config('yandex-metrika.counter_id');
        $this->token = config('yandex-metrika.token');
        $this->cache = config('yandex-metrika.cache');
    }

    /**
     * Вызов методов получения данных
     *
     * @param $name
     * @param $arguments
     *
     * @return $this
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {

            $this->getMethodName = $name;

            $this->adaptMethodName = str_replace(['get', 'ForPeriod'],
                ['adapt', ''], $this->getMethodName);

            call_user_func_array([$this, $name], $arguments);
        }

        return $this;
    }

    /**
     * Приводим полученные данные в удобочитаемый вид
     *
     * @return $this
     */
    public function adapt()
    {
        if (method_exists($this, $this->adaptMethodName) && $this->data) {
            call_user_func([$this, $this->adaptMethodName]);
        }

        return $this;
    }

    /**
     * Установить другой счетчик
     *
     * @param      $token
     * @param      $counterId
     * @param null $cache
     *
     * @return $this
     */
    public function setCounter($token, $counterId, $cache = null)
    {
        $this->token = $token;
        $this->counter_id = $counterId;
        $this->cache = $cache ? $cache : config('yandex-metrika.cache');

        return $this;
    }

    /**
     * Получаем кол-во: визитов, просмотров, уникальных посетителей по дням,
     * за выбранное кол-во дней
     *
     * @param int $days
     */
    protected function getVisitsViewsUsers($days = 30)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getVisitsViewsUsersForPeriod($startDate, $endDate);
    }

    /**
     * Получаем кол-во: визитов, просмотров, уникальных посетителей по дням,
     * за выбранный период
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     */
    protected function getVisitsViewsUsersForPeriod(
        DateTime $startDate,
        DateTime $endDate
    ) {
        $cacheName = md5(serialize('visits-views-users'
            .$startDate->format('Y-m-d').$endDate->format('Y-m-d')));

        $urlParams = [
            'ids'        => $this->counter_id,
            'date1'      => $startDate->format('Y-m-d'),
            'date2'      => $endDate->format('Y-m-d'),
            'metrics'    => 'ym:s:visits,ym:s:pageviews,ym:s:users',
            'dimensions' => 'ym:s:date',
            'sort'       => 'ym:s:date',
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Самые просматриваемые страницы за $days, количество - $maxResult
     *
     * @param int $days
     * @param int $maxResults
     */
    protected function getTopPageViews($days = 30, $maxResults = 10)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getTopPageViewsForPeriod($startDate, $endDate, $maxResults);
    }

    /**
     * Самые просматриваемые страницы за выбранный период, количество - $maxResult
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResults
     */
    protected function getTopPageViewsForPeriod(
        DateTime $startDate,
        DateTime $endDate,
        $maxResults = 10
    ) {
        $cacheName = md5(serialize('top-pages-views'.$startDate->format('Y-m-d')
            .$endDate->format('Y-m-d').$maxResults));

        //Параметры запроса
        $urlParams = [
            'ids'        => $this->counter_id,
            'date1'      => $startDate->format('Y-m-d'),
            'date2'      => $endDate->format('Y-m-d'),
            'metrics'    => 'ym:pv:pageviews',
            'dimensions' => 'ym:pv:URLPathFull,ym:pv:title',
            'sort'       => '-ym:pv:pageviews',
            'limit'      => $maxResults,
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Отчет "Источники - Сводка" за последние $days дней
     *
     * @param int $days
     */
    protected function getSourcesSummary($days = 30)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getSourcesSummaryForPeriod($startDate, $endDate);
    }

    /**
     * Отчет "Источники - Сводка" за период
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     */
    protected function getSourcesSummaryForPeriod(
        DateTime $startDate,
        DateTime $endDate
    ) {
        $cacheName = md5(serialize('sources-summary'.$startDate->format('Y-m-d')
            .$endDate->format('Y-m-d')));

        $urlParams = [
            'ids'    => $this->counter_id,
            'date1'  => $startDate->format('Y-m-d'),
            'date2'  => $endDate->format('Y-m-d'),
            'preset' => 'sources_summary',
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Отчет "Источники - Поисковые фразы" за $days дней, кол-во результатов - $maxResult
     *
     * @param int $days
     * @param int $maxResult
     */
    protected function getSourcesSearchPhrases($days = 30, $maxResult = 10)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getSourcesSearchPhrasesForPeriod($startDate, $endDate,
            $maxResult);
    }

    /**
     * Отчет "Источники - Поисковые фразы" за период, кол-во результатов - $maxResult
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResult
     */
    protected function getSourcesSearchPhrasesForPeriod(
        DateTime $startDate,
        DateTime $endDate,
        $maxResult = 10
    ) {
        $cacheName = md5(serialize('sources-search-phrases'
            .$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult));

        $urlParams = [
            'ids'    => $this->counter_id,
            'date1'  => $startDate->format('Y-m-d'),
            'date2'  => $endDate->format('Y-m-d'),
            'preset' => 'sources_search_phrases',
            'limit'  => $maxResult,
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Отчет "Технологии - Браузеры" за $days дней, кол-во результатов - $maxResult
     *
     * @param int $days
     * @param int $maxResult
     */
    protected function getTechPlatforms($days = 30, $maxResult = 10)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getTechPlatformsForPeriod($startDate, $endDate, $maxResult);
    }

    /**
     * Отчет "Технологии - Браузеры" за период, кол-во результатов - $maxResult
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResult
     */
    protected function getTechPlatformsForPeriod(
        DateTime $startDate,
        DateTime $endDate,
        $maxResult = 10
    ) {
        $cacheName = md5(serialize('tech_platforms'.$startDate->format('Y-m-d')
            .$endDate->format('Y-m-d').$maxResult));

        $urlParams = [
            'ids'        => $this->counter_id,
            'date1'      => $startDate->format('Y-m-d'),
            'date2'      => $endDate->format('Y-m-d'),
            'preset'     => 'tech_platforms',
            'dimensions' => 'ym:s:browser',
            'limit'      => $maxResult,
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Количество визитов и посетителей с учетом поисковых систем за $days дней
     *
     * @param int $days
     * @param int $maxResult
     */
    protected function getVisitsUsersSearchEngine($days = 30, $maxResult = 10)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getVisitsUsersSearchEngineForPeriod($startDate, $endDate,
            $maxResult);
    }

    /**
     * Количество визитов и посетителей с учетом поисковых систем за период
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResult
     */
    protected function getVisitsUsersSearchEngineForPeriod(
        DateTime $startDate,
        DateTime $endDate,
        $maxResult = 10
    ) {
        $cacheName = md5(serialize('visits-users-searchEngine'
            .$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult));

        $urlParams = [
            'ids'        => $this->counter_id,
            'date1'      => $startDate->format('Y-m-d'),
            'date2'      => $endDate->format('Y-m-d'),
            'metrics'    => 'ym:s:users',
            'dimensions' => 'ym:s:searchEngine',
            'filters'    => "ym:s:trafficSource=='organic'",
            'limit'      => $maxResult,
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Количество визитов с глубиной просмотра больше $pages страниц, за $days дней
     *
     * @param int $days
     * @param int $pages
     */
    protected function getVisitsViewsPageDepth($days = 30, $pages = 5)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getVisitsViewsPageDepthForPeriod($startDate, $endDate, $pages);
    }

    /**
     * Количество визитов с глубиной просмотра больше $pages страниц, за период
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $pages
     */
    protected function getVisitsViewsPageDepthForPeriod(
        DateTime $startDate,
        DateTime $endDate,
        $pages = 5
    ) {
        $cacheName = md5(serialize('visits-views-page-depth'
            .$startDate->format('Y-m-d').$endDate->format('Y-m-d').$pages));

        //Параметры запроса
        $urlParams = [
            'ids'     => $this->counter_id,
            'date1'   => $startDate->format('Y-m-d'),
            'date2'   => $endDate->format('Y-m-d'),
            'metrics' => 'ym:s:visits',
            'filters' => 'ym:s:pageViews>'.$pages,
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Отчеты о посещаемости сайта с распределением по странам и регионам, за последние $days,
     * кол-во результатов - $maxResult
     *
     * @param int $days
     * @param int $maxResult
     */
    protected function getGeoCountry($days = 7, $maxResult = 100)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getGeoCountryForPeriod($startDate, $endDate, $maxResult);
    }

    /**
     * Отчеты о посещаемости сайта с распределением по странам и регионам, за период
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResult
     */
    protected function getGeoCountryForPeriod(
        DateTime $startDate,
        DateTime $endDate,
        $maxResult = 100
    ) {
        $cacheName = md5(serialize('geo_country'.$startDate->format('Y-m-d')
            .$endDate->format('Y-m-d').$maxResult));

        //Параметры запроса
        $urlParams = [
            'ids'        => $this->counter_id,
            'date1'      => $startDate->format('Y-m-d'),
            'date2'      => $endDate->format('Y-m-d'),
            'dimensions' => 'ym:s:regionCountry,ym:s:regionArea',
            'metrics'    => 'ym:s:visits',
            'sort'       => '-ym:s:visits',
            'limit'      => $maxResult,
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Отчеты о посещаемости сайта с распределением по областям и городам, за последние $days,
     * кол-во результатов - $maxResult, $countryId - id страны(225 - Россия, 187 - Украина... и т.п.)
     *
     * @param int $days
     * @param int $maxResult
     * @param int $countryId
     */
    protected function getGeoArea($days = 7, $maxResult = 100, $countryId = 225)
    {
        list($startDate, $endDate) = $this->calculateDays($days);

        $this->getGeoAreaForPeriod($startDate, $endDate, $maxResult,
            $countryId);
    }

    /**
     * Отчеты о посещаемости сайта с распределением по областям и городам, за период
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResult
     * @param int      $countryId
     */
    protected function getGeoAreaForPeriod(
        DateTime $startDate,
        DateTime $endDate,
        $maxResult = 100,
        $countryId = 225
    ) {
        $cacheName = md5(serialize('geo_region'.$startDate->format('Y-m-d')
            .$endDate->format('Y-m-d').$maxResult.$countryId));

        $urlParams = [
            'ids'        => $this->counter_id,
            'date1'      => $startDate->format('Y-m-d'),
            'date2'      => $endDate->format('Y-m-d'),
            'dimensions' => 'ym:s:regionArea,ym:s:regionCity',
            'metrics'    => 'ym:s:visits',
            'sort'       => '-ym:s:visits',
            'filters'    => "ym:s:regionCountry=='$countryId'",
            //225 - Россия
            'limit'      => $maxResult,
        ];

        $this->data = $this->request($urlParams, $cacheName);
    }

    /**
     * Произвольный запрос к Api Yandex Metrika
     * Пример:
     * $urlParams = [
     *      'ids'           => id счетчика,
     *      'date1'         => Дата в формате 'Y-m-d',
     *      'date2'         => Дата в формате 'Y-m-d',
     *      'filters'       => 'ym:s:pageViews>5',
     *      'metrics'       => 'ym:s:visits'
     * ]
     *
     * @param array $urlParams
     *
     * @return $this
     */
    public function getRequestToApi(array $urlParams)
    {
        $cacheName = md5(serialize($urlParams));

        $this->data = $this->request($urlParams, $cacheName);

        return $this;
    }

    /**
     * GET запрос данных и кэширование
     *
     * @param $urlParams
     * @param $name
     *
     * @return mixed|null
     */
    protected function request($urlParams, $name)
    {
        $cacheName = $this->counter_id.'_'.$name;

        if (Cache::has($cacheName)) {
            return Cache::get($cacheName);
        }

        try {
            $client = new GuzzleClient([
                'headers' => [
                    'Content-Type'  => 'application/x-yametrika+json',
                    'Authorization' => 'OAuth '.$this->token,
                ],
            ]);

            $response = $client->request('GET', $this->url,
                ['query' => $urlParams]);

            $result = json_decode($response->getBody(), true);

        } catch (ClientException $e) {
            Log::error('Yandex Metrika: '.$e->getMessage());

            $result = null;
        }

        if ($result) {
            Cache::put($cacheName, $result, $this->cache);
        }

        return $result;
    }

    /**
     * Вычисляем даты
     *
     * @param $numberOfDays
     *
     * @return array
     */
    protected function calculateDays($numberOfDays)
    {
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($numberOfDays);

        return [$startDate, $endDate];
    }
}
