<?php
// bootstrap.php — ff-cli 自注册：只做惰性注册，不改变全局行为
// 所有会改变全局行为的写入（^#ext.from.input/params、^#out.emit）都由 enable() handler 执行
use function ff\{container, hook};

container([
    '^#ext.out.emit.cli' => \ff\cli\output\cli(...),
    '^#ext.route.cli' => [
        'match' => \ff\cli\route\cli(...),
        'normalize' => function(string $parentPath, string $childUnit): string {
            if($childUnit === '') return rtrim($parentPath, ' ');
            return rtrim($parentPath, ' ') . ' ' . $childUnit;
        }
    ],
]);

container('^#route.methods.cli', 'cli');

container('^#ext.enable', [
    'cli' => fn() => container([
        '^#ext.from.input'  => fn() => [
            'method' => 'cli',
            'protocol' => null,
            'uri' => implode(' ', $_SERVER['argv']),
        ],
        '^#ext.from.params' => fn() => \ff\cli\args(array_slice($_SERVER['argv'], 1)) ?? [],
        '^#out.emit'        => 'cli',
    ]),
    'cli-test' => function(){
        container('^#out.emit', 'cli');
        hook('after', function(){
            // 测试渲染：直接输出 test() 返回值，失败时 exit(1)
            $r = \ff\test();
            if($r['failed'] ?? []){
                echo json_encode($r, JSON_UNESCAPED_UNICODE), PHP_EOL;
                exit(1);
            }
        });
    },
]);

container('^i18n.#ff.test', [
    'total' => 'Total: {total}',
    'passed' => 'Passed: {passed}',
    'failed' => 'Failed: {count}',
]);
