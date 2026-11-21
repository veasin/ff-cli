<?php
include __DIR__ . '/../vendor/autoload.php';

use function ff\{container, enable, from, route, test};
use function ff\cli\report;

// 清掉请求级预置，让 ^#in.input / ^#in.params loader 生效
container('#in.input', null);
container('#in.params', null);

$_SERVER['argv'] = ['a.php', 'hello', '--foo=bar'];
enable('cli');

test('enable(cli): from(method, input) 为 cli', from('method', 'input'), 'cli');
test('enable(cli): from(protocol, input) 为 null', from('protocol', 'input'), null);
test('enable(cli): from(uri, input) 为去脚本名命令串', from('uri', 'input'), 'hello --foo=bar');
test('enable(cli): from(null, params) 来自 args()', from(null, 'params'), [0 => 'hello', 'foo' => 'bar']);
test('enable(cli): from(foo, params) 读选项值', from('foo', 'params'), 'bar');

$keys = route('cli:hello', fn($next) => 'ok');
test('enable(cli): route(cli:) 命中', $keys, ['cli:hello']);

test() |> report(...);
