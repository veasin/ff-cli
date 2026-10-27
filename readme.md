# ff-cli: CLI Capability Layer for ff

`ff-cli` 为 `ff` 框架提供 CLI 环境下的输入、输出和路由能力，通过 `ff` 的 ext 系统按需启用。

## 安装

```bash
composer require veasin/ff-cli
```

## 启用 CLI 能力

```php
<?php
include __DIR__ . '/../vendor/autoload.php';

use function ff\enable;

enable('cli');                  // 启用 CLI 输入/输出/路由
enable('cli', 'cli-test');      // 同时启用测试渲染
```

## 函数

### `ff\cli\args()`

解析命令行参数，支持短选项、长选项、无标记参数和引号处理。

```php
use function ff\cli\args;

// 字符串输入
$args = args('-v --file=test.php input.txt');
// ['v' => true, 'file' => 'test.php', 0 => 'input.txt']

// 数组输入
$args = args(['-abc', '--verbose', '--name=John', 'data.txt']);
// ['a' => true, 'b' => true, 'c' => true, 'verbose' => true, 'name' => 'John', 0 => 'data.txt']
```

### `ff\cli\output\cli()`

CLI 输出函数，打印内容并以退出码表达结果状态。

- `0` = 成功 (< 400)
- `1` = 客户端错误 (4xx)
- `2` = 服务端错误 (5xx)

```php
use function ff\cli\output\cli;

cli("hello", ["code" => 200]);  // 输出 hello，exit(0)
cli(null, ["code" => 404]);     // 空输出，exit(1)
```

该函数通过 `^#ext.out.emit.cli` 注册，当 `#out.emit` 为 `cli` 时由 `output()` 调用。

### `ff\cli\route\cli()`

CLI 路由匹配，将 `argv` 作为 uri，`args()` 解析结果作为 params。

```php
// 在 ff-cli bootstrap 中自动注册为 ^#ext.route.cli
// 当 from('method', 'input') 返回 'cli' 时自动命中
route('cli:user', fn($next) => true);
```

### `ff\cli\color()`

颜色标记转 ANSI。标记格式：`{red}text{/red}`、`{bold}text{/bold}`。非 TTY 环境自动剥离标记。

```php
use function ff\cli\color;

echo color('{red}Error{/red}: {bold}message{/bold}');
```

## 工作原理

`ff-cli` 通过 ext 系统注册能力：

1. **惰性注册**：`bootstrap.php` 只注册 `^#ext.out.emit.cli`、`^#ext.route.cli`、`^#ext.enable.cli`、`^#ext.from.input/params`
2. **显式启用**：`enable('cli')` 时才写入全局配置（`^#ext.from.input/params`、`^#out.emit`）
3. **不改变 Web 行为**：Web 入口不会命中任何 ff-cli 注册的 handler

## 与 ff 核心的关系

- `ff` 核心不包含任何 CLI 专用函数
- `ff-cli` 依赖 `veasin/ff >=0.4.0`
- 核心的 `from()`、`route()`、`output()` 已改为 ext 驱动，CLI 行为由 ff-cli 通过 ext 提供
