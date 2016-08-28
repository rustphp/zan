# Based On Zan PHP Framework
基于 有赞 的PHP框架 移除了一些不需要的代码 增加了swoole新版的支持

1.增加 swoole 1.8.8 支持

2.增加 SqlMap binds,支持 "?, :name" 形式的SQL写法

3.增加 表单数组、文件上传 支持

4.URL 中 移除 有赞 七牛CDN 业务逻辑代码(20160828)

5.route配置中 增加base_path,以便支持目录前导访问,如/directory/route/controller/action(20160828))