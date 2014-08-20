<?php
/**
 * Created by PhpStorm.
 * User: ruud
 * Date: 04/03/14
 * Time: 18:18
 */

namespace Kunstmaan\NodeSearchBundle\Search;


use Elastica\Query;
use Elastica\Search;
use Elastica\Suggest;
use Kunstmaan\SearchBundle\Search\Search as SearchLayer;

abstract class AbstractElasticaSearcher implements SearcherInterface
{
    protected   $indexName;
    protected   $indexType;
    protected   $type;

    /**
     * @var SearchLayer
     */
    protected   $search;

    protected   $query;
    protected   $data;
    protected   $language;
    protected   $contentType;

    public function __construct()
    {
        $this->query = new Query();
    }

    abstract public function defineSearch($query, $lang, $type);

    public function search($offset = null, $size = null){
        $this->defineSearch($this->data, $this->language, $this->contentType);
        $this->setPagination($offset, $size);

        return $this->getSearchResult();
    }

    public function getSuggestions()
    {
        $suggestPhrase = new Suggest\Phrase('content-suggester', 'content');
        $suggestPhrase->setText($this->data);
        $suggestPhrase->setAnalyzer('suggestion_analyzer_'.$this->language);
        $suggestPhrase->setHighlight("<strong>", "</strong>");
        $suggestPhrase->setConfidence(2);
        $suggestPhrase->setSize(1);

        $suggest = new Suggest($suggestPhrase);
        $this->query->setSuggest($suggest);

        return $this->getSearchResult();
    }

    public function getSearchResult()
    {
        $index = $this->search->getIndex($this->getIndexName());
        $search = new Search($this->search->getClient());
        $search->addIndex($index);
        $search->addType($index->getType($this->indexType.'_'.$this->language));

        $result = $search->search($this->query);

        return $result;
    }

    public function setPagination($offset, $size)
    {
        if (is_int($offset)) {
            $this->query->setFrom($offset);
        }

        if (is_int($size)) {
            $this->query->setSize($size);
        }
    }

    /**
     * @param mixed $indexName
     */
    public function setIndexName($indexName)
    {
        $this->indexName = $indexName;
    }

    /**
     * @return mixed
     */
    public function getIndexName()
    {
        return $this->indexName;
    }

    /**
     * @param mixed $indexType
     */
    public function setIndexType($indexType)
    {
        $this->indexType = $indexType;
    }

    /**
     * @return mixed
     */
    public function getIndexType()
    {
        return $this->indexType;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * @return mixed
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param mixed $search
     */
    public function setSearch($search)
    {
        $this->search = $search;
    }

    /**
     * @return mixed
     */
    public function getSearch()
    {
        return $this->search;
    }
}