<?php
/**
 * Created by PhpStorm.
 * User: ruud
 * Date: 14/04/14
 * Time: 17:31
 */

namespace Kunstmaan\NodeSearchBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Kunstmaan\AdminBundle\Helper\FormWidgets\Tabs\Tab;
use Kunstmaan\NodeBundle\Event\AdaptFormEvent;

use Kunstmaan\NodeSearchBundle\Form\NodeSearchAdminType;
use Kunstmaan\NodeSearchBundle\Helper\FormWidgets\SearchFormWidget;

/**
 * NodeListener
 */
class NodeListener
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param AdaptFormEvent $event
     */
    public function adaptForm(AdaptFormEvent $event)
    {
        $searchWidget = new SearchFormWidget($event->getNode(), $this->em);
        $searchWidget->addType('node_search', new NodeSearchAdminType());

        $tabPane = $event->getTabPane();
        $tabPane->addTab(new Tab('Searcher', $searchWidget));
    }

}
