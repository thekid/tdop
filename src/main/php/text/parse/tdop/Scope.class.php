<?php namespace text\parse\tdop;

class Scope {
  public $parent;

  public function __construct(self $parent= null) {
    $this->parent= $parent;
  }

  public function define($name, $node) {
    $definition= clone $node;
    $definition->symbol->nud= function($self) { return $self; };
    $definition->symbol->led= null;
    $definition->symbol->std= null;
    $definition->symbol->lbp= 0;
    $this->defines[$name]= $definition;
  }

  public function find($name) {
    return isset($this->defines[$name]) ? $this->defines[$name] : null;
  }
}