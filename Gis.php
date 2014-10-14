<?php
/**
 * API 2GIS
 * Компонент реализует прототип API-интерфейса для удобного парсинга 2gis.ru
 * Date: 26.09.14
 * Version 0.1
 * Date-Create: 26/09/2014
 * Date-Update: 26/09/2014
 * Author: Sergey Shumakov (shumakov.s.a@yandex.ru)
 */

class Gis
{
    private $_url_geo_search = 'http://catalog.api.2gis.ru/2.0/geo/search';
    private $_url_geo_get = 'http://catalog.api.2gis.ru/2.0/geo/get';
    private $_url_suggest = 'http://catalog.api.2gis.ru/2.0/suggest';
    private $_url_catalog_rubric_list = 'http://catalog.api.2gis.ru/2.0/catalog/rubric/list';
    private $_url_catalog_branch_list = 'http://catalog.api.2gis.ru/2.0/catalog/branch/list';
    private $_url_catalog_branch_get = 'http://catalog.api.2gis.ru/2.0/catalog/branch/get';

    private $_key;
    private $_format;
    private $_region;

    private $_parser;

    public function Gis($key, $format, $region)
    {
        $this->_key = $key;
        $this->_format = $format;
        $this->_region = $region;
        $this->_parser = new Parser('web/ie');
    }

    public function catalogBranchGet($id, $fields=array(
            'items.adm_div', 'items.region_id', 'items.reviews', 'items.photos', 'items.point', 'items.links',
            'items.name_ex', 'items.org', 'items.see_also', 'items.dates', 'items.external_content'
        ))
    {
        $get = array(
            'id'=>$id,
            'region_id'=>$this->_region,
            'fields'=>implode("%2C",$fields),
            'key'=>$this->_key,
            'format'=>$this->_format
        );
        $url = $this->_url_catalog_branch_get;
        return $this->parser($url, $get);
    }

    public function catalogBranchListOrg($org_id, $page_size=23, $page=1, $fields=array(
            'items.adm_div', 'items.contact_groups', 'items.flags', 'items.address', 'items.rubrics','items.name_ex',
            'items.point', 'items.external_content', 'items.org', 'markers', 'widgets', 'filters', 'items.reviews'
        ))
    {
        $get = array(
            'org_id'=>$org_id,
            'page_size'=>$page_size,
            'page'=>$page,
            'fields'=>implode("%2C",$fields),
            'key'=>$this->_key,
            'format'=>$this->_format
        );
        $url = $this->_url_catalog_branch_list;
        return $this->parser($url,$get);
    }

    public function catalogBranchList($rubric_id, $page, $page_size='23', $fields=array(
            'items.adm_div', 'items.contact_groups', 'items.flags', 'items.address', 'items.rubrics',
            'items.name_ex', 'items.point', 'items.external_content', 'items.org', 'markers', 'widgets', 'filters',
            'items.reviews', 'context_rubrics'
        ))
    {
        $get = array(
            'rubric_id'=>$rubric_id,
            'page_size'=>$page_size,
            'page'=>$page,
            'region_id'=>$this->_region,
            'fields'=>implode("%2C",$fields),
            'key'=>$this->_key,
            'format'=>$this->_format
        );
        $url = $this->_url_catalog_branch_list;
        return $this->parser($url,$get);
    }

    public function catalogRubricList($parent_id=0, $sort='popularity', $fields=array('items.rubrics'))
    {
        $get = array(
            'parent_id'=>$parent_id,
            'region_id'=>$this->_region,
            'sort'=>$sort,
            'fields'=>implode("%2C",$fields),
            'key'=>$this->_key,
            'format'=>$this->_format
        );
        $url = $this->_url_catalog_rubric_list;
        return $this->parser($url, $get);
    }

    public function geoGet($id,$fields=array(
        'items.attraction', 'items.region_id', 'items.adm_div', 'items.description',
        'items.photos', 'items.context', 'items.links', 'items.level_count', 'items.address',
        'items.address.is_conditional', 'items.is_paid', 'items.access', 'items.access_name',
        'items.access_comment', 'items.capacity', 'items.schedule', 'items.geometry.hover',
        'items.geometry.centroid', 'items.geometry.bound', 'items.geometry.selection', 'items.floors'))
    {
        $get = array(
            'id'=>$id,
            'region_id'=>$this->_region,
            'fields'=>implode("%2C",$fields),
            'key'=>$this->_key,
            'format'=>$this->_format
        );
        $url = $this->_url_geo_get;
        return $this->parser($url,$get);
    }

    public function geoSearch($q, $page_size, $fields = array(
        'items.address', 'items.adm_div', 'items.address.is_conditional', 'items.geometry.hover', 'items.geometry.centroid',
        'items.geometry.bound', 'items.geometry.selection', 'items.floors'
    ))
    {
        $get = array(
            'q'=>urlencode($q),
            'page_size'=>$page_size,
            'region_id'=>$this->_region,
            'fields'=>implode("%2C",$fields),
            'key'=>$this->_key,
            'format'=>$this->_format
        );
        $url = $this->_url_geo_search;
        return $this->parser($url, $get);
    }

    public function suggest($what, $geo_limit=8, $routes_limit=8, $stations_limit=8,
                            $type=array('geo', 'station', 'route'),
                            $geo_type=array('city','district','house','settlement', 'street', 'living_area', 'place', 'sight'))
    {
        $get = array(
            'key'=>$this->_key,
            'where'=>$this->_region,
            'lang'=>'ru',
            'output'=>$this->_format,
            'what'=>urlencode($what),
            'type'=>implode("%2C+",$type),
            'geo_type'=>implode("%2C",$geo_type),
            'geo_limit'=>$geo_limit,
            'routes_limit'=>$routes_limit,
            'stations_limit'=>$stations_limit
        );
        $url = $this->_url_suggest;
        return $this->parserOld($url,$get);
    }

    private function parser($url, $get=array()) {
        $response = $this->_parser->browser($url, $get, array(), 'http://2gis.ru/', false, false, 15, false);
        $json = json_decode($response);
        if ($json) {
            if (!isset($json->meta->code)) {
                return -2;
            } elseif ($json->meta->code != '200') {
                return array(
                    'code' => $json->meta->code,
                    'error' => $json->meta->error->message
                );
            } else {
                $result = array(
                    'code'=>$json->meta->code,
                    'items'=>$json->result->items,
                    'total'=>$json->result->total
                );
                if (isset($json->result->context_rubrics)) $result['context_rubrics'] = $json->result->context_rubrics;
                if (isset($json->result->filters)) $result['filters'] = $json->result->filters;
                if (isset($json->result->markers)) $result['markers'] = $json->result->markers;
                return $result;
            }
        } else {
            return -1;
        }
    }

    /**
     * Парсер совместимый с предыдущей версией методов 2GIS-API
     * когда вместо meta и result->items были response и result->data
     * @param $url
     * @param array $get
     * @return array|int
     */
    private function parserOld($url, $get=array()) {
        $response = $this->_parser->browser($url, $get, array(), 'http://2gis.ru/', false, false, 15, false);
        $json = json_decode($response);
        if ($json) {
            if (!isset($json->response->code)) {
                return -2;
            } elseif ($json->response->code != '200') {
                return array(
                    'code' => $json->response->code,
                    'error' => $json->response->error->message
                );
            } else {
                return array(
                    'code'=>$json->response->code,
                    'items'=>$json->result->data,
                    'total'=>$json->result->total->total_count
                );
            }
        } else {
            return -1;
        }
    }


}