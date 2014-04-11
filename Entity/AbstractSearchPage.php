<?php

namespace Kunstmaan\NodeSearchBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kunstmaan\NodeBundle\Entity\AbstractPage;
use Kunstmaan\NodeBundle\Helper\RenderContext;
use Kunstmaan\NodeSearchBundle\PagerFanta\Adapter\SearcherRequestAdapter;
use Kunstmaan\NodeSearchBundle\PagerFanta\Adapter\SherlockRequestAdapter;
use Kunstmaan\SearchBundle\Helper\ShouldBeIndexed;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Sherlock\Sherlock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AbstractSearchPage, extend this class to create your own SearchPage and extends the standard functionality
 *
 */
class AbstractSearchPage extends AbstractPage implements ShouldBeIndexed
{
    /**
     * Default number of search results to show per page (default: 10)
     * @var int
     */
    public $defaultperpage = 10;

    /**
     * @param ContainerInterface $container
     * @param Request            $request
     * @param RenderContext      $context
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|void
     */
    public function service(ContainerInterface $container, Request $request, RenderContext $context)
    {
        // Retrieve the current page number from the URL, if not present of lower than 1, set it to 1
        $pagenumber = $request->get("page");
        if (!$pagenumber or $pagenumber < 1) {
            $pagenumber = 1;
        }
        // Retrieve the search parameters
        $querystring = $request->get("query");
        $querytag = $request->get("tag");
        $queryrtag = $request->get("rtag");
        $querytype = $request->get("type");
        $lang = $request->getLocale();
        $tags = array();
        // Put the tags in an array
        if ($querytag and $querytag != '') {
            $tags = explode(',', $querytag);
            if ($queryrtag and $queryrtag != '') {
                unset($tags[$queryrtag]);
                $tags = array_merge(array_diff($tags, array($queryrtag)));
            }
        }
        // Perform a search if there is a querystring available
        if ($querystring and $querystring != "") {
            $pagerfanta = $this->search($container, $querystring, $querytype, $tags, $lang, $pagenumber);
            $context['q_query'] = $querystring;
            $context['q_tags'] = implode(',', $tags);
            $context['s_tags'] = $tags;
            $context['q_type'] = $querytype;
            $context['pagerfanta'] = $pagerfanta;
        }
    }

    /**
     * @param ContainerInterface $container
     * @param string             $querystring
     * @param string             $type
     * @param array              $tags
     * @param string             $lang
     * @param int                $pagenumber
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return Pagerfanta
     */
    public function search(ContainerInterface $container, $querystring, $type, array $tags, $lang, $pagenumber)
    {
        $searcher = $container->get($this->getSearcher());
        $searcher->setData($querystring);
        $searcher->setContentType($type);
        $searcher->setLanguage($lang);

        $adapter = new SearcherRequestAdapter($searcher);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($this->defaultperpage);
        try {
            $pagerfanta->setCurrentPage($pagenumber);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    /**
     * @return array
     */
    public function getPossibleChildTypes()
    {
        return array();
    }

    /*
     * return string
     */
    public function getDefaultView()
    {
        return "KunstmaanNodeSearchBundle:AbstractSearchPage:view.html.twig";
    }

    /**
     * @return boolean
     */
    public function shouldBeIndexed()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getSearcher()
    {
        return 'kunstmaan_node_search.search.node';
    }
}
