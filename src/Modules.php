<?php

/*
 * This file is part of Chevere.
 *
 * (c) Rodolfo Berrios <rodolfo@chevere.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace DocsDeploy;

class Modules
{
    private Iterator $iterator;

    private array $links = [];

    private array $nav = [];

    private array $side = [];

    public function __construct(Iterator $iterator)
    {
        $this->iterator = $iterator;
    }

    public function withAddedNavLink(string $name, string $link): self
    {
        $new = clone $this;
        $new->links[$name] = $link;

        return $new;
    }

    public function execute(): void
    {
        $this->side['/'] = 'auto';
        $mainContents = $this->iterator->contents()['/'];
        foreach ($mainContents as $node) {
            if (! str_ends_with($node, '/')) {
                continue;
            }
            $this->setNavFor($node);
            $this->setSideFor($node);
        }
        foreach ($this->links as $name => $link) {
            $this->nav[] = $this->getNavLink($name, $link);
        }
    }

    public function nav(): array
    {
        return $this->nav;
    }

    public function side(): array
    {
        return $this->side;
    }

    public function getUsableNode(string $node): string
    {
        return strtr($node, [
            'README.md' => '',
            '.md' => '',
        ]);
    }

    private function setSideFor(string $node): void
    {
        $rootNode = "/${node}";
        $flags = $this->iterator->flags()[$rootNode];
        $contents = $this->iterator->contents()[$rootNode];
        $side = 'auto';
        if ($flags->hasNested() && $flags->hasReadme()) {
            $side = [];
            $side[] = $this->getSide($rootNode, $flags, $contents);
        }
        $this->side["/${node}"] = $side;
    }

    private function setNavFor(string $node): void
    {
        $title = $this->iterator->flags()['/']->naming()[$node]
            ?? $this->getTitle($node);
        $rootNode = "/${node}";
        $flags = $this->iterator->flags()[$rootNode];
        $contents = $this->iterator->contents()[$rootNode];
        if ($flags->hasReadme()) {
            $this->nav[] = $this->getNavLink($title, $rootNode);

            return;
        }
        if (! $flags->hasNested()) {
            return;
        }
        $navMenu = [
            'text' => $title,
            'ariaLabel' => $title . ' Menu',
        ];
        $files = [];
        foreach ($contents as $subNode) {
            if (! str_ends_with($subNode, '/')) {
                $files[] = $subNode;

                continue;
            }
        }
        if ($files === []) {
            foreach ($contents as $subNode) {
                $subRoot = $rootNode . $subNode;
                $subFlags = $this->iterator->flags()[$subRoot] ?? null;
                $subContents = $this->iterator->contents()[$subRoot];
                $title = $flags->naming()[$subNode] ?? $this->getTitle($subNode);
                $navMenu['items'][] = [
                    'text' => $title,
                    'items' => $this->getNavItems($subRoot, $subFlags, $subContents),
                ];
            }
            $this->nav[] = $navMenu;

            return;
        }
        $navMenu['items'] = $this->getNavItems($rootNode, $flags, $files);
        $this->nav[] = $navMenu;
    }

    private function getNavItems(string $rootNode, Flags $flags, array $contents): array
    {
        $items = [];
        foreach ($contents as $node) {
            $items[] = $this->getNavLink(
                $flags->naming()[$node] ?? $this->getTitle($node),
                $this->getUsableNode($rootNode . $node)
            );
        }

        return $items;
    }

    private function getSide(string $rootNode, Flags $flags, array $contents): array
    {
        $main = [];
        $groups = [];
        $items = [];
        foreach ($contents as $subNode) {
            if (str_ends_with($subNode, '/')) {
                $items[] = [
                    'title' => $subNode,
                    'collapsable' => false,
                    'children' => $this->iterator->contents()[$rootNode . $subNode],
                ];
            } else {
                $main[] = $rootNode . $subNode;
            }
        }
        array_unshift($items, [
            'title' => 'MAIN',
            'collapsable' => false,
            'children' => $main,
        ]);

        return $items;
    }

    private function getNavLink(string $name, string $link): array
    {
        return [
            'text' => $name,
            'link' => $link,
        ];
    }

    private function getTitle(string $name): string
    {
        return ucfirst(strtr($name, [
            '/' => '',
            '-' => ' ',
            '.md' => '',
        ]));
    }
}
