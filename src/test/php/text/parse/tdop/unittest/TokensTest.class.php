<?php namespace text\parse\tdop\unittest;

use text\parse\tdop\Tokens;
use text\StringTokenizer;
use lang\FormatException;

class TokensTest extends \unittest\TestCase {

  /**
   * Parse input and return tokens as array
   *
   * @param  string $input
   * @return string[][]
   */
  private function parse($input) {
    $tokens= [];
    foreach (new Tokens(new StringTokenizer($input)) as $type => $value) {
      $tokens[]= [$type, $value];
    }
    return $tokens;
  }

  #[@test]
  public function empty_input() {
    $this->assertEquals([], $this->parse(''));
  }

  #[@test, @values([
  #  '""', "''",
  #  '" "', "' '",
  #  '"Test"', "'Test'",
  #  '" Test"', "' Test'",
  #  '"Test "', "'Test '",
  #  '"Test the west"', "'Test the west'"
  #])]
  public function strings($input) {
    $this->assertEquals([['string', substr($input, 1, -1)]], $this->parse($input));
  }

  #[@test, @expect(FormatException::class), @values(['"', "'", '"Test', "'Test"])]
  public function unclosed_string($input) {
    $this->parse($input);
  }

  #[@test, @values(['0', '9223372036854775808'])]
  public function integers($input) {
    $this->assertEquals([['integer', (int)$input]], $this->parse($input));
  }

  #[@test, @values(['0.0', '1.5'])]
  public function decimals($input) {
    $this->assertEquals([['decimal', (double)$input]], $this->parse($input));
  }

  #[@test]
  public function formula() {
    $this->assertEquals(
      [['name', 'a'], ['operator', '='], ['name', 'b'], ['operator', '+'], ['name', 'c']],
      $this->parse('a = b + c')
    );
  }
}