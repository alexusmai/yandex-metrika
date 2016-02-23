# Yandex Metrika Laravel 5 Package

[![Latest Version](https://img.shields.io/github/release/alexusmai/yandex-metrika.svg?style=flat-square)](https://github.com/alexusmai/yandex-metrika/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/alexusmai/yandex-metrika.svg?style=flat-square)](https://packagist.org/packages/alexusmai/yandex-metrika)

Пакет предназначен для получения данных статистики Яндекс Метрики.

## Установка

С помощью Composer

``` bash
composer require alexusmai/yandex-metrika
```

Добавить сервис провайдер в app/config/app.php

``` php
Alexusmai\YandexMetrika\YandexMetrikaServiceProvider::class,
```

Добавить алиас

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

Результат запроса - коллекция полученных данных.
Если данные не получены, то пустая коллекция или false.
Ошибки возникающие при запросе данных пишутся в лог с названием Yandex Metrika:

Все запросы кэшируются, время жизни кэша указывается в конфигурационном файле.

### Получаем кол-во: визитов, просмотров, уникальных посетителей по дням

``` php
YandexMetrika::getVisitsViewsUsers();   //По умолчанию - за последние 30 дней
//Пример
YandexMetrika::getVisitsViewsUsers(10); //За последние 10 дней
//За период
YandexMetrika::getVisitsViewsUsersForPeriod(DateTime $startDate, DateTime $endDate) //За указанный период
```

### Самые просматриваемые страницы

``` php
YandexMetrika::getTopPageViews();       //По умолчанию за последние 30 дней, количество результатов - 10
//Пример
YandexMetrika::getTopPageViews(10, 50); //За последние 10 дней, максимум 50 результатов
//За период
YandexMetrika::getTopPageViewsForPeriod(DateTime $startDate, DateTime $endDate, $maxResults = 10)   //По умолчанию максимум 10 результатов
```

### Отчет "Источники - Сводка"

``` php
YandexMetrika::getSourceSummary();      //По умолчанию за последние 30 дней
//Пример
YandexMetrika::getSourceSummary(7);     //За последние 10 дней
//За период
YandexMetrika::getSourcesSummaryForPeriod(DateTime $startDate, DateTime $endDate)
```

### Отчет "Источники - Поисковые фразы"

``` php
YandexMetrika::getSourcesSearchPhrases();       //По умолчанию за последние 30 дней, количество результатов - 10
//Пример
YandexMetrika::getSourcesSearchPhrases(15, 20); //За последние 15 дней, максимум 20 результатов
//За период
YandexMetrika::getSourcesSearchPhrasesForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)    //По максимум - 10 результатов
```

###  Отчет "Технологии - Браузеры"

``` php
YandexMetrika::getTechPlatforms();      //По умолчанию за последние 30 дней, макс количество результатов - 10
//Пример
YandexMetrika::getTechPlatforms(12, 5); //За последние 12 дней, максимум 5 результатов
//За период
YandexMetrika::getTechPlatformsForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10)   //По умолчанию максимум 10 результатов
```

### Количество визитов и посетителей с учетом поисковых систем

``` php
YandexMetrika::getVisitsUsersSearchEngine();    //По умолчанию за последние 30 дней, макс количество результатов - 10
//Пример
YandexMetrika::getVisitsUsersSearchEngine(24, 60);  //За последние 24 дня, максимум 60 результатов
//За период
YandexMetrika::getVisitsUsersSearchEngineForPeriod(DateTime $startDate, DateTime $endDate, $maxResult = 10) //По умолчанию максимум 10 результатов
```

### Количество визитов с заданной глубиной просмотра

``` php
YandexMetrika::getVisitsViewsPageDepth();   //По умолчанию за последние 30 дней, количество просмотренных страниц - 5
//Пример
YandexMetrika::getVisitsViewsPageDepth(7, 3);   //За последние 7 дней, количество просмотренных страниц - 3
//За период
YandexMetrika::getVisitsViewsPageDepthForPeriod(DateTime $startDate, DateTime $endDate, $pages = 5) //По умолчанию - 5 страниц
```

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