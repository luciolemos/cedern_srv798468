<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

class StoreBookshopIiPageAction extends StoreBookshopPageAction
{
    protected function getTemplate(): string
    {
        return 'pages/store-bookshop-ii.twig';
    }

    protected function getFallbackBasePath(): string
    {
        return '/loja/livraria-ii';
    }

    protected function getPageTitle(): string
    {
        return 'Livraria II | Loja | CEDE';
    }

    protected function getPageDescription(): string
    {
        return 'Explore uma segunda visualizacao da Livraria do CEDE, '
            . 'com o catalogo publico organizado em lista e ficha tecnica expandida.';
    }
}
