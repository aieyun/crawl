<!DOCTYPE html>
<html>
<head>
<title>网站镜像</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Cache-Control" content="max-age=3600, public">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimal-ui, minimum-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
<meta name="author" content="eray">
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-title" content="网站镜像">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="keywords" content="网站镜像,网站内容下载" />
<meta name="description" content="网站镜像可以轻松获取网站内容，并支持在线预览和镜像源码下载，网站镜像仅供学习测试，请勿用于非法用途" />
</head>
<body>
<?php
// 自定义User-Agent
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0');
define('SAVE_DIR', __DIR__ . '/sites');

// 错误处理函数
function dieWithError($message) {
    die("<h2 style='color:red;'>{$message}</h2>
</body>
</html>");
}

// 封装cURL请求函数
function curlRequest($url, $refer = '', $proxyurl = '') {
    $ch = curl_init();
    
    // 如果传入了proxyurl，则拼接代理URL
    if (!empty($proxyurl)) {
        $url = $proxyurl . $url;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, USER_AGENT);
    
    if (!empty($refer)) {
        curl_setopt($ch, CURLOPT_REFERER, $refer);
    }
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    return $response;
}

// 判断链接是否是文件类型
function isFileLink($url) {
    $path = parse_url($url, PHP_URL_PATH);
    return preg_match('/\.\w+$/', $path);
}

// 保存文件
function saveFile($fileUrl, $baseUrl, $saveDir, $refer = '', $proxyurl = '') {
    // 处理协议相对URL（以//开头）
    if (strpos($fileUrl, '//') === 0) {
        $fullUrl = parse_url($baseUrl, PHP_URL_SCHEME) . ':' . $fileUrl;
    }
    // 处理完整URL（以http或https开头）
    elseif (strpos($fileUrl, 'http') === 0) {
        $fullUrl = $fileUrl;
    }
    // 处理相对路径
    else {
        $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($fileUrl, '/');
    }

    // 获取文件路径和扩展名（去掉查询参数）
    $parsedFileUrl = parse_url($fullUrl);
    $path = $parsedFileUrl['path'];
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $fileName = basename($path);

    // 创建保存文件的目录
    $extensionDir = $saveDir . '/' . $extension;
    if (!is_dir($extensionDir) && !mkdir($extensionDir, 0777, true)) {
        return false;
    }

    // 保存文件
    $filePath = $extensionDir . '/' . $fileName;

    // 使用封装的cURL函数获取文件内容
    $fileContent = curlRequest($fullUrl, $refer, $proxyurl);
    if ($fileContent === false) {
        return false;
    }

    file_put_contents($filePath, $fileContent);
    echo "<p>保存文件: {$fullUrl}</p>";

    return $filePath;
}

// 处理文件标签
function processFileTags($fileTags, $xpath, $baseUrl, $saveDir, $refer, $proxyurl, &$modifiedHtmlContent) {
    foreach ($fileTags as $tag => $attribute) {
        $elements = $xpath->query("//{$tag}[@{$attribute}]");
        foreach ($elements as $element) {
            $fileUrl = $element->getAttribute($attribute);
            echo "<p>检测到链接：{$fileUrl}</p>";
            if (isFileLink($fileUrl)) {
                $newFilePath = saveFile($fileUrl, $baseUrl, $saveDir, $refer, $proxyurl);
                if ($newFilePath) {
                    $extension = pathinfo($newFilePath, PATHINFO_EXTENSION);
                    $relativePath = './' . $extension . '/' . basename($newFilePath);
                    $modifiedHtmlContent = str_replace($fileUrl, $relativePath, $modifiedHtmlContent);
                    //echo "<p>替换链接：{$fileUrl} -> {$relativePath}</p>";
                }
            } else {
                echo "<p>丢弃链接：{$fileUrl}</p>";
            }
        }
    }
}

// 获取传入的URL参数
if (!isset($_GET['url']) || $_GET['url'] === "") {
    dieWithError('请传入合适的参数，比如?url=xxx&refer=xxx，refer非必要,仅在需要refer才能获取内容时使用。');
}

$url = urldecode($_GET['url']);

// 验证URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    dieWithError('请传入合适的参数，比如?url=xxx&refer=xxx，refer非必要,仅在需要refer才能获取内容时使用。');
}

// 检查refer参数
$refer = isset($_GET['refer']) ? urldecode($_GET['refer']) : '';
if ($refer !== '' && !filter_var($refer, FILTER_VALIDATE_URL)) {
    dieWithError('请传入合适的refer参数');
}

// 检查proxyurl参数
$proxyurl = isset($_GET['proxyurl']) ? urldecode($_GET['proxyurl']) : '';
if ($proxyurl !== '' && !filter_var($proxyurl, FILTER_VALIDATE_URL)) {
    dieWithError('请传入合适的proxyurl参数');
}

// 解析URL，获取host
$parsedUrl = parse_url($url);
$host = str_replace('.', '', $parsedUrl['host']);
$baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

// 创建保存文件的目录
$saveDir = SAVE_DIR . '/' . $host;
if (!is_dir($saveDir) && !mkdir($saveDir, 0777, true)) {
    dieWithError('无法创建保存目录');
}

// 保存HTML文件
$htmlFilePath = $saveDir . '/' . $host . '.html';

// 使用封装的cURL函数获取网页内容
$htmlContent = curlRequest($url, $refer, $proxyurl);
if ($htmlContent === false) {
    dieWithError('cURL请求失败');
}

// 解析HTML内容
$dom = new DOMDocument();
@$dom->loadHTML($htmlContent);
$xpath = new DOMXPath($dom);

// 定义文件标签数组
$fileTags = [
    'script' => 'src',
    'link' => 'href',
    'img' => 'src',
    'audio' => 'src',
    'video' => 'src',
    'source' => 'src',
];

//如果文件链接在其他标签
$fileTags2 = [
    'img' => 'data-original',
];

// 用于存储替换后的HTML内容
$modifiedHtmlContent = $htmlContent;

// 处理第一个文件标签数组
processFileTags($fileTags, $xpath, $baseUrl, $saveDir, $refer, $proxyurl, $modifiedHtmlContent);

// 处理第二个文件标签数组
processFileTags($fileTags2, $xpath, $baseUrl, $saveDir, $refer, $proxyurl, $modifiedHtmlContent);

// 保存修改后的HTML内容
file_put_contents($htmlFilePath, $modifiedHtmlContent);
echo "<p>保存文件: {$host}.html</p>";

// 压缩目录
function zipDirectory($sourceDir, $zipFilePath) {
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();
    return true;
}

echo "<h3>保存成功，点击预览：<a href='./sites/{$host}/{$host}.html' target='_blank'>{$host}.html</a></h3>";

// 压缩目录
$zipFilePath = SAVE_DIR . '/' . $host . '.zip';
if (zipDirectory($saveDir, $zipFilePath)) {
    echo "<h3>压缩成功，点击下载: <a href='./sites/{$host}.zip' target='_blank'>{$host}.zip</a></h3>";
} else {
    echo "<h3>压缩失败</h3>";
}

?>

</body>
</html>