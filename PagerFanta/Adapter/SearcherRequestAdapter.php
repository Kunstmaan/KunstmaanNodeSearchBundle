<?php

namespace Kunstmaan\NodeSearchBundle\PagerFanta\Adapter;

use Kunstmaan\SearchBundle\Search\Search;
use Pagerfanta\Adapter\AdapterInterface;
use Sherlock\requests\SearchRequest;

class SearcherRequestAdapter implements AdapterInterface
{
    /**
     * @var Search
     */
    private $searcher;

    /**
     * @var SearchRequest
     */
    private $response;
    private $fullResponse;

    public function __construct($searcher)
    {
        $this->searcher = $searcher;
        $this->fullResponse = $this->searcher->search();
    }

    public function getResponse()
    {
        return $this->fullResponse;
    }

    public function getFullResponse()
    {
        return $this->fullResponse;
    }

    public function getSuggestions()
    {
        $result = $this->searcher->getSuggestions();
        $suggests = $result->getSuggests();

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
        return $this->response->getResults();
    }
}
