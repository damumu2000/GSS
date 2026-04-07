@extends('layouts.admin')

@section('title', '编辑工资批次 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资信息 / 编辑工资批次')

@push('styles')
    <style>
        .payroll-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding: 24px 32px;
            margin: -28px -28px 24px;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }
        .payroll-header-title {
            margin: 0;
            color: #1d2939;
            font-size: 22px;
            line-height: 1.35;
            font-weight: 800;
        }
        .payroll-header-desc {
            margin-top: 8px;
            color: #667085;
            font-size: 14px;
            line-height: 1.75;
        }
        .payroll-edit-shell {
            display: grid;
            gap: 18px;
            width: 100%;
        }
        .payroll-edit-errors {
            padding: 16px 18px;
            border: 1px solid rgba(220, 38, 38, 0.14);
            border-radius: 18px;
            background: #fff6f5;
            color: #b42318;
        }
        .payroll-edit-errors-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
        }
        .payroll-edit-errors-list {
            margin: 10px 0 0;
            padding-left: 18px;
            font-size: 13px;
            line-height: 1.8;
        }
        .payroll-edit-card {
            padding: 28px 30px 30px;
            border: 1px solid #e8edf4;
            border-radius: 28px;
            background:
                radial-gradient(circle at top left, var(--primary-soft-strong), transparent 26%),
                linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.05);
        }
        .payroll-edit-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid #edf2f7;
        }
        .payroll-edit-batch-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .payroll-edit-batch-title {
            margin: 0;
            color: #1d2939;
            font-size: 24px;
            line-height: 1.3;
            font-weight: 800;
        }
        .payroll-edit-batch-badge {
            display: inline-flex;
            align-items: center;
            height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 700;
        }
        .payroll-edit-batch-badge.is-muted {
            background: #f4f7fb;
            color: #667085;
        }
        .payroll-edit-actions .button {
            min-width: 126px;
            height: 46px;
            border-radius: 999px;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(0, 80, 179, 0.16);
        }
        .payroll-edit-actions .button.secondary {
            box-shadow: none;
        }
        .payroll-edit-actions .button[disabled] {
            opacity: 0.82;
            cursor: wait;
        }
        .payroll-edit-upload-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .payroll-edit-upload-card {
            display: grid;
            gap: 14px;
            padding: 22px 22px 20px;
            border: 1px solid #e7edf4;
            border-radius: 22px;
            background: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .payroll-edit-upload-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .payroll-edit-upload-heading {
            color: #1d2939;
            font-size: 18px;
            font-weight: 800;
        }
        .payroll-edit-upload-file {
            color: #98a2b3;
            font-size: 13px;
            line-height: 1.8;
        }
        .payroll-edit-upload-file strong {
            color: #475467;
            font-weight: 700;
        }
        .payroll-edit-upload-input {
            position: relative;
            display: flex;
            align-items: center;
            min-height: 58px;
            padding: 0 18px;
            border: 1px dashed #cfd8e3;
            border-radius: 18px;
            background: #f8fbff;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
            overflow: hidden;
        }
        .payroll-edit-upload-input:hover {
            border-color: var(--primary-border-soft);
            box-shadow: 0 0 0 4px var(--primary-soft-strong);
            background: #fff;
        }
        .payroll-edit-upload-input input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .payroll-edit-upload-placeholder {
            color: #667085;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 600;
        }
        .payroll-edit-upload-card.is-selected .payroll-edit-upload-input {
            border-color: var(--primary-border-soft);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft-strong);
        }
        .payroll-edit-upload-card.is-selected .payroll-edit-upload-placeholder {
            color: var(--primary-dark);
        }
        .payroll-edit-upload-selected {
            min-height: 20px;
            color: var(--primary-dark);
            font-size: 13px;
            line-height: 1.6;
            font-weight: 600;
        }
        .payroll-edit-upload-selected.is-empty {
            color: #98a2b3;
            font-weight: 500;
        }
        .payroll-edit-upload-error {
            min-height: 18px;
            color: #d92d20;
            font-size: 12px;
            line-height: 1.5;
        }
        .payroll-edit-export {
            display: flex;
            justify-content: flex-end;
            margin-top: 4px;
        }
        .payroll-edit-export .button.secondary {
            min-width: 118px;
            height: 40px;
            border-radius: 999px;
            box-shadow: none;
        }
        .payroll-export-button[aria-busy="true"] {
            opacity: 0.86;
            cursor: wait;
            pointer-events: none;
        }
        .payroll-edit-submit-status {
            display: none;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            color: #667085;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 600;
        }
        .payroll-edit-submit-status.is-active {
            display: inline-flex;
        }
        .payroll-edit-submit-status.is-warning {
            color: #b45309;
        }
        .payroll-edit-submit-status.is-warning .payroll-edit-submit-spinner {
            display: none;
        }
        .payroll-edit-submit-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid var(--primary-soft);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: payroll-spin 0.8s linear infinite;
            flex-shrink: 0;
        }
        @keyframes payroll-spin {
            to {
                transform: rotate(360deg);
            }
        }
        .payroll-edit-result {
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid #edf2f7;
        }
        .payroll-edit-result-title {
            margin: 0;
            color: #1d2939;
            font-size: 18px;
            line-height: 1.4;
            font-weight: 800;
        }
        .payroll-edit-result-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .payroll-edit-result-card {
            padding: 18px;
            border: 1px solid #e7edf4;
            border-radius: 20px;
            background: #fff;
        }
        .payroll-edit-result-card-title {
            color: #1d2939;
            font-size: 15px;
            font-weight: 800;
        }
        .payroll-edit-result-card-meta {
            margin-top: 8px;
            color: #667085;
            font-size: 13px;
            line-height: 1.8;
        }
        .payroll-edit-sheet-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .payroll-edit-sheet-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
        }
        .payroll-edit-sheet-name {
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }
        .payroll-edit-sheet-mode {
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.6;
        }
        .payroll-edit-sheet-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 66px;
            height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }
        @media (max-width: 960px) {
            .payroll-edit-card {
                padding: 22px 20px 22px;
            }
            .payroll-edit-topbar,
            .payroll-edit-upload-grid,
            .payroll-edit-result-grid {
                grid-template-columns: 1fr;
                display: grid;
            }
            .payroll-edit-actions {
                justify-self: start;
            }
        }
    </style>
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.payroll.batches.update', $batch->id) }}" enctype="multipart/form-data" id="payroll-batch-edit-form">
        @csrf

        <div class="payroll-header">
            <div>
                <h1 class="payroll-header-title">编辑工资批次</h1>
                <div class="payroll-header-desc">重新上传工资表或绩效表后，系统会立即重新解析并覆盖当前月份对应结果。</div>
            </div>
            <div class="page-header-actions">
                <a class="button secondary" href="{{ route('admin.payroll.batches.index') }}">返回列表</a>
            </div>
        </div>

        @include('payroll::admin._nav')

        <div class="payroll-edit-shell">
            @if ($errors->any())
                <section class="payroll-edit-errors">
                    <h2 class="payroll-edit-errors-title">请先修正以下内容</h2>
                    <ul class="payroll-edit-errors-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="payroll-edit-card">
                <div class="payroll-edit-topbar">
                    <div class="payroll-edit-batch-meta">
                        <h2 class="payroll-edit-batch-title">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch->month_key)->format('Y年n月') }}</h2>
                        <span class="payroll-edit-batch-badge {{ $batch->status === 'imported' ? '' : 'is-muted' }}">{{ $batch->status === 'imported' ? '已解析' : '待上传' }}</span>
                        <span class="payroll-edit-batch-badge is-muted">工资 {{ $recordStats['salary'] }}</span>
                        <span class="payroll-edit-batch-badge is-muted">绩效 {{ $recordStats['performance'] }}</span>
                    </div>
                    <div class="payroll-edit-actions">
                        <button class="button" type="submit" id="payroll-batch-submit">保存并解析</button>
                    </div>
                </div>

                <div class="payroll-edit-upload-grid">
                    <section class="payroll-edit-upload-card" data-upload-card>
                        <div class="payroll-edit-upload-title">
                            <span class="payroll-edit-upload-heading">工资表</span>
                        </div>
                        <div class="payroll-edit-upload-file">
                            当前文件：<strong>{{ $batch->salary_file_name ?: '未上传' }}</strong>
                        </div>
                        <label class="payroll-edit-upload-input">
                            <input type="file" name="salary_file" accept=".xls,.xlsx" data-upload-input>
                            <span class="payroll-edit-upload-placeholder" data-upload-placeholder>选择新的工资表文件（xls / xlsx）</span>
                        </label>
                        <div class="payroll-edit-upload-selected is-empty" data-upload-selected>尚未选择新文件</div>
                        <div class="payroll-edit-upload-error">@error('salary_file'){{ $message }}@enderror</div>
                        @if ($recordStats['salary'] > 0)
                            <div class="payroll-edit-export">
                                <a
                                    class="button secondary payroll-export-button"
                                    href="{{ route('admin.payroll.batches.export', ['batch' => $batch->id, 'type' => 'salary']) }}"
                                    data-loading-text="生成中…"
                                >导出工资</a>
                            </div>
                        @endif
                    </section>

                    <section class="payroll-edit-upload-card" data-upload-card>
                        <div class="payroll-edit-upload-title">
                            <span class="payroll-edit-upload-heading">绩效表</span>
                        </div>
                        <div class="payroll-edit-upload-file">
                            当前文件：<strong>{{ $batch->performance_file_name ?: '未上传' }}</strong>
                        </div>
                        <label class="payroll-edit-upload-input">
                            <input type="file" name="performance_file" accept=".xls,.xlsx" data-upload-input>
                            <span class="payroll-edit-upload-placeholder" data-upload-placeholder>选择新的绩效表文件（xls / xlsx）</span>
                        </label>
                        <div class="payroll-edit-upload-selected is-empty" data-upload-selected>尚未选择新文件</div>
                        <div class="payroll-edit-upload-error">@error('performance_file'){{ $message }}@enderror</div>
                        @if ($recordStats['performance'] > 0)
                            <div class="payroll-edit-export">
                                <a
                                    class="button secondary payroll-export-button"
                                    href="{{ route('admin.payroll.batches.export', ['batch' => $batch->id, 'type' => 'performance']) }}"
                                    data-loading-text="生成中…"
                                >导出绩效</a>
                            </div>
                        @endif
                    </section>
                </div>

                <div class="payroll-edit-submit-status" id="payroll-batch-submit-status" aria-live="polite">
                    <span class="payroll-edit-submit-spinner" aria-hidden="true"></span>
                    <span>正在解析表格，请稍候…</span>
                </div>

                @if (!empty($importSummary) && is_array($importSummary))
                    <section class="payroll-edit-result">
                        <h3 class="payroll-edit-result-title">最近一次解析结果</h3>
                        <div class="payroll-edit-result-grid">
                            @foreach ($importSummary as $type => $summary)
                                <article class="payroll-edit-result-card">
                                    <div class="payroll-edit-result-card-title">{{ $type === 'salary' ? '工资表' : '绩效表' }}</div>
                                    <div class="payroll-edit-result-card-meta">
                                        已匹配 {{ $summary['matched'] ?? 0 }} 位员工
                                        @if (!empty($summary['imported_at']))
                                            ，解析时间 {{ $summary['imported_at'] }}
                                        @endif
                                    </div>

                                    @if (!empty($summary['sheets']) && is_iterable($summary['sheets']))
                                        <div class="payroll-edit-sheet-list">
                                            @foreach ($summary['sheets'] as $sheet)
                                                <div class="payroll-edit-sheet-item">
                                                    <div>
                                                        <div class="payroll-edit-sheet-name">{{ $sheet['name'] ?? '未命名工作表' }}</div>
                                                        <div class="payroll-edit-sheet-mode">
                                                            @if (($sheet['mode'] ?? '') === 'paired')
                                                                并排双表模式
                                                            @elseif (($sheet['mode'] ?? '') === 'persisted')
                                                                当前已解析数据
                                                            @else
                                                                标准横表模式
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <span class="payroll-edit-sheet-count">{{ $sheet['matched'] ?? 0 }} 条</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </section>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('payroll-batch-edit-form');
            if (!form) {
                return;
            }

            const submitButton = document.getElementById('payroll-batch-submit');
            const submitStatus = document.getElementById('payroll-batch-submit-status');
            const uploadInputs = Array.from(form.querySelectorAll('[data-upload-input]'));
            const exportButtons = Array.from(document.querySelectorAll('.payroll-export-button'));

            form.querySelectorAll('[data-upload-card]').forEach((card) => {
                const input = card.querySelector('[data-upload-input]');
                const placeholder = card.querySelector('[data-upload-placeholder]');
                const selected = card.querySelector('[data-upload-selected]');

                if (!input || !placeholder || !selected) {
                    return;
                }

                input.addEventListener('change', () => {
                    const file = input.files && input.files[0] ? input.files[0] : null;
                    const hasFile = Boolean(file);
                    card.classList.toggle('is-selected', hasFile);
                    selected.classList.toggle('is-empty', !hasFile);

                    if (!hasFile) {
                        placeholder.textContent = input.name === 'salary_file'
                            ? '选择新的工资表文件（xls / xlsx）'
                            : '选择新的绩效表文件（xls / xlsx）';
                        selected.textContent = '尚未选择新文件';
                        return;
                    }

                    placeholder.textContent = '已选择新文件，保存后将重新解析';
                    selected.textContent = `已选择：${file.name}`;
                });
            });

            form.addEventListener('submit', (event) => {
                const hasNewFile = uploadInputs.some((input) => input.files && input.files.length > 0);

                if (!hasNewFile) {
                    event.preventDefault();
                    submitStatus?.classList.add('is-active', 'is-warning');
                    submitStatus?.classList.remove('is-success');
                    const messageNode = submitStatus?.querySelector('span:last-child');
                    if (messageNode) {
                        messageNode.textContent = '请先选择新的工资表或绩效表，再进行解析。';
                    }
                    return;
                }

                submitStatus?.classList.remove('is-warning');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = '解析中…';
                }
                const messageNode = submitStatus?.querySelector('span:last-child');
                if (messageNode) {
                    messageNode.textContent = '正在解析表格，请稍候…';
                }
                submitStatus?.classList.add('is-active');
            });

            exportButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (button.getAttribute('aria-busy') === 'true') {
                        return;
                    }

                    button.dataset.originalText = button.textContent?.trim() || '';
                    button.textContent = button.dataset.loadingText || '生成中…';
                    button.setAttribute('aria-busy', 'true');

                    window.setTimeout(() => {
                        button.textContent = button.dataset.originalText || '导出';
                        button.setAttribute('aria-busy', 'false');
                    }, 1800);
                });
            });
        })();
    </script>
@endpush
