# ff-cli: CLI Capability Layer for ff

`ff-cli` 为 `ff` 框架提供 CLI 环境下的参数解析、输出、路由和测试报告能力，通过 `ff` 的 ext 系统按需启用。

## 安装

```bash
composer require veasin/ff-cli
```

依赖 PHP 8.5+ 与 `veasin/ff >=0.4.2`。

## 启用 CLI 能力

```php
<?php
include __DIR__ . '/../vendor/autoload.php';

use function ff\enable;

enable('cli');                  // CLI 输入 / 输出 / 路由
enable('cli-test');             // 测试失败时输出结果并 exit(1)
enable('cli', 'cli-test');      // 同时启用
```

`cli` 会写入 `^#ext.from.input`、`^#ext.from.params`、`^#out.type => 'raw'`、`^#out.emit => 'cli'`，之后 `from()`、`route()`、`output()` 才走 CLI 分支。`^#ext.from.input` 的 `uri` 为去掉脚本名的命令串，`params` 为 `args()` 解析结果。

`cli-test` 只设置 `^#out.emit => 'cli'` 并注册测试收尾 hook，不启用 CLI 输入和路由。测试中需要匹配 `cli:` 路由时使用 `enable('cli', 'cli-test')`。

## 函数

### `ff\cli\args()` — 命令行参数解析

解析字符串或已分割的数组，返回「选项键值 + 位置参数数字索引」的数组。

```php
use function ff\cli\args;

$args = args('-v --file=test.php input.txt');
// ['v' => true, 'file' => 'test.php', 0 => 'input.txt']

$args = args(['-abc', '--verbose', '--name=John', 'data.txt']);
// ['a' => true, 'b' => true, 'c' => true, 'verbose' => true, 'name' => 'John', 0 => 'data.txt']
```

规则：

- 短选项：`-a` 为布尔；下一项不以 `-` 开头（或以 `-` 开头但是数字）时作为值，如 `-f file.txt` → `['f' => 'file.txt']`
- 组合短选项：`-abc` 前几位为布尔，末位可带值，如 `-abc value` → `['a' => true, 'b' => true, 'c' => 'value']`
- 单横线后除 `=`、`_` 外全为字母时按组合短选项解析；含数字或其它字符（如 `-100`）则整体作为位置参数。长选项必须用 `--`，如 `--file=x.txt`
- 长选项带值必须用 `=`：`--file=x.txt`；`--file x.txt` 会把 `x.txt` 当作位置参数
- 不支持 `-f=value` / `-abc=value`：请使用空格形式 `-f value` / `-abc value`。等号形式会按组合短选项逐字符拆键，例如 `-f=value` → `f`、`=`、`v`、`a`、`l`、`u`、`e`（若后面跟值，末位 `e` 会取该值）
- 值中的 `=` 保留，如 `--data=a=b=c` → `['data' => 'a=b=c']`
- `--` 之后全部作为位置参数，不再解析为选项；单独的 `-`、`---` 也按位置参数保留
- 重复选项后者覆盖前者
- 选项值和无标记参数均为字符串（数字也保持字符串），布尔选项为 `true`
- 字符串输入支持单/双引号和转义；反斜杠只在 `"`、`'`、空格前作为转义，Windows 路径原样保留
- 数组输入不按空格分词、不处理转义；引号按字面保留，仅当整个值被成对引号包裹时去掉首尾引号；首项含 `.php` 时视为脚本名丢弃

### `ff\cli\output\cli()` — CLI 输出

打印字符串内容，并用退出码表达结果状态。非字符串内容不打印（CLI 不会自动 JSON 序列化）。

```php
use function ff\cli\output\cli;

cli('hello', ['code' => 200]);  // 输出 hello，exit(0)
cli(null, ['code' => 404]);     // 空输出，exit(1)
```

- `code < 400` → `0`
- `400 ≤ code < 500` → `1`
- `code ≥ 500` → `2`

通过 `^#ext.out.emit.cli` 注册；`^#out.emit` 为 `cli` 时由 `output()` 调用。

### `ff\cli\route\cli()` — CLI 路由匹配

`^#ext.route.cli` 的 match handler，通常由 `route()` 调度，不直接调用。

```php
route('cli:user', fn($next) => true);
route('cli:*', fn($next) => true);       // 未知命令
```

规则：

- 词序列取 `args()` 结果中的数字索引项
- 路由键为 `cli:` + 空格分隔的模式；模式词数必须与词序列完全相等
- 固定词必须逐位相等
- `*` 匹配恰好一个词，但该词不能是本层已声明的固定命令；顶部 `cli:*` 即「未知命令」。通配路由本身不参与固定命令收集，因此字面 `*` 可作为未知词匹配 `cli:*`
- 多条路由命中时按路由表顺序全部收集，由 `middleware()` 链的 `$next()` 决定是否继续
- 返回 `['handlers' => callable[], 'keys' => string[], 'params' => array]` 或 `null`

`cli()` 只负责匹配和返回结果，不写 `#in.params` / `#route.result`；写入由 `route()` 完成。

### `ff\cli\route\script()` — 命令行脚本编排

类同 `rest()` 对 HTTP，`script()` 对命令行脚本：把命令配置树展开成 `route()` 可接受的 `cli:` 路由键数组。

简单脚本：

```php
use function ff\{container, output, route};
use function ff\cli\route\script;

route(script([
    '文件处理工具',
    ['file' => '目标文件', 'force' => '强制'],
    false,                                      // 无参数 / --help → help
    function($next){
        $params = container('#in.params') ?? [];
        if(!isset($params['file'])) return $next(); // 缺参 → help
        output('处理：' . $params['file']);
    },
]));
// php a.php --file=x.txt → 处理：x.txt
// php a.php              → help
// php a.php --help       → help
```

多子命令、嵌套、中间件与未知命令：

```php
route(script([
    '项目管理',
    ['v|verbose' => '详细日志'],
    fn($next) => $next(),                        // 中间件，作用于所有路由
    false,                                       // 无子命令 → help
    true,                                        // 未知子命令 → unknown + help
    'list' => [
        '列出文件',
        ['a|all' => '含隐藏'],
        fn($next) => output('列出文件'),
    ],
    'remote' => [
        '远程管理',
        false, true,
        'add' => fn($next) => output('添加远程'),
    ],
]));
// php a.php list          → 列出文件
// php a.php list --help   → list 的 help
// php a.php remote        → remote 的 help
// php a.php remote add    → 添加远程
// php a.php foo           → 未知命令：foo + 顶层 help
```

配置规则：

- 无键字符串：help 描述，多条换行合并
- 无键关联数组：选项声明，多条合并；**只用于 help 显示，不参与解析或校验**
- 无键 callable（或 callable 列表）：进入本层栈，父层栈向下继承为子路由的前置中间件
- `false`：本层 help 拦截。顶层完全无参数或 `--help` 时直接输出 help，不执行本层闭包；子层命中 `false` 路由即输出 help。有参数时按链执行，业务缺参仍可调用 `$next()` 落到 help
- `true`：本层遇到未知子命令时输出 unknown + help
- 字符串键：子命令，值可为闭包或同结构的子配置
- `''` / `'?'`：显式覆盖简写。`''` 用 `false` / 闭包 / 闭包数组，`'?'` 用 `true` / 闭包 / 闭包数组；`[]` 分别等价于 `false` / `true`。类型错配（如 `'' => true`、`'?' => false`）会抛 `InvalidArgumentException`
- 闭包数组的最后一项是业务终点，前面的项作为中间件；业务终点会被 help 检查包裹
- 叶子层（无子命令、无 `''`/`'?'`，但栈非空）自动生成 `cli:<path>`，最后一个闭包为业务 handler
- 每层按 `''`、`'?'`、固定子命令（配置顺序）展开
- `--help` 命中叶子路由、显式 `''`/`'?'` 业务闭包或 `false` 本层时，输出该层及下级 help 并阻断业务；不支持 `-h`
- `false` 在 `--help` 或顶层完全无参数时拦截；`true` 的未知命令 handler 总是先输出 unknown，`--help` 不改变该顺序
- help 说明列全局对齐（颜色标记不计宽）；顶层未声明 `help` 选项时，末尾附「使用 --help 查看帮助」
- 文案走 i18n：`#ff.cli.script.help`、`#ff.cli.script.unknown`（未注册时回落默认中文）；输出前统一经过 `color()`

`f|file` 这类写法只是 help 中的别名展示。实际参数键由 `args()` 决定：`-f` → `f`，`--file` → `file`，两者不会自动映射或合并。

### `ff\cli\color()` — 颜色标记转 ANSI

把 i18n 格式串中的 `[字母]` 标记转为 ANSI 转义码。

```php
use function ff\cli\color;

echo color('[r]Error[:]: [n]message[:]');
```

- 标准色：`[k]` 黑、`[r]` 红、`[g]` 绿、`[y]` 黄、`[b]` 蓝、`[m]` 品、`[c]` 青、`[w]` 白、`[n]` 灰（亮黑）
- 亮色：大写字母 `[K]` `[R]` `[G]` `[Y]` `[B]` `[M]` `[C]` `[W]`
- 前景 + 背景：`[r:w]`；仅背景：`[:g]`
- 重置：`[ :]` 前景、`[: ]` 背景、`[ : ]` 或 `[:]` 全部
- 非 CLI SAPI（`PHP_SAPI !== 'cli'`）剥离标记
- CLI SAPI 下不检测 TTY，输出重定向到文件也保留 ANSI；末尾统一追加重置

### `ff\cli\report()` — 测试结果报告

渲染 `test()` 的返回值：全部通过输出一行统计；有失败时逐条输出用例名、预期、实际，最后输出汇总。

```php
use function ff\cli\report;

$result = test();
report($result);
exit(empty($result['failed']) ? 0 : 1);   // 需要 CI 退出码时自行设置
```

- `actual` 为 `Throwable` 时取 `getMessage()`
- `expected` 为 `Throwable` 时取 `getMessage()`，为 callable 时显示 `assertion`，其余 JSON 编码
- 失败项兼容 `test()` 的关联数组结构，以及 `[label, actual, expected]` 列表
- 依赖 `color()` 与 bootstrap 注册的 `#ff.test.*` 文案
- `report()` 只渲染，不设置退出码；`test() |> report(...)` 失败时进程仍以 0 退出。需要退出码请在 `test()` 后自行 `exit(empty($result['failed']) ? 0 : 1)`。`enable('cli-test')` 的 after hook 也会调用 `test()`，但 `test()` 执行一次后清空用例；测试文件若已自行调用 `test()`，hook 只能拿到空结果，不能作为退出码依据

## 测试

```bash
php test/args.php
php test/route/cli.php
php test/route/script.php
php test/output/cli.php
```

测试文件通过 `test() |> \ff\cli\report(...)` 输出报告。`report()` 不改变退出码；CI 中建议保存结果后显式设置：

```php
$result = test();
report($result);
exit(empty($result['failed']) ? 0 : 1);
```

## 工作原理

`ff-cli` 通过 ext 系统注册能力：

1. **惰性注册**：`bootstrap.php` 注册 `^#ext.out.emit.cli`、`^#ext.route.cli`（`match` + `normalize`）、`^#route.methods.cli`、`^#ext.enable`（`cli` / `cli-test`）、`^i18n.#ff.test` 和 `^i18n.#ff.cli.script`
2. **显式启用**：`enable('cli')` 写入 `^#ext.from.input`、`^#ext.from.params`、`^#out.type => 'raw'`、`^#out.emit => 'cli'`
3. **测试收尾**：`enable('cli-test')` 写入 `^#out.emit => 'cli'` 并注册 after hook；失败时输出 JSON 并 `exit(1)`，全部通过时静默。注意 `test()` 执行一次后清空用例，该 hook 仅在测试文件未自行调用 `test()` 时有效
4. **不改变 Web 行为**：正常 Web 请求（GET/POST 等）不会命中任何 ff-cli 注册的 handler

## 与 ff 核心的关系

- `ff` 核心不包含任何 CLI 专用函数
- `ff-cli` 依赖 `veasin/ff >=0.4.2` 与 PHP 8.5+
- 核心的 `from()`、`route()`、`output()` 已改为 ext 驱动，CLI 行为由 ff-cli 通过 ext 提供
- `script()` 基于 `route()` 的 `cli:` 协议与 `^#ext.route.cli` 的 `normalize` 能力
- `report()` 使用核心 `test()` 返回值与 i18n 文案，不修改测试执行逻辑
