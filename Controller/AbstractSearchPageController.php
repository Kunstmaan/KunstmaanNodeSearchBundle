<?php

namespace Kunstmaan\NodeSearchBundle\Controller;

use Kunstmaan\NodeBundle\Helper\RenderContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated since KunstmaanNodeSearchBundle 5.9 and will be removed in KunstmaanNodeSearchBundle 6.0.
 */
class AbstractSearchPageController extends Controller
{
    public function serviceAction(Request $request)
    {
        if ($request->query->has('query')) {
            $search = $this->container->get('kunstmaan_node_search.search.service');
            $pagerfanta = $search->search();
            /** @var RenderContext $renderContext */
            $renderContext = $search->getRenderContext();
            $renderContext['pagerfanta'] = $pagerfanta;

            $request->attributes->set('_renderContext', $renderContext);
        }
    }
}
