<?php
/**
 * Created by PhpStorm.
 * User: ruud
 * Date: 03/03/14
 * Time: 16:42
 */

namespace Kunstmaan\NodeSearchBundle\Configuration;


use Kunstmaan\NodeBundle\Entity\Node;
use Kunstmaan\NodeBundle\Entity\NodeTranslation;
use Kunstmaan\NodeSearchBundle\Event\Events;
use Kunstmaan\NodeSearchBundle\Event\IndexNodeEvent;
use Kunstmaan\NodeSearchBundle\Helper\HasCustomSearchType;
use Kunstmaan\PagePartBundle\Helper\HasPagePartsInterface;
use Kunstmaan\SearchBundle\Configuration\SearchConfigurationInterface;
use Kunstmaan\SearchBundle\Helper\ShouldBeIndexed;
use Kunstmaan\SearchBundle\Search\AnalysisFactory;
use Kunstmaan\UtilitiesBundle\Helper\ClassLookup;
use Symfony\Component\HttpFoundation\Request;

class NodePagesConfiguration implements SearchConfigurationInterface
{
    private $indexName;
    private $indexType;
    private $provider;
    private $locales;
    private $analyzerLanguages;
    private $em;
    private $container;
    private $documents;

    public function __construct($name, $type, $provider, $locales, $analyzerLanguages, $em, $container)
    {
        $this->indexName = $name;
        $this->indexType = $type;
        $this->provider = $provider;
        $this->locales = explode('|', $locales);
        $this->analyzerLanguages = $analyzerLanguages;
        $this->em = $em;
        $this->container = $container;
    }

    public function createIndex()
    {
        //build new index
        $index = $this->provider->createIndex($this->indexName);

        //create analysis
        $analysis = $this->container->get('kunstmaan_search.search.factory.analysis');
        foreach ($this->locales as $locale) {
            $analysis->addIndexAnalyzer($locale);
        }

        //create index with analysis
        $this->setAnalysis($index, $analysis);

        //create mapping
        foreach ($this->locales as $locale) {
            $this->setMapping($index, $locale);
        }

    }

    public function populateIndex()
    {
        $nodeRepository = $this->em->getRepository('KunstmaanNodeBundle:Node');

        $nodes = $nodeRepository->getAllTopNodes();

        foreach ($this->locales as $lang) {
            foreach ($nodes as $node) {
                $this->createNodeDocuments($node, $lang);
            }
        }

        $this->provider->addDocuments($this->documents);
    }

    public function indexNode(Node $node, $lang)
    {
        $this->createNodeDocuments($node, $lang);
        $this->provider->addDocuments($this->documents);
    }

    public function createNodeDocuments(Node $node, $lang)
    {
        $nodeTranslation = $node->getNodeTranslation($lang);
        if ($nodeTranslation) {
            if ($this->indexNodeTranslation($nodeTranslation)) {
                $this->indexChildren($node, $lang);
            }
        }
    }

    public function indexChildren(Node $node, $lang)
    {
        foreach ($node->getChildren() as $childNode) {
            $this->indexNode($childNode, $lang);
        }
    }

    /**
     * @param  NodeTranslation $nodeTranslation
     * @return bool            Return true of document has been indexed
     */
    public function indexNodeTranslation(NodeTranslation $nodeTranslation)
    {
        // Only index online NodeTranslations
        if ($nodeTranslation->isOnline()) {
            // Retrieve the public NodeVersion
            $publicNodeVersion = $nodeTranslation->getPublicNodeVersion();
            if ($publicNodeVersion) {
                $node = $nodeTranslation->getNode();
                // Retrieve the referenced entity from the public NodeVersion
                $page = $publicNodeVersion->getRef($this->em);
                // If the page doesn't implement ShouldBeIndexed interface or it return true on shouldBeIndexed, index the page
                if (!($page instanceof ShouldBeIndexed) or $page->shouldBeIndexed()) {
                    $doc = array(
                        "node_id"               => $node->getId(),
                        "nodetranslation_id"    => $nodeTranslation->getId(),
                        "nodeversion_id"        => $publicNodeVersion->getId(),
                        "title"                 => $nodeTranslation->getTitle(),
                        "lang"                  => $nodeTranslation->getLang(),
                        "slug"                  => $nodeTranslation->getFullSlug(),
                    );
                    $this->container->get('logger')->info("Indexing document : " . implode(', ', $doc));

                    // Type
                    $type = ClassLookup::getClassName($page);
                    if($page instanceof HasCustomSearchType){
                        $type = $page->getSearchType();
                    }
                    $doc = array_merge($doc, array("type" => $type));
                    // Analyzer field

                    $language = $this->analyzerLanguages[$nodeTranslation->getLang()]['analyzer'];
                    $doc['contentanalyzer'] = $language;

                    // Parent and Ancestors

                    $parent = $node->getParent();
                    $parentNodeTranslation = null;

                    if ($parent) {
                        $doc = array_merge($doc, array("parent" => $parent->getId()));
                        $ancestors = array();
                        do {
                            $ancestors[] = $parent->getId();
                            $parent = $parent->getParent();
                        } while ($parent);
                        $doc = array_merge($doc, array("ancestors" => $ancestors));
                    }

                    // Content

                    $content = '';
                    if ($page instanceof HasPagePartsInterface) {
                        if (!$this->container->isScopeActive('request')) {
                            $this->container->enterScope('request');
                            $request = new Request();
                            $request->setLocale($nodeTranslation->getLang());
                            $this->container->set('request', $request, 'request');
                        }
                        $pageparts = $this->em
                            ->getRepository('KunstmaanPagePartBundle:PagePartRef')
                            ->getPageParts($page);
                        $renderer = $this->container->get('templating');
                        $view = 'KunstmaanNodeSearchBundle:PagePart:view.html.twig';
                        $content = strip_tags($renderer->render($view, array('page' => $page, 'pageparts' => $pageparts, 'pagepartviewresolver' => $this)));
                    }
                    $doc = array_merge($doc, array("content" => $content));

                    // Trigger index node event
                    $event = new IndexNodeEvent($page, $doc);

                    $dispatcher = $this->container->get('event_dispatcher');
                    $dispatcher->dispatch(Events::INDEX_NODE, $event);

                    // Add document to index
                    $uid = "nodetranslation_" . $nodeTranslation->getId();

                    $this->documents[] = $this->provider->createDocument($uid, $doc, $this->indexType.'_'.$nodeTranslation->getLang(), $this->indexName);
                }

                return true; // return true even if the page itself should not be indexed. This makes sure its children are being processed (i.e. structured nodes)
            }
        }

        return false;
    }

    public function deleteNodeTranslation(NodeTranslation $nodeTranslation)
    {
        $uid = "nodetranslation_" . $nodeTranslation->getId();
        $this->provider->deleteDocument($this->indexName, $this->indexType, $uid);
    }

    public function deleteIndex()
    {
        $this->provider->deleteIndex($this->indexName);
    }

    public function setAnalysis(\Elastica\Index $index, AnalysisFactory $analysis)
    {
        $index->create(
            array(
                'number_of_shards' => 4,
                'number_of_replicas' => 1,
                'analysis' => $analysis->build()
            ));
    }

    public function setMapping(\Elastica\Index $index, $lang = 'en'){
        $mapping = new \Elastica\Type\Mapping();
        $mapping->setType($index->getType($this->indexType.'_'.$lang));
        $mapping->setParam('analyzer', 'index_analyzer_'.$lang);
        $mapping->setParam('_boost', array('name' => '_boost', 'null_value' => 1.0));

        $mapping->setProperties(array(
            'node_id'      => array('type' => 'integer', 'include_in_all' => false, 'index' => 'not_analyzed'),
            'nodetranslation_id'      => array('type' => 'integer', 'include_in_all' => false, 'index' => 'not_analyzed'),
            'nodeversion_id'      => array('type' => 'integer', 'include_in_all' => false, 'index' => 'not_analyzed'),
            'title'    => array('type' => 'string', 'include_in_all' => true),
            'lang' => array('type' => 'string', 'include_in_all' => true, 'index' => 'not_analyzed'),
            'slug'  => array('type' => 'string', 'include_in_all' => false, 'index' => 'not_analyzed'),
            'type'     => array('type' => 'string', 'include_in_all' => false, 'index' => 'not_analyzed'),
            'contentanalyzer'  => array('type' => 'string', 'include_in_all' => true, 'index' => 'not_analyzed'),
            'content'=> array('type' => 'string', 'include_in_all' => true),
            '_boost'  => array('type' => 'float', 'include_in_all' => false)
        ));

        $mapping->send();
        $index->refresh();
    }
}