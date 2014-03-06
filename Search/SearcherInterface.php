<?php
/**
 * Created by PhpStorm.
 * User: ruud
 * Date: 04/03/14
 * Time: 18:31
 */

namespace Kunstmaan\NodeSearchBundle\Search;


interface SearcherInterface
{
    public function search($offset = null, $size = null);
    public function defineSearch($query, $lang, $type);
    public function setPagination($offset, $size);
    public function setData($data);
    public function setLanguage($lang);
    public function setType($type);
    public function setIndexName($name);
}