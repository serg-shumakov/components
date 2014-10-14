<?php
/**
 * Веб-парсер страниц
 * Использует CURL
 * Умеет подставлять Referrer, UserAgent
 * Version 0.1
 * Date-Create: 26/09/2014
 * Date-Update: 26/09/2014
 * Author: Sergey Shumakov (shumakov.s.a@yandex.ru)
 */

class Parser
{
    private $_uaArr = array(    // массив юзерагентов
        'web' => array(
            'ie' => 'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
            'ff' => 'Mozilla/5.0 (Windows; I; Windows NT 5.1; ru; rv:1.9.2.13) Gecko/20100101 Firefox/4.0',
            'op' => 'Opera/9.80 (Windows NT 6.1; U; ru) Presto/2.8.131 Version/11.10',
            'sa' => 'Mozilla/5.0 (Macintosh; I; Intel Mac OS X 10_6_7; ru-ru) AppleWebKit/534.31+ (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1',
            'ch' => 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-US) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/10.0.648.205 Safari/534.16'
        ),
        'mob' => array(
            'an' => 'Mozilla/5.0 (Linux; U; Android 3.1; en-us; GT-P7510 Build/HMJ37) AppleWebKit/534.13 (KHTML, like Gecko) Version/4.0 Safari/534.13',
            'bb' => 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9900; en-US) AppleWebKit/534.11+ (KHTML, like Gecko) Version/7.0.0.261 Mobile Safari/534.11+',
            'sy' => 'Mozilla/5.0 (SymbianOS/9.2; U; Series60/3.1 NokiaN95_8GB/31.0.015; Profile/MIDP-2.0 Configuration/CLDC-1.1 ) AppleWebKit/413 (KHTML, like Gecko) Safari/413'
        ),
        'bot' => array(
            'go' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
            'ya' => 'Mozilla/5.0 (compatible; YandexBot/3.0)',
            'yh' => 'Mozilla/5.0 (compatible; Yahoo! Slurp;http://help.yahoo.com/help/us/ysearch/slurp)',
            'ba' => 'Baiduspider (+http://www.baidu.com/search/spider.htm)'
        )
    );
    private $_defaultUA = 'web/ie';

    public $ua; // юзерагент текущий

    /**
     * Конструктор парсера
     * @param string $ua - юзерагент в формате web/ie
     */
    public function Parser($ua='')
    {
        // установка UserAgent
        if($ua=='') $this->setUA($this->_defaultUA);
        elseif(!$this->setUA($ua)) $this->setUA($this->_defaultUA);
    }

    /**
     * Загрузка страницы
     * @param $url - адрес урл (http://site.ru/index.php)
     * @param array $get - массив $_GET
     * @param array $post - массив $_POST
     * @param string $referrer - реферер
     * @param bool $follow - следование по редиректам
     * @param bool $nobody - вернуть только заголовки
     * @param int $timeout - таймаут выполнения запроса
     * @param bool $debug - включить отладку запросов
     * @return array|int
     * TODO: Cookie, Logs, AutoReferrer
     */
    public function browser($url, $get=array(), $post=array(), $referrer='', $follow=false, $nobody=false, $timeout=15, $debug=false)
    {
        // формируем URL
        if (count($get)) {
            $url = $url."?";
            foreach($get as $key => $value) {
                $url .= $key.'='.$value.'&';
            }
            $url = substr($url,0,-1);
        }
        $c = curl_init($url);
        // формируем параметры CURL
        $params = Array(
            CURLOPT_REFERER => $referrer,
            CURLOPT_USERAGENT => $this->ua,
            CURLOPT_HEADER => 1,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => 1
        );
        // если урл https
        if (strpos($url, "https") !== false) {
            $params[CURLOPT_SSL_VERIFYPEER] = 0;
            $params[CURLOPT_SSL_VERIFYHOST] = 0;
            $params[CURLOPT_SSLVERSION] = 3;
        }
        // если есть POST
        if (count($post)) {
            $postFields = '';
            foreach ($post as $keyPost => $valPost) {
                $postFields .= '&' . $keyPost . '=' . urlencode($valPost);
            }
            $postFields = substr($postFields, 1);
            $params[CURLOPT_POST] = true;
            $params[CURLOPT_POSTFIELDS] = $postFields;
        }
        // если нужен FOLLOWLOCATION
        if ($follow) {
            $params[CURLOPT_FOLLOWLOCATION] = true;
        }
        // если нужны только заголовки
        if ($nobody) {
            $params[CURLOPT_NOBODY] = true;
        }
        curl_setopt_array($c, $params);
        // выполняем запрос
        $tryCount = 3;
        $result = '';
        while ($tryCount) {
            $result = curl_exec($c);
            // если возникли ошибки
            if (curl_errno($c)) {
                $tryCount--;
                echo "CURL ERROR: $url ".curl_errno($c)." (".curl_error($c).") TRY REPEAT #$tryCount \n";
                if (!$tryCount) return curl_errno($c);
            } else {
                $tryCount = false;
            }
        }
        // разделяем заголовки и конент
        preg_match("/^(.*?)\\r\\n\\r\\n(.*)/sm", $result, $contentArray);
        $headers = $contentArray[1];
        $content = $contentArray[2];
        // поиск 404
        if (strpos($headers, "404 Not Found") !== false) {
            echo "404 ERROR - $url\n";
            return 404;
        }
        // отладочная информация (если включен $debug)
        if ($debug) $this->debug($url, $params, $c, $headers, $content);
        // закрываем курл
        curl_close($c);
        // возвращаем полученный контент или заголовки
        if ($nobody) return $headers;
        return $content;
    }

    /**
     * Установка юзерагента
     * @param $ua - юзер агент в формате web/ie
     */
    public function setUA($ua)
    {
        if (!strlen($ua)) return false;
        $ua_mas = explode("/", $ua);
        if (count($ua_mas) < 2) return false;
        if (isset($this->_uaArr[$ua_mas[0]][$ua_mas[1]])) {
            $this->ua = $this->_uaArr[$ua_mas[0]][$ua_mas[1]];
            return true;
        } else {
            return false;
        }
    }

    /**
     * Отладка запросов
     * @param $url
     * @param $params
     * @param $c
     * @param string $headers
     * @param string $content
     */
    private function debug($url, $params, $c, $headers = '', $content = '')
    {
        $debugInfo = curl_getinfo($c);
        echo "--------------- CURL DEBUG -------------\n";
        echo "REQUEST:\n";
        echo "TARGET URL: $url\n";
        echo "HEADERS:\n";
        echo $debugInfo['request_header'];
        $debugParams = Array();
        foreach ($params as $key => $value) {
            switch ($key) {
                case CURLOPT_REFERER:
                    $debugParams['CURLOPT_REFERER'] = $value;
                    break;
                case CURLOPT_USERAGENT:
                    $debugParams['CURLOPT_USERAGENT'] = $value;
                    break;
                case CURLOPT_COOKIEFILE:
                    $debugParams['CURLOPT_COOKIEFILE'] = $value;
                    break;
                case CURLOPT_COOKIEJAR:
                    $debugParams['CURLOPT_COOKIEJAR'] = $value;
                    break;
                case CURLOPT_HEADER:
                    $debugParams['CURLOPT_HEADER'] = $value;
                    break;
                case CURLINFO_HEADER_OUT:
                    $debugParams['CURLINFO_HEADER_OUT'] = $value;
                    break;
                case CURLOPT_RETURNTRANSFER:
                    $debugParams['CURLOPT_RETURNTRANSFER'] = $value;
                    break;
                case CURLOPT_SSL_VERIFYPEER:
                    $debugParams['CURLOPT_SSL_VERIFYPEER'] = $value;
                    break;
                case CURLOPT_SSL_VERIFYHOST:
                    $debugParams['CURLOPT_SSL_VERIFYHOST'] = $value;
                    break;
                case CURLOPT_SSLVERSION:
                    $debugParams['CURLOPT_SSLVERSION'] = $value;
                    break;
                case CURLOPT_POST:
                    $debugParams['CURLOPT_POST'] = $value;
                    break;
                case CURLOPT_POSTFIELDS:
                    $debugParams['CURLOPT_POSTFIELDS'] = $value;
                    break;
                case CURLOPT_TIMEOUT:
                    $debugParams['CURLOPT_TIMEOUT'] = $value;
                    break;
            }
        }
        print_r($debugParams);
        echo "RESPONSE:\n";
        echo "HEADERS:\n" . $headers . "\n";
        echo "CONTENT:\n" . str_replace("<", "&lt;", $content) . "\n";
        echo "----------------------------------------\n";
    }
}