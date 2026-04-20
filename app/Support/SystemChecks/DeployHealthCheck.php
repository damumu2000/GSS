<?php

namespace App\Support\SystemChecks;

use Illuminate\Support\Facades\File;

class DeployHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $items = [
            $this->gitBranchItem(),
            $this->gitWorktreeItem(),
            $this->deployScriptItem(),
            $this->envFileItem(),
        ];

        return [
            'key' => 'deploy',
            'title' => '部署状态检查',
            'status' => $this->overallStatus($items),
            'summary' => '检查当前分支、工作区状态、部署脚本和环境文件准备情况。',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function gitBranchItem(): array
    {
        $branch = trim((string) $this->runGitCommand('git branch --show-current'));

        if ($branch === 'main') {
            return [
                'label' => '部署分支',
                'status' => 'ok',
                'value' => 'main',
                'message' => '当前代码位于正式部署分支。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        if ($branch === '') {
            return [
                'label' => '部署分支',
                'status' => 'warning',
                'value' => '无法识别',
                'message' => '当前环境无法确认 Git 分支信息。',
                'suggestion' => '请检查服务器目录是否为完整 Git 工作区。',
                'details' => '',
            ];
        }

        return [
            'label' => '部署分支',
            'status' => 'warning',
            'value' => $branch,
            'message' => '当前分支不是 main。',
            'suggestion' => '正式环境建议切回 main 后再执行 deploy.sh。',
            'details' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function gitWorktreeItem(): array
    {
        $status = trim((string) $this->runGitCommand('git status --porcelain'));

        if ($status === '') {
            return [
                'label' => '工作区状态',
                'status' => 'ok',
                'value' => 'clean',
                'message' => 'Git 工作区干净，可以安全执行部署脚本。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        return [
            'label' => '工作区状态',
            'status' => 'warning',
            'value' => '存在本地改动',
            'message' => '服务器目录存在未提交或未清理的变更。',
            'suggestion' => '请先处理本地改动，再执行 deploy.sh。',
            'details' => collect(explode("\n", $status))->filter()->take(6)->implode('；'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function deployScriptItem(): array
    {
        $path = base_path('deploy.sh');

        if (is_file($path) && is_executable($path)) {
            return [
                'label' => '部署脚本',
                'status' => 'ok',
                'value' => 'deploy.sh',
                'message' => '安全部署脚本存在且可执行。',
                'suggestion' => '',
                'details' => '',
            ];
        }

        return [
            'label' => '部署脚本',
            'status' => 'error',
            'value' => '缺失或不可执行',
            'message' => '未检测到可执行的 deploy.sh。',
            'suggestion' => '请确认 deploy.sh 已同步并具备执行权限。',
            'details' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function envFileItem(): array
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            return [
                'label' => '环境文件',
                'status' => 'error',
                'value' => '.env 缺失',
                'message' => '系统运行缺少环境配置文件。',
                'suggestion' => '请先根据 .env.example 配置正式环境参数。',
                'details' => '',
            ];
        }

        return [
            'label' => '环境文件',
            'status' => 'ok',
            'value' => '.env 已存在',
            'message' => '环境文件已准备。',
            'suggestion' => '',
            'details' => '',
        ];
    }

    protected function runGitCommand(string $command): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $fullCommand = sprintf(
            'cd %s && %s 2>/dev/null',
            escapeshellarg(base_path()),
            $command
        );

        $output = @shell_exec($fullCommand);

        return is_string($output) ? $output : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function overallStatus(array $items): string
    {
        $priority = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $max = 0;

        foreach ($items as $item) {
            $max = max($max, $priority[$item['status']] ?? 0);
        }

        return array_search($max, $priority, true) ?: 'ok';
    }
}
