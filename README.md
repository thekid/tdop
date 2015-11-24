TDOP Experiment
===============

Inspired by Douglas Crockford's [talk at the goto; conference]() about [top down operator precence](http://portal.acm.org/citation.cfm?id=512931), a PHP version of [the code explained here](http://javascript.crockford.com/tdop/tdop.html).

```sh
$ xp text.parse.tdop.Vm examples/greet.top
>> 0.001 seconds to compile file examples/greet.top
Instantiated Person w/ 0 args
Hello Person<@Test>
===================
>> 0.001 seconds to execute script, result= 0
```

```sh
$ xp text.parse.tdop.Vm examples/run-test.top -e PersonTest
>> 0.001 seconds to compile file examples/run-test.top
Running tests in PersonTest:
✓ OK can_create
✓ OK create_with_handle
✓ OK create_with_handle_and_name
✓ OK handle_accessor
✓ OK string_representation
>> 0.004 seconds to execute script, result= 0
```

