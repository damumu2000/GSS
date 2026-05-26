# 服务器首次部署清单

这份清单用于把当前项目首次部署到服务器，并保证后续通过 Git 更新时不会误伤线上数据。

## 一、部署前原则

线上环境只允许做这些事：

- 拉取代码
- 安装生产依赖
- 执行安全迁移
- 清理并重建缓存

线上环境的 Git 工作目录应保持干净：

- 不要直接在服务器里改已被 Git 跟踪的代码文件
- 如果服务器目录里有本地改动，先处理掉再执行 `deploy.sh`
- 正式环境默认只允许部署 `main` 分支，不要在服务器上停留在功能分支

线上环境不要做这些事：

- `php artisan migrate:fresh`
- `php artisan db:seed`
- `php artisan test`
- 删除 `storage/app/web`
- 删除 `storage/logs`
- 删除 `.env`

线上依赖的第三方前端静态资源，优先使用本地文件，不依赖外部 CDN。

静态资源版本检查和一键升级统一通过平台后台执行：

- `平台配置 -> 系统检查`
- 不再使用独立脚本手动检查

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
- `CACHE_STORE=failover`
- `REDIS_CLIENT=phpredis`
- `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD`
- `REDIS_CACHE_DB`，必须使用当前应用独占的 Redis 数据库编号
- 如通过 URL 配置 Redis，缓存连接必须单独使用 `REDIS_CACHE_URL`，且数据库编号与 `REDIS_CACHE_DB` 规划一致
- `REDIS_TIMEOUT=1.0` / `REDIS_READ_TIMEOUT=1.0` / `REDIS_MAX_RETRIES=1`，确保故障时快速进入后备缓存
- `FRONTEND_PAGE_CACHE_ENABLED=true`
- `FRONTEND_PAGE_CACHE_TTL`
- 邮件、短信、对象存储等第三方配置

如果 `APP_KEY` 为空，再执行：

```bash
php artisan key:generate
```

当前缓存链路为 `redis -> database -> array`：Redis 正常时承载应用缓存；Redis 不可用时会降级到数据库缓存表，最后才使用当前进程内存。队列与会话仍默认使用 database，不随缓存切换到 Redis。

上线前需确认：

- PHP 已启用 `redis` 扩展，服务器已安装并启动 Redis。
- 线上 `.env` 必须同步为 `CACHE_STORE=failover`；如果仍是 `CACHE_STORE=file`，系统会继续使用旧文件缓存，不会启用 Redis。
- `REDIS_CACHE_DB` 仅供当前应用缓存使用；后台清理应用缓存会清空该 Redis 数据库。
- 不要仅设置带数据库编号的 `REDIS_URL` 来承载缓存连接；应使用 `REDIS_CACHE_URL`，避免缓存落入默认 Redis 库。
- 已执行迁移并存在 `cache`、`cache_locks` 表，否则 Redis 故障时无法使用 database 后备缓存。
- 从旧 `file` 缓存切换到 Redis 时，需清理 `storage/framework/cache/data` 下的旧缓存文件；保留该目录及 `.gitignore`。
- 执行部署脚本的缓存刷新步骤会清空专用 `REDIS_CACHE_DB` 与数据库 `cache` 表中的缓存数据，不会删除站点、用户、内容等业务表记录。
- 多站点或多环境共用同一个 Redis 服务时，应分配不同 `REDIS_CACHE_DB`，或至少设置不同 `CACHE_PREFIX`；数据库独占更稳妥。
- 如果后台状态曾显示 Redis“已降级”，修复 Redis 后应在 `系统检查 -> 清除缓存` 执行一次“应用缓存”，清除故障期间各层可能留下的旧缓存数据。

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
- 清除 `storage/framework/cache/data` 下旧 `file` 缓存文件（保留目录与 `.gitignore`）
- `php artisan cache:clear database`
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

如需显式指定允许部署的分支，可以先设置：

```bash
export DEPLOY_BRANCH=main
bash deploy.sh
```

如果脚本提示：

```bash
[deploy] repository has local changes, aborting.
```

说明服务器上有人直接改过 Git 跟踪文件。先处理这些本地修改，再继续部署，不要强行覆盖。

如果脚本提示：

```bash
[deploy] current branch 'xxx' does not match allowed deploy branch 'main', aborting.
```

说明服务器当前停在错误分支。先切回正式分支，再继续部署。

或者如果你想分开执行，也只允许这套白名单流程：

```bash
git fetch --prune origin
git pull --ff-only origin main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
find storage/framework/cache/data -type f ! -name '.gitignore' -delete
find storage/framework/cache/data -mindepth 1 -type d -empty -delete
php artisan cache:clear database
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
- 平台后台 `系统检查 -> 性能缓存检查` 中的 `Redis 应用缓存` 是否显示运行中
- 平台仪表盘中的 `Redis 应用缓存` 是否显示 `Redis：运行` 与 `整页缓存：开启`
- 上传附件是否正常
- 站点前台是否可访问
- 留言板、图宣、模板是否正常
- `storage/app/web` 是否正常保留

可在服务器执行以下只读/临时写入验证；临时键会立即删除：

```bash
php artisan tinker --execute='$key="deploy-redis-check-".uniqid(); Cache::store("redis")->put($key, "ok", 30); dump(Cache::store("redis")->get($key)); Cache::store("redis")->forget($key);'
php artisan tinker --execute='dump(config("cache.default")); dump(config("cache.stores.failover.stores"));'
```

预期结果分别包含 `"ok"`、`"failover"` 和 `["redis", "database", "array"]`。

如需检查或升级本地静态第三方资源，请直接在平台后台进入“系统检查”页面操作。

## 十三、建议的部署顺序

最稳妥的顺序是：

1. 本地开发并提交 Git
2. 推送到 GitHub
3. 测试服务器执行 `bash deploy.sh`
4. 验证通过后，再在正式服务器执行 `bash deploy.sh`

这样能把上线风险降到最低。
