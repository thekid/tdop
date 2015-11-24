<?php namespace text\parse\tdop;

use text\parse\tdop\Tokens;
use text\StringTokenizer;
use text\StreamTokenizer;
use io\streams\FileInputStream;
use util\cmd\Console;

/** @see http://javascript.crockford.com/tdop/tdop.html */
class Tokenize {

  public static function main($args) {
    if (is_file($args[0])) {
      $input= new StreamTokenizer(new FileInputStream($args[0]));
    } else {
      $input= new StringTokenizer($args[0]);
    }
    foreach (new Tokens($input) as $type => $value) {
      Console::writeLinef('%s(%s)', $type, $value);
    }
  }
}