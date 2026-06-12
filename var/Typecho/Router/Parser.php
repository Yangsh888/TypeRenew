<?php

namespace Typecho\Router;

class Parser
{
    private array $defaultRegex;

    private array $routingTable;

    private array $params;

    public function __construct(array $routingTable)
    {
        $this->routingTable = $routingTable;

        $this->defaultRegex = [
            'string' => '(.%s)',
            'char' => '([^/]%s)',
            'digital' => '([0-9]%s)',
            'alpha' => '([_0-9a-zA-Z-]%s)',
            'alphaslash' => '([_0-9a-zA-Z-/]%s)',
            'split' => '((?:[^/]+/)%s[^/]+)',
        ];
    }

    public function match(array $matches): string
    {
        $params = explode(' ', $matches[1]);
        $paramsNum = count($params);
        $this->params[] = $params[0];

        return match ($paramsNum) {
            1 => sprintf($this->defaultRegex['char'], '+'),
            2 => sprintf($this->defaultRegex[$params[1]], '+'),
            3 => sprintf($this->defaultRegex[$params[1]], $params[2] > 0 ? '{' . $params[2] . '}' : '*'),
            4 => sprintf($this->defaultRegex[$params[1]], '{' . $params[2] . ',' . $params[3] . '}'),
            default => $matches[0],
        };
    }

    public function parse(): array
    {
        $result = [];

        foreach ($this->routingTable as $key => $route) {
            $this->params = [];
            $route['regx'] = preg_replace_callback(
                "/%([^%]+)%/",
                [$this, 'match'],
                preg_quote(str_replace(['[', ']', ':'], ['%', '%', ' '], $route['url']))
            );

            $route['regx'] = rtrim($route['regx'], '/');
            $route['regx'] = '|^' . $route['regx'] . '[/]?$|';

            $route['format'] = preg_replace("/\[([^\]]+)\]/", "%s", $route['url']);
            $route['params'] = $this->params;

            $result[$key] = $route;
        }

        return $result;
    }
}
