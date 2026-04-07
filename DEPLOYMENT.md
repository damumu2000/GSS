# 服务器首次部署清单

这份清单用于把当前项目首次部署到服务器，并保证后续通过 Git 更新时不会误伤线上数据。

## 一、部署前原则

线上环境只允许做这些事：

- 拉取代码
- 安装生产依赖
- 执行安全迁移
- 清理并重建缓存

线上环境不要做这些事：

- `php artisan migrate:fresh`
- `php artisan db:seed`
- `php artisan test`
- 删除 `storage/app/web`
- 删除 `storage/logs`
- 删除 `.env`

## 二、服务器目录建议

建议项目目录类似：

```bash
/www/wwwroot/GSS
```

进入服务器后，先创建目录：

```bash
mkdir -p /www/wwwroot/GSS
cd /www/wwwroot/GSS
```

## 三、首次拉取代码

使用 SSH 方式拉取：

```bash
git clone git@github.com:damumu2000/GSS.git .
```

如果服务器还没有配置 GitHub SSH key，需要先给服务器生成 SSH key，并添加到 GitHub。

## 四、准备环境变量

首次部署时，用示例文件生成线上配置：

```bash
cp .env.example .env
```

然后编辑 `.env`，至少要确认这些值：

- `APP_NAME`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `FILESYSTEM_DISK`
- 邮件、短信、对象存储等第三方配置

如果 `APP_KEY` 为空，再执行：

```bash
php artisan key:generate
```

## 五、准备目录权限

确保 Web 服务用户对这些目录有写权限：

- `storage`
- `bootstrap/cache`

例如：

```bash
chown -R www:www /www/wwwroot/GSS
chmod -R 775 storage bootstrap/cache
```

如果你的服务器用户不是 `www`，请替换成实际运行用户。

## 六、安装 PHP 依赖

服务器上进入项目目录后执行：

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

## 七、首次上线执行

首次部署建议手动执行一次：

```bash
bash deploy.sh
```

这会安全执行：

- `git fetch`
- `git pull --ff-only`
- `composer install --no-dev`
- `php artisan migrate --force`
- `php artisan optimize:clear`
- `php artisan config:cache`
- `php artisan view:cache`
- `php artisan storage:link`

## 八、Nginx / Apache 注意事项

Web 根目录要指向：

```bash
public
```

不要把站点根目录直接指向项目根，否则会暴露不该公开的文件。

## 九、后续更新流程

以后每次更新代码，服务器只需要：

```bash
cd /www/wwwroot/GSS
bash deploy.sh
```

或者如果你想分开执行，也只允许这套白名单流程：

```bash
git fetch --prune origin
git pull --ff-only origin main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

## 十、哪些数据不会被 Git 覆盖

当前仓库已经明确排除了这些运行态内容：

- `.env`
- `storage/app/web`
- `storage/logs`
- `storage/framework`
- `database/database.sqlite`
- `tests`
- `tools`

所以 Git 更新不会直接覆盖：

- 线上环境配置
- 用户上传附件
- 站点运行文件
- 线上日志和缓存

## 十一、线上绝对不要执行的命令

```bash
php artisan migrate:fresh
php artisan migrate:fresh --seed
php artisan db:seed
php artisan test
rm -rf storage/app/web
rm -rf storage
```

## 十二、首次部署完成后检查

建议逐项确认：

```bash
php artisan about
php artisan route:list
php artisan migrate:status
```

再检查：

- 后台能否登录
- 上传附件是否正常
- 站点前台是否可访问
- 留言板、图宣、模板是否正常
- `storage/app/web` 是否正常保留

## 十三、建议的部署顺序

最稳妥的顺序是：

1. 本地开发并提交 Git
2. 推送到 GitHub
3. 测试服务器执行 `bash deploy.sh`
4. 验证通过后，再在正式服务器执行 `bash deploy.sh`

这样能把上线风险降到最低。
