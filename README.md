TDOP Experiment
===============

Inspired by Douglas Crockford's [talk at the goto; conference](https://www.youtube.com/watch?v=Nlqv6NtBXcA) about [top down operator precence](http://portal.acm.org/citation.cfm?id=512931), a PHP version of [the code explained here](http://javascript.crockford.com/tdop/tdop.html).

Code
----
The following is our simplemost unittest runner written in **TOP**, the programming language I created with a TDOP parser.

```groovy
var test = new Type(args[0]);

print 'Running tests in ' ~ test.name ~ ':';

foreach (method : test.methods()) {
  if (method.annotationPresent('test')) {
    try {
      method.invoke(test.newInstance());
      print '✓ OK ' ~ method.name;
    } catch (e) {
      print '✗ FAIL ' ~ method.name ~ ': ' ~ e.toString();
    }
  }
}

return 0;
```

The class under test is the following:

```groovy
class Person {
  handle, name;

  def this(handle, name) {
    this.handle = handle ?? null;
    this.name = name ?? null;
  }

  def toString() {
    return 'Person<@' ~ this.handle ~ '>';
  }
}
```

The unittest is written as follows:

```groovy
class PersonTest : Test {
  handle = 'Test', name = 'Unit Tester';

  @test def name_accessor() {
    this.assertEquals(this.name, new Person(this.handle, this.name).name);
  }

  @test def string_representation() {
    this.assertEquals('Person<@Test>', new Person(this.handle).toString());
  }
}
```

Running it can be done by invoking the following:

```sh
$ xp text.parse.tdop.Vm examples/run-test.top PersonTest
>> 0.001 seconds to compile file examples/run-test.top
Running tests in PersonTest:
✓ OK can_create
✓ OK create_with_handle
✓ OK create_with_handle_and_name
✓ OK handle_accessor
✓ OK name_accessor
✓ OK string_representation
>> 0.004 seconds to execute script, result= 0
```

