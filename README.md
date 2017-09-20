# Yandex Metrika Laravel 5 Package

[![Latest Stable Version](https://poser.pugx.org/alexusmai/yandex-metrika/v/stable)](https://packagist.org/packages/alexusmai/yandex-metrika) 
[![Total Downloads](https://poser.pugx.org/alexusmai/yandex-metrika/downloads)](https://packagist.org/packages/alexusmai/yandex-metrika) 
[![Latest Unstable Version](https://poser.pugx.org/alexusmai/yandex-metrika/v/unstable)](https://packagist.org/packages/alexusmai/yandex-metrika) 
[![License](https://poser.pugx.org/alexusmai/yandex-metrika/license)](https://packagist.org/packages/alexusmai/yandex-metrika)

Пакет предназначен для получения данных статистики Яндекс Метрики.

## Установка

С помощью Composer

``` bash
composer require alexusmai/yandex-metrika
```

Если у вас установлен Laravel 5.4 или более ранней версии, то в app/config/app.php нужно добавить провайдера,

``` php
Alexusmai\YandexMetrika\YandexMetrikaServiceProvider::class,
```

и добавить алиас.

``` php
'YandexMetrika' => Alexusmai\YandexMetrika\YandexMetrikaFacade::class,
```


Публикуем файл настроек

``` php
php artisan vendor:publish --provider="Alexusmai\YandexMetrika\YandexMetrikaServiceProvider" --tag="yandex-metrika"
```

## Настройка

Метрика использует протокол OAuth, этот протокол позволяет работать с данными Яндекса от лица пользователя Яндекса через приложение, зарегистрированное на Яндексе.
Для начала нужно зарегистрировать новое приложение, и получить token

- Заходим на страницу 
```
https://oauth.yandex.ru/
```
- Нажимаем «Зарегистрировать новое приложение»
- Запоняем поле «Название»
- Выбираем в разделе права пункт - Яндекс.Метрика и ставим галочку напротив пункта «Получение статистики, чтение параметров своих и доверенных счетчиков»
- Выбираем «Подставить URL для разработки» под полем «Callback URL»
- Сохраняем
- Копируем ID приложения и заходим на Яндекс под той учетной записью, от имени которой будет работать приложение
- Переходим по URL: 
```
https://oauth.yandex.ru/authorize?response_type=token&client_id=подставим сюда идентификатор приложения
```
- Приложение запросит разрешение на доступ, нажимаем «Разрешить»
- Заносим полученный токен в файл конфигурации пакета.


## Использование

Большинтсво запросов взято из документации API Яндекс Метрики https://tech.yandex.ru/metrika/

Результат запроса - объект.

```
YandexMetrika {#373 ▼
  #url: "https://api-metrika.yandex.ru/"
  #token: "123456789"
  #counter_id: "123456789"
  #cache: 60
  #getMethodName: "getVisitsViewsUsersForPeriod"
  #adaptMethodName: "adaptVisitsViewsUsers"
  +data: array:11 [▼
    "query" => array:13 [▶]
    "data" => array:11 [▶]
    "total_rows" => 11
    "sampled" => false
    "sample_share" => 1.0
    "sample_size" => 122
    "sample_space" => 122
    "data_lag" => 87
    "totals" => array:3 [▶]
    "min" => array:3 [▶]
    "max" => array:3 [▶]
  ]
  +adaptData: array:2 [▶]
}
```
Если данные не получены - null.
Ошибки возникающие при запросе данных пишутся в лог с названием Yandex Metrika:

Все запросы кэшируются, время жизни кэша указывается в конфигурационном файле.
!!! Внимание - отрицаетльный результат запроса (null) будет также закэширован !!!

Для обработки полученных данных есть дополнительные методы, которые делают данные более удобными для применения.
Для их спользования используйте метод adapt()
Не у всех методов для получения данных есть метод для обработки. У getRequestToApi() - нету

### Получаем кол-во: визитов, просмотров, уникальных посетителей по дням

``` php
YandexMetrika::getVisitsViewsUsers();   //По умолчанию - за последние 30 дней
//Пример
YandexMetrika::getVisitsViewsUsers(10); //За последние 10 дней
//За период
YandexMetrika::getVisitsViewsUsersForPeriod(DateTime $startDate, DateTime $endDate) //За указанный период
//Обработка полученных данных для построения графика Highcharts › Basic line
YandexMetrika::getVisitsViewsUsers()->adapt();
```

### Самые просматриваемые страницы

``` php
YandexMetrika::getTopPageViews();       //По умолчанию за последние 30 дней, количество результатов - 10
//Пример
YandexMetrika::getTopPageViews(10, 50); //За последние 10 дней, максимум 50 результатов
//За период
YandexMetrika::getTopPageViewsForPeriod(DateTime $startDate, DateTime $endDate, $maxResults = 10)   //По умолчанию максимум 10 результатов
//Обработка полученных данных
YandexMetrika::getTopPageViews()->adapt();
```

### Отчет "Источники - Сводка"

``` php
YandexMetrika::getSourceSummary();      //По умолчанию за последние 30 дней
//Пример
YandexMetrika::getSourceSummary(7);     //За последние 10 дней
//За период
YandexMetrika::getSourcesSummaryForPeriod(DateTime $startDate, DateTime $endDate)
//Обработка полученных данных
YandexMetrika::getSourcesSummary()->adapt();
```

### Отчет "Источники - Поисковые фразы"

``` php
YandexMetrika::getSourcesSearchPhrases();       //По умолчанию за последние 30 дней, количество результатов - 10
//Пример
YandexMetrika::getSourcesSearchPhrases(15, 20); //За последние 15 дней, максимум 20 результатов
//За период
YandexMetrika::getSourcesSearchPhrasesForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)    //По максимум - 10 результатов
//Обработка полученных данных
YandexMetrika::getSourcesSearchPhrases()->adapt();
```

###  Отчет "Технологии - Браузеры"

``` php
YandexMetrika::getTechPlatforms();      //По умолчанию за последние 30 дней, макс количество результатов - 10
//Пример
YandexMetrika::getTechPlatforms(12, 5); //За последние 12 дней, максимум 5 результатов
//За период
YandexMetrika::getTechPlatformsForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)   //По умолчанию максимум 10 результатов
//Обработка полученных данных
YandexMetrika::getTechPlatforms()->adapt();
```

### Количество визитов и посетителей с учетом поисковых систем

``` php
YandexMetrika::getVisitsUsersSearchEngine();    //По умолчанию за последние 30 дней, макс количество результатов - 10
//Пример
YandexMetrika::getVisitsUsersSearchEngine(24, 60);  //За последние 24 дня, максимум 60 результатов
//За период
YandexMetrika::getVisitsUsersSearchEngineForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10) //По умолчанию максимум 10 результатов
//Обработка полученных данных
YandexMetrika::getVisitsUsersSearchEngine()->adapt();
```

### Количество визитов с заданной глубиной просмотра

``` php
YandexMetrika::getVisitsViewsPageDepth();       //По умолчанию за последние 30 дней, количество просмотренных страниц - 5
//Пример
YandexMetrika::getVisitsViewsPageDepth(14, 30);   //За последние 14 дней, макс количество результатов - 30
//За период
YandexMetrika::getVisitsViewsPageDepthForPeriod(DateTime $startDate, DateTime $endDate, $pages = 5) //По умолчанию - 5 страниц
//Обработка полученных данных
YandexMetrika::getVisitsViewsPageDepth()->adapt();
```

### Отчеты о посещаемости сайта с распределением по странам и регионам

``` php
YandexMetrika::getGeoCountry();   //По умолчанию за последние 7 дней, макс количество результатов - 100
//Пример
YandexMetrika::getGeoCountry(12, 30);   //За последние 12 дней, макс количество результатов - 30
//За период
YandexMetrika::getGeoCountryForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 100) //По умолчанию максимум 100 результатов
//Обработка полученных данных для построения графика Highcharts.js > Pie with drilldown
YandexMetrika::getGeoCountry()->adapt()();
```

### Отчеты о посещаемости сайта с распределением по областям и городам

``` php
YandexMetrika::getGeoArea();   //По умолчанию за последние 7 дней, макс количество результатов - 100, Страна - Россия (id-225)
//Пример
YandexMetrika::getGeoArea(12, 30, 187);   //За последние 12 дней, макс количество результатов - 30, страна - Украина
//За период
YandexMetrika::getGeoAreaForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 100, $countryId = 225)
//Обработка полученных данных для построения графика Highcharts.js > Pie with drilldown
YandexMetrika::getGeoArea()->adapt()();
```

Для методов getGeoCountry() и getGeoArea() - метод обработки данных общий - adaptGeoPie()

### Произвольный запрос к Api Yandex Metrika

Внимание! В данном случае id счетчика и token, не подставляются из файла конфигурации, а указываются вручную в параметрах запроса!!!

``` php
getRequestToApi(array $urlParams, $urlApi)
//Пример
//Параметры запроса
$urlParams = [
            'ids'           => '123456',    //id счетчика
            'oauth_token'   => '123456',    //oauth token
            'date1'         => Carbon::today()->subDays(10),    //Начальная дата
            'date2'         => Carbon::today(),     //Конечная дата
            'metrics'       => 'ym:s:visits',
            'filters'       => 'ym:s:pageViews>5'
        ];
//<раздел_API>/<версия>/<имя_метода>.<формат_результата>?
$url ='stat/v1/data?';
//Запрос
YandexMetrika::getRequestToApi($urlParams, $url);
```
