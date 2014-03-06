<?php
/**
 * Created by PhpStorm.
 * User: ruud
 * Date: 04/03/14
 * Time: 17:51
 */

namespace Kunstmaan\NodeSearchBundle\Search;


class OldNodeSearcher extends AbstractElasticaSearcher implements SearcherInterface
{
    public function defineSearch($query, $lang, $type)
    {
        $elasticaFilterLang = new \Elastica\Filter\Term();
        $elasticaFilterLang->setTerm('lang', $lang);

        $elasticaFilterAnd = new \Elastica\Filter\BoolAnd();
        $elasticaFilterAnd->addFilter($elasticaFilterLang);

        $elasticaQueryString  = new \Elastica\Query\Match();
        $elasticaQueryString->setFieldQuery('content', $query);
        $elasticaQueryString->setFieldMinimumShouldMatch('content', '80%');


        $elasticaQueryTitle  = new \Elastica\Query\QueryString();
        $elasticaQueryTitle->setDefaultField('title');
        $elasticaQueryTitle->setQuery($query);


        $elasticaQueryBool = new \Elastica\Query\Bool();
        $elasticaQueryBool->addShould($elasticaQueryTitle);
        $elasticaQueryBool->addShould($elasticaQueryString);
        $elasticaQueryBool->setMinimumNumberShouldMatch(1);


        $elasticaFacet    = new \Elastica\Facet\Terms('type');
        $elasticaFacet->setField('type');
        $elasticaFacet->setFilter($elasticaFilterAnd);
        $elasticaFacet->setSize(5);


        $this->query->addFacet($elasticaFacet);
        $this->query->setFilter($elasticaFilterAnd);
        $this->query->setQuery($elasticaQueryBool);
        $this->query->setHighlight(array(
            "pre_tags" => array("<strong>"),
            "post_tags" => array("</strong>"),
            'fields' => array(
                "content" => array(
                    "fragment_size" => 150,
                    "number_of_fragments" => 3
                )
            )
        ));
    }
} 