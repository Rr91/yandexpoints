<?php

return array(
    'delivery_cost_by_yandex' => array(
        'title'        => _wp('стоимость доставки для Москвы, в руб'),
        'description'  => 'данная стоимость будет передаваться в яндекс при создании точек самовывоза',
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'delivery_cost_by_yandex_piter' => array(
        'title'        => _wp('стоимость доставки для Питера, в руб'),
        'description'  => 'данная стоимость будет передаваться в яндекс при создании точек самовывоза',
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'grastin_token' => array(
        'title'        => _wp('токен для доступа к api грастин'),
        'description'  => 'токен необходим для получения информации по пунктам самовывоза',
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'campaing_id' => array(
        'title'        => _wp('ID компании на маркете'),
        'description'  => 'необходим для работы с Api Яндекса',
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'yandex_app_id' => array(
        'title'        => _wp('ID приложения для OAuth авторизации'),
        'description'  => 'необходим для работы с Api Яндекса',
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'yandex_token' => array(
        'title'        => _wp('Токен для выполнения запросов к API Яндекса'),
        'description'  => 'токен выдается на год, затем его нужно будет менять - https://yandex.ru/dev/direct/doc/start/token-docpage/#token__token_how_get',
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
);
