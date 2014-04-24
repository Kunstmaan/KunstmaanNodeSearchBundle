<?php

namespace Kunstmaan\NodeSearchBundle\PagerFanta\Adapter;

use Kunstmaan\NodeSearchBundle\Search\SearcherInterface;
use Pagerfanta\Adapter\AdapterInterface;
use Elastica\ResultSet;

class SearcherRequestAdapter implements AdapterInterface
{
    /**
     * @var SearcherInterface
     */
    private $searcher;

    /**
     * @var ResultSet
     */
    private $response;

    /**
     * @var ResultSet
     */
    private $fullResponse;

    /**
     * @var ResultSet
     */
    private $suggests;

    public function __construct(SearcherInterface $searcher)
    {
        $this->searcher = $searcher;
        $this->fullResponse = $this->searcher->search();
    }

    public function getResponse()
    {
        return $this->createBCResponse($this->fullResponse);
    }

    public function getFullResponse()
    {
        return $this->createBCResponse($this->fullResponse);
    }

    public function getSuggestions()
    {
        if(!isset($this->suggests)){
            $this->suggests = $this->searcher->getSuggestions();
        }
        $suggests = $this->suggests->getSuggests();

        return $suggests['content-suggester'][0]['options'];
    }

    /**
     * Returns the number of results.
     *
     * @return integer The number of results.
     */
    public function getNbResults()
    {
        return $this->fullResponse->getTotalHits();
    }

    /**
     * Returns an slice of the results.
     *
     * @param integer $offset The offset.
     * @param integer $length The length.
     *
     * @return array|\Traversable The slice.
     */
    public function getSlice($offset, $length)
    {
        $this->response = $this->searcher->search($offset, $length);
        $response = $this->createBCResponse($this->response);

        return $response['hits'];
    }

    public function createBCResponse(ResultSet $result)
    {
        $data = $result->getResults();
        $bcResponse = array();
        foreach($data as $item){
            $content = array();
            $content['_source'] = $item->getData();

            $highlights = $item->getHighlights();
            if(!empty($highlights)){
                $content['highlight'] = $highlights;
            }

            $bcResponse['hits'][] = $content;
        }

        if($result->hasFacets()){
            $bcResponse['facets'] = $result->getFacets();

        }

        return $bcResponse;
    }
}
