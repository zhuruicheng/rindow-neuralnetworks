<?php
namespace Rindow\NeuralNetworks\Gradient\Core;

use InvalidArgumentException;
use LogicException;
use Traversable;
use ArrayAccess;
use Countable;
use IteratorAggregate;
use Rindow\NeuralNetworks\Gradient\Module;
use Rindow\NeuralNetworks\Gradient\Variable;
use Rindow\NeuralNetworks\Model\Model;

/**
 * @implements ArrayAccess<int,Module>
 * @implements IteratorAggregate<int,Module>
 */
class Modules implements Module, ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @var array<Module> $modules
     */
    protected ?string $name;
    /** @var array<Module> $modules */
    protected array $modules = [];
    protected bool $shapeInspection=true;

    /**
     * @param array<Module> $modules
     */
    public function __construct(
        ?array $modules=null,
        ?string $name=null,
    )
    {
        $this->name = $name;
        if($modules) {
            foreach($modules as $m) {
                if(!($m instanceof Module)) {
                    throw new InvalidArgumentException('moduels must be array of Module');
                }
            }
            $this->modules = $modules;
        }
    }
    public function name() : ?string
    {
        return $this->name;
    }

    public function add(Module $module) : void
    {
        $this->modules[] = $module;
    }

    public function shapeInspection() : bool
    {
        return $this->shapeInspection;
    }

    public function setShapeInspection(bool $enable) : void
    {
        if($this->shapeInspection==$enable)
            return;
        foreach ($this->submodules() as $module) {
            $module->setShapeInspection($enable);
        }
        $this->shapeInspection = $enable;
    }

    public function reverseSyncWeightVariables() : void
    {
    }

    public function submodules() : array
    {
        return $this->modules;
    }

    public function variables() : array
    {
        $variables = [];
        foreach ($this->submodules() as $module) {
            $variables = array_merge($variables,$module->variables());
        }
        return $variables;
    }

    /**
     * @return array<Variable>
     */
    public function parameterVariables() : array
    {
        $variables = [];
        foreach ($this->submodules() as $module) {
            if($module instanceof Model) {
                $variables = array_merge($variables,$module->parameterVariables());
            }
        }
        return $variables;
    }

    public function trainableVariables() : array
    {
        $variables = [];
        foreach ($this->submodules() as $module) {
            $variables = array_merge($variables,$module->trainableVariables());
        }
        return $variables;
    }

    public function offsetExists( $offset ) : bool
    {
        if(!is_int($offset)) {
            throw new LogicException('offset must be int');
        }
        if(!isset($this->modules[$offset])) {
            return false;
        }
        return true;
    }

    public function offsetGet( $offset ) : mixed
    {
        if(!$this->offsetExists($offset)) {
            throw new LogicException('no found the offset: '.$offset);
        }
        return $this->modules[$offset];
    }

    public function offsetSet( $offset , $value ) : void
    {
        throw new LogicException('unsupported operation on boolean type');
    }

    public function offsetUnset( $offset ) : void
    {
        throw new LogicException('unsupported operation on boolean type');
    }

    public function count() : int
    {
        return count($this->modules);
    }

    public function  getIterator() :  Traversable
    {
        foreach($this->modules as $i => $v) {
            yield $i => $v;
        }
    }

    public function isAwareOf(string $name) : bool
    {
        throw new LogicException('"isAwareOf" cannot be used with "Module Collection" objects.');        
    }
}