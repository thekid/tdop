<?php namespace text\parse\tdop;

class Parse {
  private $tokens, $token, $scope;
  private $symbols= [];

  public function __construct($tokens, $scope= null) {
    $this->tokens= $tokens->getIterator();
    $this->scope= $scope ?: new Scope(null);
    $this->setup();
  }

  private function setup() {
    $this->symbol(':');
    $this->symbol(';');
    $this->symbol(',');
    $this->symbol(')');
    $this->symbol(']');
    $this->symbol('}');
    $this->symbol('else');

    $this->symbol('(end)');
    $this->symbol('(name)');
    $this->symbol('(print)');
    $this->symbol('(literal)')->nud= function($node) { return $node; };    
    $this->symbol('args')->nud= function($node) { return $node; };
    $this->symbol('this')->nud= function($node) { return $node; };

    $this->constant('true', true);
    $this->constant('false', false);
    $this->constant('null', null);

    $this->infix('->', 10, function($node, $left) {
      $this->scope->define($left->value, $left);

      $this->token= $this->expect('{');
      $statements= $this->statements();
      $this->token= $this->expect('}');

      $node->value= [$left, $statements];
      $node->arity= 'closure';
      return $node;
    });

    $this->infixr('??', 30);
    $this->infixr('&&', 30);
    $this->infixr('||', 30);

    $this->infixr('==', 40);
    $this->infixr('!=', 40);
    $this->infixr('<', 40);
    $this->infixr('<=', 40);
    $this->infixr('>', 40);
    $this->infixr('>=', 40);

    $this->infix('~', 50);
    $this->infix('+', 50);
    $this->infix('-', 50);

    $this->infix('*', 60);
    $this->infix('/', 60);
    $this->infix('×', 60);

    $this->infix('(', 80, function($node, $left) {
      $arguments= $this->arguments();
      $this->token= $this->expect(')');
      $node->value= [$left, $arguments];
      $node->arity= 'invoke';
      return $node;
    });

    $this->infix('[', 80, function($node, $left) {
      $expr= $this->expression(0);
      $this->token= $this->expect(']');
      $node->value= [$left, $expr];
      $node->arity= 'offset';
      return $node;
    });

    $this->infix('.', 80, function($node, $left) {
      $node->value= [$left, $this->token];
      $node->arity= 'member';
      $this->token= $this->advance();
      return $node;
    });

    $this->suffix('++', 50);

    $this->prefix('!');

    $this->assignment('=');
    $this->assignment('+=');
    $this->assignment('-=');

    $this->prefix('new', function($node) {
      $t= $this->token;
      $this->token= $this->advance();

      $this->token= $this->expect('(');
      $arguments= $this->arguments();
      $this->token= $this->expect(')');

      $node->arity= 'new';
      $node->value= [$t, $arguments];
      return $node;
    });

    $this->stmt('var', function($node) {
      $t= $this->token;
      $this->scope->define($t->value, $t);

      $this->token= $this->advance();
      $this->token= $this->expect('=');

      $node->value= [$t, $this->expression(0)];
      $node->arity= 'assignment';

      $this->token= $this->expect(';');
      return $node;
    });

    $this->stmt('if', function($node) {
      $this->token= $this->expect('(');
      $condition= $this->expression(0);
      $this->token= $this->expect(')');

      if ('{'  === $this->token->symbol->id) {
        $this->token= $this->expect('{');
        $when= $this->statements();
        $this->token= $this->expect('}');
      } else {
        $when= [$this->statement()];
      }

      if ('else' === $this->token->symbol->id) {
        $this->token= $this->advance('else');
        if ('{'  === $this->token->symbol->id) {
          $this->token= $this->expect('{');
          $otherwise= $this->statements();
          $this->token= $this->expect('}');
        } else {
          $otherwise= [$this->statement()];
        }
      } else {
        $otherwise= null;
      }

      $node->value= [$condition, $when, $otherwise];
      $node->arity= 'if';
      return $node;
    });

    $this->stmt('foreach', function($node) {
      $this->token= $this->expect('(');
      $variable= $this->token;
      $this->scope->define($variable->value, $variable);

      $this->token= $this->advance();
      $this->token= $this->expect(':');
      $expression= $this->expression(0);
      $this->token= $this->expect(')');

      $this->token= $this->expect('{');
      $statements= $this->statements();
      $this->token= $this->expect('}');

      $node->value= [$variable, $expression, $statements];
      $node->arity= 'foreach';
      return $node;
    });

    $this->stmt('throw', function($node) {
      $node->value= $this->expression(0);
      $node->arity= 'throw';
      $this->token= $this->expect(';');
      return $node;      
    });

    $this->stmt('try', function($node) {
      $this->token= $this->expect('{');
      $statements= $this->statements();
      $this->token= $this->expect('}');

      if ('catch'  === $this->token->symbol->id) {
        $this->token= $this->advance();
        $this->token= $this->expect('(');
        $variable= $this->token;
        $this->scope->define($variable->value, $variable);
  
        $this->token= $this->advance();
        $this->token= $this->expect(')');

        $this->token= $this->expect('{');
        $catch= [$variable, $this->statements()];
        $this->token= $this->expect('}');
      } else {
        $catch= null;
      }

      $node->value= [$statements, $catch];
      $node->arity= 'try';
      return $node;      
    });

    $this->stmt('return', function($node) {
      if (';' === $this->token->symbol->id) {
        $expr= null;
      } else {
        $expr= $this->expression(0);
      }
      $this->token= $this->expect(';');

      $result= new Node($node->symbol);
      $result->value= $expr;
      $result->arity= 'return';
      return $result;
    });

    $this->stmt('def', function($node) {
      $t= $this->token;
      $this->scope->define($t->value, $t);
      $this->token= $this->advance();

      $this->scope= new Scope($this->scope); {
        $params= [];
        $this->token= $this->expect('(');
        while (')' !== $this->token->symbol->id) {
          $this->scope->define($this->token->value, $this->token);
          $params[]= $this->token;
          $this->token= $this->advance();
          if (',' === $this->token->symbol->id) {
            $this->token= $this->expect(',');
          }
        }
        $this->token= $this->expect(')');

        $this->token= $this->expect('{');
        $body= $this->statements();
        $this->token= $this->expect('}');

        $this->scope= $this->scope->parent;
      }

      $node->value= [$t, $params, $body];
      $node->arity= 'function';

      return $node;
    });

    $this->stmt('class', function($node) {
      $t= $this->token;

      $this->token= $this->advance();
      if (':' === $this->token->symbol->id) {
        $this->token= $this->expect(':');
        $parent= $this->token;
        $this->token= $this->advance();
      } else {
        $parent= null;
      }

      $this->token= $this->expect('{');
      $body= [];
      $annotation= null;
      while ('}' !== $this->token->symbol->id) {
        if ('def' === $this->token->symbol->id) {
          $member= new Node($this->token->symbol);
          $member->arity= 'method';
          $member->value= [$annotation, $this->statement()];
          $body[]= $member;
        } else if ('name' === $this->token->arity) {
          while (';' !== $this->token->symbol->id) {
            $member= new Node($this->token->symbol);
            $member->arity= 'property';
            $this->token= $this->advance();

            if ('=' === $this->token->symbol->id) {
              $this->token= $this->expect('=');
              $member->value= [$annotation, $this->expression(0)];
            } else {
              $member->value= [$annotation, null];
            }

            $body[]= $member;
            if (',' === $this->token->symbol->id) {
              $this->token= $this->expect(',');
            }
          }
          $this->token= $this->expect(';');
        } else if ('@' === $this->token->symbol->id) {
          $this->token= $this->expect('@');
          $annotation= $this->token;
          $this->token= $this->advance();
        } else {
          $this->expect('def');
        }
      }
      $this->token= $this->expect('}');

      $node->value= [$t, $parent, $body];
      $node->arity= 'class';

      return $node;
    });

    $this->stmt('print', function($node) {
      $node->value= $this->expression(0);
      $node->arity= 'print';
      $this->token= $this->expect(';');
      return $node;
    });
  }

  private function arguments() {
    $arguments= [];
    while (')' !== $this->token->symbol->id) {
      $arguments[]= $this->expression(0, false);    // Undefined arguments are OK
      if (',' === $this->token->symbol->id) {
        $this->token= $this->expect(',');
      }
    }
    return $arguments;
  }

  private function expression($rbp, $nud= true) {
    $t= $this->token;
    $this->token= $this->advance();
    if ($nud || $t->symbol->nud) {
      $left= $t->nud();
    } else {
      $left= $t;
    }

    while ($rbp < $this->token->symbol->lbp) {
      $t= $this->token;
      $this->token= $this->advance();
      $left= $t->led($left);
    }

    return $left;
  }

  private function top() {
    while ('(end)' !== $this->token->symbol->id) {
      if (null === ($statement= $this->statement())) break;
      yield $statement;
    }
  }

  private function statements() {
    $statements= [];
    while ('}' !== $this->token->symbol->id) {
      if (null === ($statement= $this->statement())) break;
      $statements[]= $statement;
    }
    return $statements;
  }

  private function statement() {
    if ($this->token->symbol->std) {
      $t= $this->token;
      $this->token= $this->advance();
      return $t->std();
    }

    $expr= $this->expression(0);
    $this->token= $this->expect(';');
    return $expr;
  }

  // {{ setup
  private function symbol($id, $lbp= 0) {
    if (isset($this->symbols[$id])) {
      $symbol= $this->symbols[$id];
      if ($lbp > $symbol->lbp) {
        $symbol->lbp= $lbp;
      }
    } else {
      $symbol= new Symbol();
      $symbol->id= $id;
      $symbol->lbp= $lbp;
      $this->symbols[$id]= $symbol;
    }
    return $symbol;
  }

  private function constant($id, $value) {
    $const= $this->symbol($id);
    $const->nud= function($node) use($value) {
      $node->arity= 'literal';
      $node->value= $value;
      return $node;
    };
    return $const;
  }

  private function stmt($id, $func) {
    $stmt= $this->symbol($id);
    $stmt->std= $func;
    return $stmt;
  }

  private function assignment($id) {
    $infix= $this->symbol($id, 10);
    $infix->led= function($node, $left) use($id) {
      $result= new Node($this->symbol($id));
      $result->arity= 'assignment';
      $result->value= [$left, $this->expression(9)];
      return $result;
    };
    return $infix;
  }

  private function infix($id, $bp, $led= null) {
    $infix= $this->symbol($id, $bp);
    $infix->led= $led ?: function($node, $left) use($bp) {
      $node->value= [$left, $this->expression($bp)];
      $node->arity= 'binary';
      return $node;
    };
    return $infix;
  }

  private function infixr($id, $bp, $led= null) {
    $infix= $this->symbol($id, $bp);
    $infix->led= $led ?: function($node, $left) use($bp) {
      $node->value= [$left, $this->expression($bp - 1)];
      $node->arity= 'binary';
      return $node;
    };
    return $infix;
  }

  private function prefix($id, $nud= null) {
    $prefix= $this->symbol($id);
    $prefix->nud= $nud ?: function($node) {
      $node->value= $this->expression(70);
      $node->arity= 'unary';
      return $node;
    };
    return $prefix;
  }

  private function suffix($id, $bp, $led= null) {
    $suffix= $this->symbol($id, $bp);
    $suffix->led= $led ?: function($node, $left) use($bp) {
      $left->value= $node;
      $left->arity= 'unary';
      return $left;
    };
    return $suffix;
  }
  // }}}

  private function expect($id) {
    if ($id !== $this->token->symbol->id) {
      throw new Error('Expected `'.$id.'`, have `'.$this->token->symbol->id.'`');
    }

    return $this->advance();
  }

  private function advance() {
    if ($this->tokens->valid()) {
      $value= $this->tokens->current();
      $type= $this->tokens->key();
      $this->tokens->next();
      if ('name' === $type) {
        $node= $this->scope->find($value) ?: new Node($this->symbol($value) ?: clone $this->symbol('(name)'));
      } else if ('operator' === $type) {
        $node= new Node($this->symbol($value));
      } else if ('string' === $type || 'integer' === $type || 'decimal' === $type) {
        $node= new Node(clone $this->symbol('(literal)'));
        $type= 'literal';
      } else {
        $node->error('Unexpected token');
      }
      $node->arity= $type;
      $node->value= $value;
      // \util\cmd\Console::writeLine('-> ', $node);
      return $node;
    } else {
      return new Node($this->symbol('(end)'));
    }
  }

  public function execute() {
    $this->token= $this->advance();
    return $this->top();
  }
}