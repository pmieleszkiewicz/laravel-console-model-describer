<?php

declare(strict_types=1);

namespace PMieleszkiewicz\ModelDescriber\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

abstract class AbstractModelDescriber extends Command
{
    /** @var \Illuminate\Config\Repository */
    protected $config;

    public function __construct(Repository $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    /**
     * Returns class full namespace path
     *
     * @param string $class
     * @return string
     */
    protected function parseClassName(string $class): string
    {
        if (Str::startsWith($class, "\\")) {
            return $class;
        }

        $namespace = $this->config->get('model-describer.default_namespace', static::DEFAULT_MODEL_NAMESPACE);

        return sprintf('%s\%s', $namespace, $class);
    }
}
