<?php

namespace Alexusmai\YandexMetrika;

use Carbon\Carbon;

trait DataPreparation
{
    /**
     * Данные для графика Highcharts › Basic line
     */
    protected function adaptVisitsViewsUsers()
    {

        //Формируем массив данных для графика
        $itemArray = [];

        foreach ($this->data['data'] as $item) {
            $itemArray['date'][] = Carbon::createFromFormat('Y-m-d',
                $item['dimensions'][0]['name'])->formatLocalized('%e.%m');
            $itemArray['visits'][] = $item['metrics'][0];
            $itemArray['pageviews'][] = $item['metrics'][1];
            $itemArray['users'][] = $item['metrics'][2];
        }

        $dataArray = [
            ['name' => 'Визиты', 'data' => $itemArray['visits']],
            ['name' => 'Просмотры', 'data' => $itemArray['pageviews']],
            ['name' => 'Посетители', 'data' => $itemArray['users']],
        ];

        $this->adaptData = [
            'dataArray' => json_encode($dataArray, JSON_UNESCAPED_UNICODE),
            'dateArray' => json_encode($itemArray['date'],
                JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Самые просматриваемые страницы
     */
    protected function adaptTopPageViews()
    {
        $dataArray = [];

        //Формируем массив
        foreach ($this->data['data'] as $item) {
            $dataArray[] = [
                'url'       => $item['dimensions'][0]['name'],
                'title'     => $item['dimensions'][1]['name'],
                'pageviews' => $item['metrics'][0],
            ];
        }

        $this->adaptData = $dataArray;
    }

    /**
     * Отчет "Источники - Сводка"
     */
    protected function adaptSourcesSummary()
    {
        $dataArray = [];

        //Формируем массив
        foreach ($this->data['data'] as $item) {
            $dataArray['data'][] = [
                'trafficSource'           => $item['dimensions'][0]['name'],
                'sourceEngine'            => $item['dimensions'][1]['name'],
                'visits'                  => $item['metrics'][0],
                //Визиты
                'bounceRate'              => $item['metrics'][1],
                //Отказы %
                'pageDepth'               => $item['metrics'][2],
                //Глубина просмотра
                'avgVisitDurationSeconds' => date("i:s", $item['metrics'][3])
                //Время проведенное на сайте мин:сек.
            ];
        }

        //Итого и средние значения
        $dataArray['totals'] = [
            'visits'                  => $this->data['totals'][0],
            'bounceRate'              => $this->data['totals'][1],
            'pageDepth'               => $this->data['totals'][2],
            'avgVisitDurationSeconds' => date("i:s", $this->data['totals'][3]),
        ];

        $this->adaptData = $dataArray;
    }

    /**
     * Отчет "Источники - Поисковые фразы"
     */
    protected function adaptSourcesSearchPhrases()
    {
        $dataArray = [];

        //Формируем массив
        foreach ($this->data['data'] as $item) {
            $dataArray['data'][] = [
                'searchPhrase'            => $item['dimensions'][0]['name'],
                'searchEngineRoot'        => $item['dimensions'][1]['name'],
                'visits'                  => $item['metrics'][0],
                //Визиты
                'bounceRate'              => $item['metrics'][1],
                //Отказы %
                'pageDepth'               => $item['metrics'][2],
                //Глубина просмотра
                'avgVisitDurationSeconds' => date("i:s", $item['metrics'][3])
                //Время проведенное на сайте мин:сек.
            ];
        }

        //Итого и средние значения
        $dataArray['totals'] = [
            'visits'                  => $this->data['totals'][0],
            'bounceRate'              => $this->data['totals'][1],
            'pageDepth'               => $this->data['totals'][2],
            'avgVisitDurationSeconds' => date("i:s", $this->data['totals'][3]),
        ];

        $this->adaptData = $dataArray;
    }

    /**
     * Отчет "Технологии - Браузеры"
     */
    protected function adaptTechPlatforms()
    {
        $dataArray = [];

        //Формируем массив
        foreach ($this->data['data'] as $item) {
            $dataArray['data'][] = [
                'browser'                 => $item['dimensions'][0]['name'],
                'visits'                  => $item['metrics'][0],
                //Визиты
                'bounceRate'              => $item['metrics'][1],
                //Отказы %
                'pageDepth'               => $item['metrics'][2],
                //Глубина просмотра
                'avgVisitDurationSeconds' => date("i:s", $item['metrics'][3])
                //Время проведенное на сайте мин:сек.
            ];
        }

        //Итого и средние значения
        $dataArray['totals'] = [
            'visits'                  => $this->data['totals'][0],
            'bounceRate'              => $this->data['totals'][1],
            'pageDepth'               => $this->data['totals'][2],
            'avgVisitDurationSeconds' => date("i:s", $this->data['totals'][3]),
        ];

        $this->adaptData = $dataArray;
    }

    /**
     * Количество визитов и посетителей с учетом поисковых систем
     */
    protected function adaptVisitsUsersSearchEngine()
    {
        $dataArray = [];

        //Формируем массив
        foreach ($this->data['data'] as $item) {
            $dataArray['data'][] = [
                'searchEngine' => $item['dimensions'][0]['name'],
                'users'        => $item['metrics'][0]              //Юзеры
            ];
        }

        //Итого
        $dataArray['totals'] = [
            'users' => $this->data['totals'][0],
        ];

        $this->adaptData = $dataArray;
    }

    /**
     * Количество визитов с глубиной просмотра больше $pages страниц, за $days дней
     */
    protected function adaptVisitsViewsPageDepth()
    {
        $this->adaptData = $this->data['totals'][0];
    }

    /**
     * Вызов общего метода adaptGeoPie()
     */
    protected function adaptGeoArea()
    {
        $this->adaptGeoPie();
    }

    /**
     * Вызов общего метода adaptGeoPie()
     */
    protected function adaptGeoCountry()
    {
        $this->adaptGeoPie();
    }

    /**
     * География посещений Страны/Области
     * Подготовка данных для построения графика Highcharts > Pie with drilldown
     */
    protected function adaptGeoPie()
    {
        //Выбираем уникальные id стран/областей
        $key_array = [];

        //Результирующий массив с id и названием страны/области
        $idArray = [];

        foreach ($this->data['data'] as $value) {
            //Проверяем есть ли такое значение в массиве
            if (!in_array($value['dimensions'][0]['id'], $key_array)) {
                //Если нет то заносим в массив для поиска и в результирующий массив
                $key_array[] = $value['dimensions'][0]['id'];
                $idArray[] = $value['dimensions'][0];
            }
        }

        //Колличество уникальных стран/областей
        $cnt = count($idArray);

        //Массивы для построения графика
        $dataArray = [];            // страны/области
        $drilldownArray = [];      // области/города

        for ($i = 0; $i < $cnt; $i++) {
            $dataArray[$i] = [
                'name'      => $idArray[$i]['name'],
                'y'         => 0,
                'drilldown' => $idArray[$i]['name'],
            ];

            $drilldownArray[$i] = [
                'name' => $idArray[$i]['name'],
                'id'   => $idArray[$i]['name'],
                'data' => [],
            ];

            //Перебираем исходный массив и выбираем нужные данные
            foreach ($this->data['data'] as $item) {

                //Если id страны/области совпадает
                if ($item['dimensions'][0]['id'] == $idArray[$i]['id']) {
                    //Добавляем кол-во визитов в общий список страны/области
                    $dataArray[$i]['y'] += $item['metrics'][0];

                    //Если нет названия у области/города
                    if ($item['dimensions'][1]['name']) {
                        $region = $item['dimensions'][1]['name'];
                    } else {
                        $region = 'Не определено';
                    }

                    //Добавляем данные по области/городу
                    $drilldownArray[$i]['data'][] = [
                        $region,
                        $item['metrics'][0],
                    ];
                }
            }
        }

        $this->adaptData = [
            'dataArray'      => json_encode($dataArray, JSON_UNESCAPED_UNICODE),
            'drilldownArray' => json_encode($drilldownArray,
                JSON_UNESCAPED_UNICODE),
        ];
    }
}
