# 迷你文档插件

`迷你文档` 插件支持文档在线浏览，包括：doc/docx/xls/xlsx/ppt/pptx/pdf类型

`迷你文档` 工作原理是把指定[迷你文档]服务器，通过后台定时任务，把指定的文件类型转换为PDF，并提取PDF的第一页作为文档封面

 浏览器使用[pdf.js]加载pdf文件，这样的上述多种文档即可在线浏览，[pdf.js]不支持IE8及其以下版本IE浏览器


# 开启插件

管理员登录网页版，进入 管理后台，选中 插件管理，启用 迷你文档 插件

# 获得文件列表
* `event` Event


[pdf.js]:https://github.com/mozilla/pdf.js