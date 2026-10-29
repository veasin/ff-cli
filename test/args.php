<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{test};
use function ff\cli\args;

//echo "========== args 函数测试开始 ==========\n\n";
// ==================== 1. 基础边界测试 ====================
test("空字符串输入", function(){ return args(""); }, []);
test("空数组输入", function(){ return args([]); }, []);
test("空白字符串", function(){ return args("   "); }, []);
test("单独的短横线", function(){ return args("-"); }, ['-']);
test("双减号分隔符", function(){ return args("--"); }, []);
test("三个横线", function(){ return args("---"); }, ['---']);
test("四个横线", function(){ return args("----"); }, ['----']);
test("无效长选项 --=", function(){ return args("--="); }, ['--=']);
test("无效短选项 -=", function(){ return args("-="); }, ['-=']);
test("无效长选项带值 --=value", function(){ return args("--=value"); }, ['--=value']);
// ==================== 2. 短选项测试 ====================
test("单个短选项 -a", function(){ return args("-a"); }, ['a' => true]);
test("单个短选项 -v", function(){ return args("-v"); }, ['v' => true]);
test("多个独立短选项", function(){ return args("-a -b -c"); }, ['a' => true, 'b' => true, 'c' => true]);
test("组合短选项 -abc", function(){ return args("-abc"); }, ['a' => true, 'b' => true, 'c' => true]);
test("组合短选项加选项", function(){ return args("-abc -d"); }, ['a' => true, 'b' => true, 'c' => true, 'd' => true]);
test("短选项带值 -f file.txt", function(){ return args("-f file.txt"); }, ['f' => 'file.txt']);
test("短选项带数字值", function(){ return args("-n 100"); }, ['n' => '100']);
test("短选项带负数值", function(){ return args("-n -100"); }, ['n' => '-100']);
test("组合选项最后一个带值", function(){ return args("-abc value"); }, ['a' => true, 'b' => true, 'c' => 'value']);
test("组合选项带文件", function(){ return args("-abcf file.txt"); }, ['a' => true, 'b' => true, 'c' => true, 'f' => 'file.txt']);
// ==================== 3. 长选项测试 ====================
test("布尔长选项 --verbose", function(){ return args("--verbose"); }, ['verbose' => true]);
test("布尔长选项 --debug", function(){ return args("--debug"); }, ['debug' => true]);
test("长选项带等号值", function(){ return args("--file=test.php"); }, ['file' => 'test.php']);
test("长选项带数字值", function(){ return args("--count=123"); }, ['count' => '123']);
test("长选项带小数", function(){ return args("--pi=3.14159"); }, ['pi' => '3.14159']);
test("长选项带负数值", function(){ return args("--number=-100"); }, ['number' => '-100']);
test("多个等号的情况", function(){ return args("--data=a=b=c"); }, ['data' => 'a=b=c']);
test("多个等号哈希值", function(){ return args("--hash=sha256=test"); }, ['hash' => 'sha256=test']);
test("空值处理 --empty=", function(){ return args("--empty="); }, ['empty' => '']);
test("混合空值处理", function(){ return args("--flag --empty="); }, ['flag' => true, 'empty' => '']);
// ==================== 4. 引号处理测试 ====================
test("双引号值", function(){ return args('--name="John Doe"'); }, ['name' => 'John Doe']);
test("空双引号", function(){ return args('--name=""'); }, ['name' => '']);
test("空格双引号", function(){ return args('--name=" "'); }, ['name' => ' ']);
test("Windows路径双引号", function(){ return args('--path="C:\\Program Files"'); }, ['path' => 'C:\\Program Files']);
test("单引号值", function(){ return args("--name='John Doe'"); }, ['name' => 'John Doe']);
test("空单引号", function(){ return args("--name=''"); }, ['name' => '']);
test("Windows路径单引号", function(){ return args("--path='C:\\Program Files'"); }, ['path' => 'C:\\Program Files']);
test("混合引号", function(){
	return args('--mixed="test" -a --single=\'value\'');
}, ['mixed' => 'test', 'a' => true, 'single' => 'value']);
test("引号内的引号", function(){
	return args('--message="Hello \\"World\\""');
}, ['message' => 'Hello "World"']);
test("引号参数", function(){ return args('"quoted param"'); }, ['quoted param']);
test("Windows路径参数", function(){ return args('"C:\\Program Files\\app.exe"'); }, ['C:\\Program Files\\app.exe']);
// ==================== 5. 混合选项测试 ====================
test("长短选项混合1", function(){ return args("-v --file=test.php"); }, ['v' => true, 'file' => 'test.php']);
test("长短选项混合2", function(){
	return args("-abc --verbose --count=10");
}, ['a' => true, 'b' => true, 'c' => true, 'verbose' => true, 'count' => '10']);
test("长短选项混合3", function(){
	return args("-v -f file.txt --output=out.log");
}, ['v' => true, 'f' => 'file.txt', 'output' => 'out.log']);
test("混合无标记参数1", function(){ return args("-a file.txt -b"); }, ['a' => 'file.txt', 'b' => true]);
test("混合无标记参数2", function(){ return args("--debug input.txt -v"); }, ['debug' => true, 'input.txt', 'v' => true]);
test("复杂混合场景1", function(){
	return args("-abc --config=app.json --debug input1.txt input2.txt");
}, ['a' => true, 'b' => true, 'c' => true, 'config' => 'app.json', 'debug' => true, 'input1.txt', 'input2.txt']);
test("复杂混合场景2", function(){
	return args("-vvv --level=3 --name='test name' --path=\"/home/user\" file.txt");
}, ['v' => true, 'level' => '3', 'name' => 'test name', 'path' => '/home/user', 'file.txt']);
// ==================== 6. 无标记参数测试 ====================
test("单个文件参数", function(){ return args("file.txt"); }, ['file.txt']);
test("单个数字参数", function(){ return args("123"); }, ['123']);
test("负数作为参数", function(){ return args("-100"); }, ['-100']);
test("多个参数", function(){ return args("file1.txt file2.txt"); }, ['file1.txt', 'file2.txt']);
test("多个混合参数", function(){
	return args("input.txt output.log config.json");
}, ['input.txt', 'output.log', 'config.json']);
test("混合引号的参数", function(){ return args("file1.txt \"file with spaces.txt\" file3.txt"); }, ['file1.txt', 'file with spaces.txt', 'file3.txt']);
// ==================== 7. 分隔符 -- 测试 ====================
test("-- 后跟参数1", function(){ return args("-- -v"); }, ['-v']);
test("-- 后跟参数2", function(){ return args("-- --debug"); }, ['--debug']);
test("-- 后跟参数3", function(){ return args("-- -f file.txt"); }, ['-f', 'file.txt']);
test("选项后跟 --", function(){ return args("-v -- file.txt"); }, ['v' => true, 'file.txt']);
test("选项后跟 --2", function(){ return args("--debug -- input.txt -v"); }, ['debug' => true, 'input.txt', '-v']);
test("复杂分隔符场景", function(){
	return args("-abc -- --debug -f file.txt");
}, ['a' => true, 'b' => true, 'c' => true, 0 => '--debug', 1 => '-f', 2 => 'file.txt']);
// ==================== 8. 特殊值和通配符测试 ====================
test("通配符短选项", function(){ return args("-f file?.txt"); }, ['f' => 'file?.txt']);
test("通配符长选项", function(){ return args("--pattern=*.log"); }, ['pattern' => '*.log']);
test("URL参数", function(){
	return args("--url='https://example.com?q=test&lang=zh'");
}, ['url' => 'https://example.com?q=test&lang=zh']);
test("标签参数", function(){ return args("--tags='php,args,test'"); }, ['tags' => 'php,args,test']);
test("路径参数", function(){ return args("--path=/usr/local/bin/php"); }, ['path' => '/usr/local/bin/php']);
// ==================== 9. Windows 路径测试（数组模式）====================
test("Windows路径无空格", function(){ return args(['--path=C:\\Projects']); }, ['path' => 'C:\\Projects']);
test("Windows路径有空格", function(){ return args(['--path=C:\\Program Files']); }, ['path' => 'C:\\Program Files']);
test("Windows路径含引号", function(){
	return args(['--path=C:\\"Program\\" Files']);
}, ['path' => 'C:\\"Program\\" Files']);
test("Windows路径反斜杠", function(){ return args(['--path=C:\\Program Files']); }, ['path' => 'C:\\Program Files']);
// ==================== 10. 重复选项测试 ====================
test("短选项重复", function(){ return args("-v -v"); }, ['v' => true]);
test("短选项多重复", function(){ return args("-a -b -a"); }, ['a' => true, 'b' => true]);
test("长选项重复", function(){ return args("--debug --debug"); }, ['debug' => true]);
test("长选项值覆盖", function(){ return args("--name=first --name=second"); }, ['name' => 'second']);
// ==================== 11. 数组输入测试 ====================
test("数组基本输入", function(){ return args(['-a', '-b', 'file.txt']); }, ['a' => true, 'b' => 'file.txt']);
test("数组长选项", function(){ return args(['--verbose', '--file=test.php']); }, ['verbose' => true, 'file' => 'test.php']);
test("数组混合选项", function(){
	return args(['-abc', '--name=test', 'arg1']);
}, ['a' => true, 'b' => true, 'c' => true, 'name' => 'test', 'arg1']);
test("数组包含分隔符", function(){
	return args(['-v', '--', '-f', 'file.txt']);
}, ['v' => true, '-f', 'file.txt']);
// ==================== 12. 压力测试 ====================
test("大规模参数处理", function(){
	$manyArgs = [];
	for($i = 1; $i <= 100; $i++){
		$manyArgs[] = "-$i";
		$manyArgs[] = "--long$i=value$i";
	}
	$manyArgs[] = "last_argument";
	//$startTime = microtime(true);
	$result = args($manyArgs);
	//$endTime = microtime(true);
	//echo "处理100个短选项+100个长选项+1个参数: " . count($result) . " 个结果\n";
	//echo "耗时: " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
	return $result;
}, function($result){
	return count($result) === 201; // 100个短选项 + 100个长选项 + 1个参数
});

test() |> \ff\cli\report(...);
