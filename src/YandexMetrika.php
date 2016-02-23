<?php namespace Alexusmai\YandexMetrika;

use DateTime;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;

class YandexMetrika
{
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
     * YandexMetrika constructor.
     */
    public function __construct()
    {
        $this->token = config('yandex-metrika.token');
        $this->counter_id = config('yandex-metrika.counter_id');
        $this->cache = config('yandex-metrika.cache');
    }


    /**----------------------------------------------------------------------
     * Получаем кол-во: визитов, просмотров, уникальных посетителей по дням,
     * за выбранное кол-во дней
     * @param int $days
     * @return \Illuminate\Support\Collection
     */
    public function getVisitsViewsUsers($days = 30)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        return $this->getVisitsViewsUsersForPeriod($startDate, $endDate);
    }


    /**
     * Получаем кол-во: визитов, просмотров, уникальных посетителей по дням,
     * за выбранный период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return \Illuminate\Support\Collection
     */
    public function getVisitsViewsUsersForPeriod(DateTime $startDate, DateTime $endDate)
    {
        //на выход
        $data = [];

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

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Если данные получены, заполняем массив
        if( $requestData )
        {
            //Формируем массив
            foreach($requestData['data'] as $item)
            {
                $data[] = [
                    'date'      => Carbon::createFromFormat('Y-m-d', $item['dimensions'][0]['name']),
                    'visits'    => $item['metrics'][0],
                    'pageviews' => $item['metrics'][1],
                    'users'     => $item['metrics'][2]
                ];
            }
        }

        //отдаем коллекцию
        return collect($data);
    }


    /**----------------------------------------------------------------------
     * Самые просматриваемые страницы за $days, количество - $maxResult
     * @param $days
     * @param $maxResult
     * @return \Illuminate\Support\Collection
     */
    public function getTopPageViews($days = 30, $maxResults = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        return $this->getTopPageViewsForPeriod($startDate, $endDate, $maxResults);
    }


    /**
     * Самые просматриваемые страницы за выбранный период, количество - $maxResult
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResults
     * @return \Illuminate\Support\Collection
     */
    public function getTopPageViewsForPeriod(DateTime $startDate, DateTime $endDate, $maxResults = 10)
    {
        $data = [];

        $cacheName = md5(serialize('top-pages-views'.$startDate->format('Y-m-d').$endDate->format('Y-m-d')));

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

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Если данные получены, заполняем массив
        if( $requestData )
        {
            //Формируем массив
            foreach($requestData['data'] as $item)
            {
                $data[] = [
                    'url'       => $item['dimensions'][0]['name'],
                    'title'     => $item['dimensions'][1]['name'],
                    'pageviews' => $item['metrics'][0]
                ];
            }
        }

        //отдаем коллекцию
        return collect($data);

    }


    /**----------------------------------------------------------------------
     * Отчет "Источники - Сводка" за последние $days дней
     * @param int $days
     * @return \Illuminate\Support\Collection
     */
    public function getSourcesSummary($days = 30)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        return $this->getSourcesSummaryForPeriod($startDate, $endDate);
    }

    /**
     * Отчет "Источники - Сводка" за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return \Illuminate\Support\Collection
     */
    public function getSourcesSummaryForPeriod(DateTime $startDate, DateTime $endDate)
    {
        $data = [];
        $totals = [];
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

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Если данные получены, заполняем массив
        if( $requestData )
        {
            //Формируем массив
            foreach($requestData['data'] as $item)
            {
                $data[] = [
                    'trafficSource' => $item['dimensions'][0]['name'],
                    'sourceEngine'  => $item['dimensions'][1]['name'],
                    'visits'        => $item['metrics'][0],             //Визиты
                    'bounceRate'    => $item['metrics'][1],             //Отказы %
                    'pageDepth'     => $item['metrics'][2],             //Глубина просмотра
                    'avgVisitDurationSeconds'    => date("i:s", $item['metrics'][3]) //Время проведенное на сайте мин:сек.
                ];
            }

            //Итого и средние значения
            $totals = [
                'visits'        => $requestData['totals'][0],
                'bounceRate'    => $requestData['totals'][1],
                'pageDepth'     => $requestData['totals'][2],
                'avgVisitDurationSeconds'    => date("i:s", $requestData['totals'][3])
            ];
        }

        //отдаем коллекцию
        return collect(['data' => $data, 'totals' => $totals]);
    }


    /**----------------------------------------------------------------------
     * Отчет "Источники - Поисковые фразы" за $days дней, кол-во результатов - $maxResult
     * @param int $days
     * @param int $maxResult
     * @return \Illuminate\Support\Collection
     */
    public function getSourcesSearchPhrases($days = 30, $maxResult = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        return $this->getSourcesSearchPhrasesForPeriod($startDate, $endDate, $maxResult);
    }


    /**
     * Отчет "Источники - Поисковые фразы" за период, кол-во результатов - $maxResult
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param $maxResult
     * @return \Illuminate\Support\Collection
     */
    public function getSourcesSearchPhrasesForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)
    {
        $data = [];
        $totals = [];
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

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Если данные получены, заполняем массив
        if( $requestData )
        {
            //Формируем массив
            foreach($requestData['data'] as $item)
            {
                $data[] = [
                    'searchPhrase'      => $item['dimensions'][0]['name'],
                    'searchEngineRoot'  => $item['dimensions'][1]['name'],
                    'visits'            => $item['metrics'][0],             //Визиты
                    'bounceRate'        => $item['metrics'][1],             //Отказы %
                    'pageDepth'         => $item['metrics'][2],             //Глубина просмотра
                    'avgVisitDurationSeconds'    => date("i:s", $item['metrics'][3]) //Время проведенное на сайте мин:сек.
                ];
            }

            //Итого и средние значения
            $totals = [
                'visits'        => $requestData['totals'][0],
                'bounceRate'    => $requestData['totals'][1],
                'pageDepth'     => $requestData['totals'][2],
                'avgVisitDurationSeconds'    => date("i:s", $requestData['totals'][3])
            ];
        }

        //отдаем коллекцию
        return collect(['data' => $data, 'totals' => $totals]);
    }


    /**----------------------------------------------------------------------
     * Отчет "Технологии - Браузеры" за $days дней, кол-во результатов - $maxResult
     * @param int $days
     * @param int $maxResult
     * @return \Illuminate\Support\Collection
     */
    public function getTechPlatforms($days = 30, $maxResult = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        return $this->getTechPlatformsForPeriod($startDate, $endDate, $maxResult);
    }

    /**
     * Отчет "Технологии - Браузеры" за период, кол-во результатов - $maxResult
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param $maxResult
     * @return \Illuminate\Support\Collection
     */
    public function getTechPlatformsForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)
    {
        $data = [];
        $totals = [];
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

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Если данные получены, заполняем массив
        if( $requestData )
        {
            //Формируем массив
            foreach($requestData['data'] as $item)
            {
                $data[] = [
                    'browser'      => $item['dimensions'][0]['name'],
                    'visits'            => $item['metrics'][0],             //Визиты
                    'bounceRate'        => $item['metrics'][1],             //Отказы %
                    'pageDepth'         => $item['metrics'][2],             //Глубина просмотра
                    'avgVisitDurationSeconds'    => date("i:s", $item['metrics'][3]) //Время проведенное на сайте мин:сек.
                ];
            }

            //Итого и средние значения
            $totals = [
                'visits'        => $requestData['totals'][0],
                'bounceRate'    => $requestData['totals'][1],
                'pageDepth'     => $requestData['totals'][2],
                'avgVisitDurationSeconds'    => date("i:s", $requestData['totals'][3])
            ];
        }

        //отдаем коллекцию
        return collect(['data' => $data, 'totals' => $totals]);
    }


    /**----------------------------------------------------------------------
     * Количество визитов и посетителей с учетом поисковых систем за $days дней
     * @param int $days
     * @param int $maxResult
     * @return \Illuminate\Support\Collection
     */
    public function getVisitsUsersSearchEngine($days = 30, $maxResult = 10)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        return $this->getVisitsUsersSearchEngineForPeriod($startDate, $endDate, $maxResult);
    }


    /**
     * Количество визитов и посетителей с учетом поисковых систем за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $maxResult
     * @return \Illuminate\Support\Collection
     */
    public function getVisitsUsersSearchEngineForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)
    {
        $data = [];
        $totals = [];
        $cacheName = md5(serialize('visits-users-searchEngine'.$startDate->format('Y-m-d').$endDate->format('Y-m-d').$maxResult));

        //Параметры запроса
        $urlParams = [
            'ids'           => $this->counter_id,
            'oauth_token'   => $this->token,
            'date1'         => $startDate->format('Y-m-d'),
            'date2'         => $endDate->format('Y-m-d'),
            'metrics'       => 'ym:s:visits,ym:s:users',
            'dimensions'    => 'ym:s:searchEngine',
            'filters'       => "ym:s:trafficSource=='organic'",
            'limit'         => $maxResult

        ];

        //Формируем url для запроса
        $requestUrl = $this->url.'stat/v1/data?'.urldecode(http_build_query($urlParams));

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Если данные получены, заполняем массив
        if( $requestData )
        {
            //Формируем массив
            foreach($requestData['data'] as $item)
            {
                $data[] = [
                    'searchEngine' => $item['dimensions'][0]['name'],
                    'visits'       => $item['metrics'][0],              //Визиты
                    'users'        => $item['metrics'][1]               //Юзеры
                ];
            }

            //Итого
            $totals = [
                'visits'   => $requestData['totals'][0],
                'users'    => $requestData['totals'][1]
            ];
        }

        //отдаем коллекцию
        return collect(['data' => $data, 'totals' => $totals]);
    }


    /**----------------------------------------------------------------------
     * Количество визитов с глубиной просмотра больше $pages страниц, за $days дней
     * @param int $days
     * @param int $maxResult
     * @param int $pages
     * @return \Illuminate\Support\Collection
     */
    public function getVisitsViewsPageDepth($days = 30, $pages = 5)
    {
        //Вычисляем даты
        list($startDate, $endDate) = $this->calculateDays($days);

        //Получаем данные
        return $this->getVisitsViewsPageDepthForPeriod($startDate, $endDate, $pages);
    }


    /**
     * Количество визитов с глубиной просмотра больше $pages страниц, за период
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $pages
     * @return \Illuminate\Support\Collection
     */
    public function getVisitsViewsPageDepthForPeriod(DateTime $startDate, DateTime $endDate, $pages = 5)
    {
        $data = [];

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

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Если данные получены, заполняем массив
        if( $requestData )
        {
            //если есть данные
            if($requestData['data']){
                $data = $requestData['data'][0]['metrics'];
            }
        }

        //отдаем коллекцию
        return collect($data);
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
     * @return bool|mixed
     */
    public function getRequestToApi(array $urlParams, $urlApi)
    {
        $cacheName = md5(serialize($urlParams));

        //Формируем url для запроса
        $requestUrl = $this->url.$urlApi.urldecode(http_build_query($urlParams));

        //Запрос данных
        $requestData = $this->request($requestUrl, $cacheName);

        //Возвращаем коллекцию или false,если данные не получены
        return collect($requestData);
    }

    /**----------------------------------------------------------------------
     * GET запрос данных и кэширование
     * @param $url
     * @return bool|mixed
     */
    protected function request($url, $cacheName)
    {
        return \Cache::remember($cacheName, $this->cache, function() use($url){
            try
            {
                $client = new GuzzleClient();
                $response = $client->request('GET', $url);

                //Получаем массив с данными
                $result = json_decode($response->getBody(), true);

            } catch (ClientException $e)
            {
                //Логируем ошибку
                \Log::error('Yandex Metrika: '.$e->getMessage());

                //Данные не получены
                $result = false;
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