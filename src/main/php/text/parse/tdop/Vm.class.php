<?php namespace text\parse\tdop;

use text\parse\tdop\Parse;
use text\parse\tdop\Tokens;
use text\parse\tdop\Node;
use text\StringTokenizer;
use text\StreamTokenizer;
use io\streams\FileInputStream;
use util\cmd\Console;
use util\profiling\Timer;
use lang\Throwable;

/** @see http://javascript.crockford.com/tdop/tdop.html */
class Vm {

  public static function main($args) {
    if (is_file($args[0])) {
      $input= new StreamTokenizer(new FileInputStream($args[0]));
      $source= 'file '.$args[0];
    } else {
      $input= new StringTokenizer($args[0]);
      $source= 'command line argument';
    }

    $show= false;
    $classpath= ['src/main/top'];
    for ($i= 1; $i < sizeof($args); $i++) {
      if ('-s' === $args[$i]) {
        $show= true;
      } else if ('-cp' === $args[$i]) {
        $classpath[]= $args[++$i];
      } else if ('-' !== $args[$i]{0} || '--' === $args[$i]) {
        break;
      }
    }
    $argv= array_slice($args, $i);

    $t= new Timer();

    $parse= new Parse(new Tokens($input));
    $t->start();
    try {
      $script= iterator_to_array($parse->execute());
      $t->stop();
    } catch (Throwable $e) {
      $t->stop();
      Console::writeLinef('>> %.3f seconds to compile %s', $t->elapsedTime(), $source);
      Console::writeLine("\e[33m", $e, "\e[0m");
      return 1;
    }

    Console::writeLinef('>> %.3f seconds to compile %s', $t->elapsedTime(), $source);
    $show && Console::writeLine("\e[32m", $script, "\e[0m");

    $execution= new Execution($classpath, $argv);
    $t->start();
    try {
      Console::write("\e[36m");
      $result= $execution->execute($script);
      $t->stop();
      Console::write("\e[0m");
    } catch (Throwable $e) {
      $t->stop();
      Console::write("\e[0m");
      Console::writeLinef('>> %.3f seconds to execute script, runtime error', $t->elapsedTime());
      Console::writeLine("\e[31m", $e, "\e[0m");
      return 2;
    }

    Console::writeLinef(">> %.3f seconds to execute script, result= %s", $t->elapsedTime(), \xp::stringOf($result));
  }
}