<?php namespace text\parse\tdop;

use lang\IllegalStateException;
use util\cmd\Console;
use text\parse\tdop\Parse;
use text\parse\tdop\Tokens;
use text\parse\tdop\node;
use text\StreamTokenizer;
use io\streams\FileInputStream;

class Instance {
  public $type, $parent, $properties= [];
}

class Parameter {
  public $value;

  public function __construct($value) {
    $this->value= $value;
  }
}

class Native extends Node {
  public $func;

  public function __construct($func) {
    $this->func= $func;
    $this->arity= 'native';
  }
}

class Execution {

  public function __construct($classpath, $args= []) {
    $this->classpath= $classpath;
    $this->declared= ['args' => $args];
    $this->types= [
      'Type' => ['parent' => null, 'properties' => ['name' => null], 'methods' => [
        'this' => ['member', [new Parameter('name')], [
          new Native(function($self, &$exit) {
            $self->properties['name']= $this->declared['name'];
          })
        ]],
        'newInstance' => ['member', [], [
          new Native(function($self, &$exit) {
            $class= $this->load($self->properties['name']);

            $instance= new Instance();
            $instance->type= $self->properties['name'];
            foreach ($class['properties'] as $name => $init) {
              $instance->properties[$name]= $init ? $this->run($init, false, $exit) : null;
            }

            // TODO: Invoke constructor
            return $instance;
          })
        ]],
        'methods' => ['member', [], [
          new Native(function($self, &$exit) {
            $class= $this->load($self->properties['name']);

            foreach ($class['methods'] as $name => $backing) {
              $method= new Instance();
              $method->type= 'Method';
              $method->properties['backing']= $backing;
              $method->properties['name']= $name;
              yield $method;
            }
          })
        ]]
      ]],
      'Method' => ['parent' => null, 'properties' => [], 'methods' => [
        'annotationPresent' => ['member', [new Parameter('name')], [
          new Native(function($self, &$exit) {
            if (!isset($self->properties['backing'][3])) return false;
            return $self->properties['backing'][3] === $this->declared['name'];
          })
        ]],
        'invoke' => ['member', [new Parameter('object')], [
          new Native(function($self, &$exit) {
            $calling= $this->declared;
            $test= false;
            $this->declared= ['this' => &$calling['object']];    // FIXME: Rest of parameters
            $return= null;

            foreach ($self->properties['backing'][2] as $statement) {
              $return= $this->run($statement, $test, $exit);
              if ($exit) break;
            }
            $this->declared= $calling;
            return $return;
          })
        ]]
      ]]
    ];
    $this->binary= [
      '~'   => function($a, $b) { return $a . $b; },
      '+'   => function($a, $b) { return $a + $b; },
      '-'   => function($a, $b) { return $a - $b; },
      '*'   => function($a, $b) { return $a * $b; },
      '×'   => function($a, $b) { return str_repeat($a, $b); },
      '/'   => function($a, $b) { return $a / $b; },
      '>'   => function($a, $b) { return $a > $b; },
      '<'   => function($a, $b) { return $a < $b; },
      '<='  => function($a, $b) { return $a <= $b; },
      '>='  => function($a, $b) { return $a >= $b; },
      '??'  => function($a, $b) { return isset($a) ? $a : $b; },
      '&&'  => function($a, $b) { return $a && $b; },
      '||'  => function($a, $b) { return $a || $b; },
      '=='  => function($a, $b) { return $a === $b; },
      '!='  => function($a, $b) { return $a !== $b; }
    ];
  }

  public function execute($nodes) {
    $exit= false;
    foreach ($nodes as $statement) {
      $return= $this->run($statement, false, $exit);
      if ($exit) return $return;
    }
    return null;  
  }

  private function load($type) {
    if (isset($this->types[$type])) return $this->types[$type];

    foreach ($this->classpath as $path) {
      $file= $path.DIRECTORY_SEPARATOR.$type.'.top';
      if (file_exists($file)) {
        $parse= new Parse(new Tokens(new StreamTokenizer(new FileInputStream($file))));
        $this->execute($parse->execute());

        if (isset($this->types[$type])) return $this->types[$type];
      }
    }
    throw new IllegalStateException('No class "'.$type.'"');
  }

  private function run(Node $node, $test, &$exit) {
    switch ($node->arity) {
      case 'literal':
        return $node->value;

      case 'offset':
        $value= $this->run($node->value[0], $test, $exit);
        $offset= $this->run($node->value[1], $test, $exit);
        if (isset($value[$offset])) {
          return $value[$offset];
        } else if ($test) {
          return null;
        } else {
          throw new IllegalStateException('Undefined index '.$offset.' in '.$node->value[0]->symbol->id);
        }

      case 'name':
        if (array_key_exists($node->value, $this->declared)) {
          return $this->declared[$node->value];
        } else if ($test) {
          return null;
        } else {
          throw new IllegalStateException('Undefined "'.$node->value.'"');
        }

      case 'member':
        $value= $this->run($node->value[0], $test, $exit);
        $member= $node->value[1]->value;

        if ($value instanceof Instance) {
          if (isset($this->types[$value->type]['methods'][$member])) {
            $method= $this->types[$value->type]['methods'][$member];
            $method[0]= $value;
            return $method;
          } else if ('class' === $member) {
            $class= new Instance();
            $class->type= 'Type';
            $class->properties['name']= $value->type;
            return $class;
          } else if (array_key_exists($member, $value->properties)) {
            return $value->properties[$member];
          } else if ($test) {
            return null;
          } else {
            throw new IllegalStateException('Unknown member '.$value->type.'::'.$member.' in '.$node->value[0]->symbol->id);
          }
        } else if ('length' === $member) {
          if (is_string($value)) return strlen($value);
          if (is_array($value)) return sizeof($value);
          throw new IllegalStateException('Value '.$node->value[0]->symbol->id.' does not have a length');
        } else {
          throw new IllegalStateException('Value '.$node->value[0]->symbol->id.' does not have members');
        }

      case 'function':
        list($name, $parameters, $statements)= $node->value;
        $this->declared[$name->symbol->id]= [null, $parameters, $statements];
        break;

      case 'class':
        list($type, $parent, $declarations)= $node->value;

        if ($parent) {
          $declaration= $this->load($parent->value);
          $declaration['parent']= $parent;
        } else {
          $declaration= ['parent' => null, 'properties' => [], 'methods' => []];
        }
        foreach ($declarations as $member) {
          if ('method' === $member->arity) {
            list($name, $parameters, $statements)= $member->value[1]->value;
            $declaration['methods'][$name->value]= ['member', $parameters, $statements, $member->value[0]->value];
          } else if ('property' === $member->arity && $member->value) {
            $declaration['properties'][$member->symbol->id]= $member->value[1];
          }
        }
        $this->types[$type->value]= $declaration;
        break;

      case 'new':
        list($type, $arguments)= $node->value;
        $class= $this->load($type->symbol->id);

        // Initialize
        $instance= new Instance();
        $instance->type= $type->symbol->id;
        foreach ($class['properties'] as $name => $init) {
          $instance->properties[$name]= $init ? $this->run($init, $test, $exit) : null;
        }

        // Invoke constructor
        if (isset($class['methods']['this'])) {
          list(, $parameters, $statements)= $class['methods']['this'];
          $locals= ['this' => &$instance];
          foreach ($parameters as $i => $parameter) {
            $locals[$parameter->value]= isset($arguments[$i])
              ? $this->run($arguments[$i], $test, $exit)
              : null
            ;
          }

          $calling= $this->declared;
          $this->declared= $locals;
          $return= null;
          foreach ($statements as $statement) {
            $return= $this->run($statement, $test, $exit);
            if ($exit) break;
          }
          $this->declared= $calling;
        }

        return $instance;

      case 'closure':
        list($parameter, $statements)= $node->value;
        return [null, [$parameter], $statements];

      case 'invoke':
        list($name, $arguments)= $node->value;
        list($instance, $parameters, $statements)= $this->run($name, $test, $exit);
        if (null === $instance) {
          $locals= [];
        } else {
          $locals= ['this' => &$instance];
        }
        foreach ($parameters as $i => $parameter) {
          if (!isset($arguments[$i])) {
            $locals[$parameter->value]= null;
          } else {
            $locals[$parameter->value]= $this->run($arguments[$i], $test, $exit);
          }
        }

        $calling= $this->declared;
        $stack= $exit;
        $exit= false;
        $this->declared= $locals;
        $return= null;
        foreach ($statements as $statement) {
          $return= $this->run($statement, $test, $exit);
          if ($exit) break;
        }

        if ($exit instanceof Instance) {
          // An exception, pass on up the stack
        } else {
          $exit= $stack;
        }
        $this->declared= $calling;
        return $return;

      case 'binary':
        if ('??' === $node->symbol->id) {
          $lhs= $this->run($node->value[0], true, $exit);
        } else {
          $lhs= $this->run($node->value[0], $test, $exit);
        }
        return $this->binary[$node->symbol->id]($lhs, $this->run($node->value[1], $test, $exit));

      case 'return':
        $exit= true;
        return $node->value ? $this->run($node->value, $test, $exit) : null;

      case 'throw':
        $exit= $this->run($node->value, $test, $exit);
        break;

      case 'try':
        list($statements, $catch)= $node->value;
        foreach ($statements as $statement) {
          $this->run($statement, $test, $exit);
          if ($exit instanceof Instance) {
            $this->declared[$catch[0]->value]= $exit;
            $exit= false;
            foreach ($catch[1] as $handling) {
              $this->run($handling, $test, $exit);
            }
            break;
          }
        }
        break;

      case 'assignment':
        list($target, $expression)= $node->value;
        if ('.' === $target->symbol->id) {
          return $this->declared[$target->value[0]->value]->properties[$target->value[1]->value]= $this->run($expression, $test, $exit);
        } else {
          return $this->declared[$target->value]= $this->run($expression, $test, $exit);
        }

      case 'if':
        list($condition, $statements, $otherwise)= $node->value;
        $result= $this->run($condition, $test, $exit);
        if ($result) {
          $run= $statements;
        } else if ($otherwise) {
          $run= $otherwise;
        } else {
          break;
        }

        foreach ($run as $statement) {
          $return= $this->run($statement, $test, $exit);
          if ($exit) return $return;
        }
        break;

      case 'foreach':
        list($variable, $expression, $statements)= $node->value;
        foreach ($this->run($expression, $test, $exit) as $element) {
          $this->declared[$variable->symbol->id]= $element;
          foreach ($statements as $statement) {
            $return= $this->run($statement, $test, $exit);
            if ($exit) return $return;
          }
        }
        break;

      case 'print':
        $result= $this->run($node->value, $test, $exit);
        $exit || Console::writeLine($result);
        break;

      case 'native':
        return $node->func->__invoke($this->declared['this'], $exit);

      default:
        throw new IllegalStateException('Cannot handle '.$node->arity);
    }
  }
}