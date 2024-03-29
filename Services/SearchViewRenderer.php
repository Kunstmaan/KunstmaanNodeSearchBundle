<?php

namespace Kunstmaan\NodeSearchBundle\Services;

use Kunstmaan\NodeBundle\Entity\CustomViewDataProviderInterface;
use Kunstmaan\NodeBundle\Entity\NodeTranslation;
use Kunstmaan\NodeBundle\Entity\PageInterface;
use Kunstmaan\NodeBundle\Entity\PageViewDataProviderInterface;
use Kunstmaan\NodeBundle\Helper\RenderContext;
use Kunstmaan\NodeSearchBundle\Helper\IndexablePagePartsService;
use Kunstmaan\NodeSearchBundle\Helper\SearchViewTemplateInterface;
use Kunstmaan\PagePartBundle\Helper\HasPagePartsInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class SearchViewRenderer
{
    /** @var Environment */
    private $twig;

    /** @var IndexablePagePartsService */
    private $indexablePagePartsService;

    /** @var RequestStack */
    private $requestStack;
    /** @var PsrContainerInterface|null */
    private $viewDataProviderServiceLocator;

    public function __construct(Environment $twig, IndexablePagePartsService $indexablePagePartsService, RequestStack $requestStack, PsrContainerInterface $viewDataProviderServiceLocator = null)
    {
        if (null === $viewDataProviderServiceLocator) {
            @trigger_error(sprintf('Not passing a service locator of page renderer services to the "$viewDataProviderServiceLocator" parameter of "%s" is deprecated since KunstmaanNodeSearchBundle 5.9 and will be required in KunstmaanNodeSearchBundle 6.0.', __METHOD__), E_USER_DEPRECATED);
        }

        $this->twig = $twig;
        $this->indexablePagePartsService = $indexablePagePartsService;
        $this->requestStack = $requestStack;
        $this->viewDataProviderServiceLocator = $viewDataProviderServiceLocator;
    }

    public function renderDefaultSearchView(NodeTranslation $nodeTranslation, HasPagePartsInterface $page, string $defaultView = '@KunstmaanNodeSearch/PagePart/view.html.twig')
    {
        $html = $this->twig->render($defaultView, [
            'locale' => $nodeTranslation->getLang(),
            'page' => $page,
            'pageparts' => $this->indexablePagePartsService->getIndexablePageParts($page),
            'indexMode' => true,
        ]);

        return $this->removeHtml($html);
    }

    public function renderCustomSearchView(NodeTranslation $nodeTranslation, SearchViewTemplateInterface $page, ContainerInterface $container = null)
    {
        $renderContext = new RenderContext([
            'locale' => $nodeTranslation->getLang(),
            'page' => $page,
            'indexMode' => true,
            'nodetranslation' => $nodeTranslation,
        ]);

        // NEXT_MAJOR: Remove if and `$page->service` call.
        if ($page instanceof PageInterface && null !== $container) {
            $page->service($container, $this->requestStack->getCurrentRequest(), $renderContext);
        }

        //NEXT_MAJOR: Remove "null !== $this->viewDataProviderServiceLocator" check
        if ($page instanceof CustomViewDataProviderInterface && null !== $this->viewDataProviderServiceLocator) {
            $serviceId = $page->getViewDataProviderServiceId();

            if (!$this->viewDataProviderServiceLocator->has($serviceId)) {
                throw new \RuntimeException(sprintf('Missing page renderer service "%s"', $serviceId));
            }
            /** @var PageViewDataProviderInterface $service */
            $service = $this->viewDataProviderServiceLocator->get($serviceId);

            $service->provideViewData($nodeTranslation, $renderContext);
        }

        $html = $this->twig->render($page->getSearchView(), $renderContext->getArrayCopy());

        return $this->removeHtml($html);
    }

    public function removeHtml(string $text): string
    {
        if (empty(trim($text))) {
            return '';
        }

        $crawler = new Crawler();
        $crawler->addHtmlContent($text);
        $crawler->filter('style, script')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });
        $text = $crawler->html();

        $result = strip_tags($text);
        $result = trim(html_entity_decode($result, ENT_QUOTES));

        return $result;
    }
}
